<?php

namespace App\Sync;

use App\Support\AppMode;
use App\Sync\Engine\ReplicationRules;
use App\Sync\Exceptions\WrongSideEdit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * PD-34, edits (decided 2026-11-24, option 1): data is changed only on the
 * side that owns it. A change on the other side would never reach the
 * owner and would be overwritten by the owner's next change to that row,
 * without warning, so it is refused before anything is saved, with a clear
 * message.
 *
 * Who owns what is not decided here: it is read from the replication rules
 * in config/sync.php, the same rules the sync engine applies on arrival.
 *  - A one-way table belongs wholly to its sender: "up" tables to the
 *    vessel, "down" tables to the office.
 *  - A table both sides change may name the side that creates its rows
 *    ('creates'), the side that keeps each row ('row_owner'), and the owner
 *    of each column ('columns'). Shared columns are changed on both sides.
 *  - On board, the office-only columns (PD-73) count as the office's.
 *  - A guarded transition lets the other side make one named change (the
 *    office approving a completed PMS job).
 *
 * Where the rule applies:
 *  - On a vessel server, to every table that travels (SYNC_TABLES).
 *  - On the cloud, to rows of a vessel that runs its own server, with
 *    navisuite.cloud.lock_offline_vessels on. For any other vessel the
 *    cloud holds the only copy, so nothing is overwritten and nothing
 *    changes.
 *  - Never to the sync engine's own writes, nor to a write with capture
 *    switched off (neither travels), nor to schema migrations (they run
 *    the same on every server and are nobody's edit).
 *  - Timestamps, the sync columns and a table's local columns are ignored.
 *
 * Every Eloquent save is checked (SyncServiceProvider). The few bulk
 * query-builder writes that can reach such rows call assertMayWrite(), or
 * leave out vesselsKeepingTheirOwn(), themselves.
 */
class WrongSideEdits
{
    private const TIMESTAMPS = ['created_at', 'updated_at'];

    /** Schema migrations in progress: they run the same on every server and are nobody's edit. */
    private static int $migrating = 0;

    public static function migrationsStarted(): void
    {
        self::$migrating++;
    }

    public static function migrationsEnded(): void
    {
        self::$migrating = max(0, self::$migrating - 1);
    }

    /** Set while outsideTheApplication() runs. */
    private static int $outside = 0;

    /**
     * Runs $change without the ownership check, as a change made directly in
     * the database (or by an older release) would be. Only for tests that
     * prove sync still refuses and flags such a change when it arrives. No
     * application code calls it (a test checks this).
     */
    public static function outsideTheApplication(callable $change): mixed
    {
        self::$outside++;
        try {
            return $change();
        } finally {
            self::$outside--;
        }
    }

    /** Writes the check never applies to: sync's own, uncaptured, migrations, and outsideTheApplication(). */
    private static function exempt(): bool
    {
        return self::$migrating > 0 || self::$outside > 0 || SyncContext::origin() !== null || ! SyncContext::captureEnabled();
    }

    public function __construct(
        private SyncRegistry $registry,
        private ReplicationRules $rules,
        private VesselOwnership $ownership,
    ) {
    }

    /** Throws WrongSideEdit when saving $model would change the other side's data. */
    public function check(Model $model): void
    {
        $refusal = $this->refusalFor($model);
        if ($refusal !== null) {
            throw new WrongSideEdit($refusal);
        }
    }

    /** Why saving $model must be refused, or null when it may be saved. */
    public function refusalFor(Model $model): ?string
    {
        $table = $model->getTable();
        $side = $this->sideFor($table);
        if ($side === null) {
            return null;
        }

        $values = array_merge($model->getRawOriginal(), $model->getAttributes());
        if ($side === ReplicationRules::CLOUD && ! $this->forOfflineVessel($model, $table, $values)) {
            return null;
        }

        $problem = $model->exists
            ? $this->updateProblem($table, $model, $values, $side)
            : $this->insertProblem($table, $values, $side);

        return $problem === null ? null : $this->message($table, $problem, $side);
    }

    /**
     * For bulk query-builder writes: refuse writing $columns (null = whole
     * rows) of $table for $vesselId when that is the other side's data.
     */
    public function assertMayWrite(string $table, ?int $vesselId, ?array $columns = null): void
    {
        $side = $this->sideFor($table);
        if ($side === null) {
            return;
        }
        if ($side === ReplicationRules::CLOUD && ($vesselId === null || ! app(AppMode::class)->vesselIsOfflineEnabled($vesselId))) {
            return;
        }

        $other = $this->otherSide($side);
        $problem = $this->wholeTableOwner($table) === $other
            ? 'the whole record'
            : $this->columnProblem($table, $columns ?? [], [], [], $side);

        if ($problem !== null) {
            throw new WrongSideEdit($this->message($table, $problem, $side));
        }
    }

    /**
     * For bulk query-builder writes that span vessels: on the cloud, the
     * vessels whose rows of $table must be left alone (those that run their
     * own server). Empty where the rule does not apply.
     *
     * @return list<int>
     */
    public function vesselsKeepingTheirOwn(string $table): array
    {
        if ($this->sideFor($table) !== ReplicationRules::CLOUD || $this->wholeTableOwner($table) !== ReplicationRules::VESSEL) {
            return [];
        }
        return $this->offlineVessels();
    }

    /**
     * Query-builder inserts (App\Sync\Database\OwnershipGuardedBuilder):
     * refuses the batch when any row would be a wrong-side insert.
     *
     * @param  list<array<string, mixed>|object>  $rows
     */
    public function checkInsertRows(string $table, array $rows): void
    {
        $side = $this->sideFor($table);
        if ($side === null) {
            return;
        }
        $offline = $side === ReplicationRules::CLOUD ? $this->offlineVessels() : [];
        if ($side === ReplicationRules::CLOUD && $offline === []) {
            return;
        }

        foreach ($rows as $row) {
            $row = (array) $row;
            if ($side === ReplicationRules::CLOUD) {
                try {
                    $vessel = $this->vesselOfValues($table, $row);
                } catch (Throwable) {
                    $vessel = null;
                }
                if ($vessel === null || ! in_array($vessel, $offline, true)) {
                    continue;
                }
            }
            $problem = $this->insertProblem($table, $row, $side);
            if ($problem !== null) {
                throw new WrongSideEdit($this->message($table, $problem, $side));
            }
        }
    }

    /**
     * INSERT ... SELECT: the rows cannot be seen before they are written. On
     * board, a table only the office fills is refused; on the cloud it is
     * left alone (the application has no such write).
     */
    public function checkInsertFromQuery(string $table): void
    {
        if ($this->sideFor($table) !== ReplicationRules::VESSEL) {
            return;
        }
        $problem = match (true) {
            $this->wholeTableOwner($table) === ReplicationRules::CLOUD => 'the whole record',
            $this->rules->creator($table) === ReplicationRules::CLOUD => 'new records',
            default => null,
        };
        if ($problem !== null) {
            throw new WrongSideEdit($this->message($table, $problem, ReplicationRules::VESSEL));
        }
    }

    /**
     * Query-builder updates, mass updates included: refused when a row they
     * change is the other side's, or a column they set belongs to the other
     * side (unless every change is a guarded transition).
     *
     * @param  array<string, mixed>  $values
     */
    public function checkUpdateQuery(QueryBuilder $query, string $table, ?string $alias, array $values): void
    {
        $side = $this->sideFor($table);
        if ($side === null) {
            return;
        }
        $ref = $alias ?? $table;
        $rows = (clone $query)->reorder();

        if ($side === ReplicationRules::CLOUD) {
            $hit = $this->offlineVesselsAmong($rows, $table, $ref);
            if ($hit === []) {
                return;
            }
            $rows->whereRaw('('.$this->vesselExpression($table, $ref).') IN ('.implode(',', $hit).')');
        }

        $other = $this->otherSide($side);
        $problem = null;
        if ($this->wholeTableOwner($table) === $other) {
            $problem = 'the whole record';
        } elseif (($owner = $this->rules->rule($table)['row_owner']['column'] ?? null) !== null
            && (clone $rows)->pluck($ref.'.'.$owner)->contains(fn ($v) => $this->rules->rowOwner($table, [$owner => $v]) === $other)) {
            $problem = 'this record';
        } elseif ($this->otherSidesParent($rows, $table, $ref, $values, $other)) {
            $problem = 'this record';
        } else {
            $columns = [];
            foreach (array_keys($values) as $column) {
                $column = preg_replace('/->.*$/', '', (string) $column);
                $columns[] = str_contains($column, '.') ? substr($column, strrpos($column, '.') + 1) : $column;
            }
            $problem = $this->columnProblem($table, array_unique($columns), [], [], $side,
                fn (string $column) => $this->allGuarded($rows, $table, $ref, $column, $values));
        }

        if ($problem !== null && (clone $rows)->exists()) {
            throw new WrongSideEdit($this->message($table, $problem, $side));
        }
    }

    /**
     * PD-34, deletes, for query-builder deletes: on the cloud, refuse
     * deleting the own record of a vessel that runs its own server (the
     * rule App\Sync\VesselOwnership already applies to model deletes).
     */
    public function checkDeleteQuery(QueryBuilder $query, string $table, ?string $alias): void
    {
        if (self::exempt()) {
            return;
        }
        // On board: a record the office keeps in a shared table (an office
        // document, its link to an office procurement stage) is not
        // removed here (PD-22, PD-59 B).
        if ($this->sideFor($table) === ReplicationRules::VESSEL) {
            $rows = (clone $query)->reorder();
            $ref = $alias ?? $table;
            $owner = $this->rules->rule($table)['row_owner']['column'] ?? null;
            $office = ($owner !== null && (clone $rows)->pluck($ref.'.'.$owner)->contains(fn ($v) => $this->rules->rowOwner($table, [$owner => $v]) === ReplicationRules::CLOUD))
                || $this->otherSidesParent($rows, $table, $ref, [], ReplicationRules::CLOUD);
            if ($office) {
                throw new WrongSideEdit($this->message($table, 'this record', ReplicationRules::VESSEL));
            }

            return;
        }
        if (! app(AppMode::class)->isCloud() || ! config('navisuite.cloud.lock_offline_vessels', true) || ! $this->registry->isSynced($table)) {
            return;
        }
        $ref = $alias ?? $table;
        $rows = (clone $query)->reorder();
        $hit = $this->offlineVesselsAmong($rows, $table, $ref);
        if ($hit === []) {
            return;
        }

        $expression = $this->vesselExpression($table, $ref);
        $found = $rows->select($ref.'.*')->selectRaw('('.$expression.') AS wse_vessel')
            ->whereRaw('('.$expression.') IN ('.implode(',', $hit).')')->get();
        foreach ($found as $row) {
            $values = (array) $row;
            $vessel = (int) $values['wse_vessel'];
            unset($values['wse_vessel']);
            if (($why = $this->ownership->reason($table, $values, $vessel)) !== null) {
                throw new \App\Sync\Exceptions\VesselRecordProtected($this->ownership->deleteRefusalMessage($table, $why));
            }
        }
    }

    /**
     * owned_with: whether a row the query touches, or the parent a written
     * value points it to, belongs to the other side.
     */
    private function otherSidesParent(QueryBuilder $rows, string $table, string $ref, array $values, string $other): bool
    {
        $with = $this->rules->ownedWith($table);
        if ($with === null) {
            return false;
        }
        $parents = (clone $rows)->pluck($ref.'.'.$with['column'])->all();
        foreach ($values as $column => $value) {
            if (preg_replace('/^.*\./', '', (string) $column) === $with['column']) {
                $parents[] = $value;
            }
        }
        foreach (array_unique(array_filter($parents, fn ($v) => $v !== null)) as $id) {
            if ($this->rules->parentOwner($table, [$with['column'] => $id]) === $other) {
                return true;
            }
        }

        return false;
    }

    /** @return list<int> the vessels running their own server (none before the column exists). */
    private function offlineVessels(): array
    {
        return app(AppMode::class)->offlineEnabledVesselIds();
    }

    /** @return list<int> the vessels running their own server whose rows $rows touches. */
    private function offlineVesselsAmong(QueryBuilder $rows, string $table, string $ref): array
    {
        $offline = $this->offlineVessels();
        $expression = $offline === [] ? null : $this->vesselExpression($table, $ref);
        if ($expression === null) {
            return [];
        }
        try {
            $touched = (clone $rows)->selectRaw('DISTINCT ('.$expression.') AS wse_vessel')->pluck('wse_vessel')
                ->filter(fn ($v) => $v !== null)->map(fn ($v) => (int) $v)->all();
        } catch (Throwable $e) {
            report($e);   // a query whose rows cannot be traced to a vessel is not refused, but reported

            return [];
        }

        return array_values(array_intersect($offline, $touched));
    }

    private function vesselExpression(string $table, string $ref): ?string
    {
        $expression = $this->registry->definition($table)['vessel'] ?? null;

        return $expression === null ? null : str_replace('{row}', $ref, $expression);
    }

    /** Whether every row $rows changes in $column makes a guarded transition to its new value. */
    private function allGuarded(QueryBuilder $rows, string $table, string $ref, string $column, array $values): bool
    {
        $pairs = $this->rules->guardedTransitions($table, $column);
        $new = $values[$column] ?? $values[$ref.'.'.$column] ?? null;
        if ($pairs === [] || ! is_scalar($new)) {
            return false;
        }
        foreach ((clone $rows)->distinct()->pluck($ref.'.'.$column) as $from) {
            if (! in_array([(string) $from, (string) $new], array_map(fn ($p) => [(string) $p[0], (string) $p[1]], $pairs), true)) {
                return false;
            }
        }

        return true;
    }

    /** cloud or vessel: the side this server is, when the rule applies to $table here; null when it does not. */
    private function sideFor(string $table): ?string
    {
        if (self::exempt()) {
            return null;
        }
        if (! $this->registry->isSynced($table) || ! $this->rules->isEnabled($table)) {
            return null;
        }

        $mode = app(AppMode::class);
        if ($mode->isVessel()) {
            return ReplicationRules::VESSEL;
        }
        if ($mode->isCloud() && config('navisuite.cloud.lock_offline_vessels', true)) {
            return ReplicationRules::CLOUD;
        }

        return null;
    }

    private function forOfflineVessel(Model $model, string $table, array $values): bool
    {
        try {
            $vessel = $model->exists
                ? $this->ownership->vesselOf($table, $model->getKeyName(), $model->getKey())
                : $this->vesselOfValues($table, $values);
        } catch (Throwable) {
            $vessel = null;   // a partial schema, or values that do not name a vessel
        }

        return $vessel !== null && app(AppMode::class)->vesselIsOfflineEnabled($vessel);
    }

    /** The vessel a row not yet saved belongs to, from its values and the table's sync definition. */
    private function vesselOfValues(string $table, array $values): ?int
    {
        $expression = $this->registry->definition($table)['vessel'];
        if ($expression === null) {
            return null;
        }
        if (preg_match('/^\{row\}\.(\w+)$/', $expression, $m)) {
            $vessel = $values[$m[1]] ?? null;

            return $vessel === null || $vessel === '' ? null : (int) $vessel;
        }

        // A lookup through a parent row: run it against the new row's values.
        // A column the lookup names but the row leaves out counts as empty.
        $grammar = DB::connection()->getQueryGrammar();
        $columns = array_filter($values, fn ($v) => $v === null || is_scalar($v));
        preg_match_all('/\{row\}\.(\w+)/', $expression, $named);
        foreach ($named[1] as $column) {
            $columns += [$column => null];
        }
        if ($columns === []) {
            return null;
        }
        $select = implode(', ', array_map(fn ($c) => '? AS '.$grammar->wrap($c), array_keys($columns)));
        $vessel = DB::query()
            ->fromSub(DB::query()->selectRaw($select, array_values($columns)), $table)
            ->value(DB::raw(str_replace('{row}', $table, $expression)));

        return $vessel === null ? null : (int) $vessel;
    }

    private function insertProblem(string $table, array $values, string $side): ?string
    {
        $other = $this->otherSide($side);

        return match (true) {
            $this->wholeTableOwner($table) === $other => 'the whole record',
            $this->rules->creator($table) === $other => 'new records',
            $this->rules->ownerOfRow($table, $values) === $other => 'this record',
            default => null,
        };
    }

    private function updateProblem(string $table, Model $model, array $values, string $side): ?string
    {
        $other = $this->otherSide($side);
        if ($this->wholeTableOwner($table) === $other) {
            return 'the whole record';
        }
        if ($this->rules->ownerOfRow($table, $model->getRawOriginal()) === $other
            || $this->rules->ownerOfRow($table, $values) === $other) {
            return 'this record';
        }

        $dirty = array_keys($model->getDirty());

        return $this->columnProblem($table, $dirty, $model->getRawOriginal(), $model->getAttributes(), $side);
    }

    /** The other side's columns among $columns, as "a, b", or null when there are none. */
    private function columnProblem(string $table, array $columns, array $original, array $new, string $side, ?callable $guarded = null): ?string
    {
        $other = $this->otherSide($side);
        $ignored = [...self::TIMESTAMPS, ...$this->rules->unsentColumns($table)];
        $officeOnly = $side === ReplicationRules::VESSEL ? $this->rules->officeOnlyColumns($table) : [];

        $refused = [];
        foreach ($columns as $column) {
            if (in_array($column, $ignored, true)) {
                continue;
            }
            $owner = in_array($column, $officeOnly, true) ? ReplicationRules::CLOUD : $this->rules->ownerOf($table, $column);
            if ($owner !== $other || $this->guardedChange($table, $column, $original, $new) || ($guarded !== null && $guarded($column))) {
                continue;
            }
            $refused[] = $column;
        }

        return $refused === [] ? null : implode(', ', $refused);
    }

    /** A change the other side is allowed to make to this column (a guarded [from, to] pair). */
    private function guardedChange(string $table, string $column, array $original, array $new): bool
    {
        if (! array_key_exists($column, $original) || ! array_key_exists($column, $new)) {
            return false;
        }
        foreach ($this->rules->guardedTransitions($table, $column) as [$from, $to]) {
            if ((string) $original[$column] === (string) $from && (string) $new[$column] === (string) $to) {
                return true;
            }
        }

        return false;
    }

    /** cloud for a table only the office sends, vessel for one only vessels send, null for both. */
    private function wholeTableOwner(string $table): ?string
    {
        return match ($this->rules->rule($table)['direction'] ?? null) {
            ReplicationRules::DOWN => ReplicationRules::CLOUD,
            ReplicationRules::UP => ReplicationRules::VESSEL,
            default => null,
        };
    }

    private function otherSide(string $side): string
    {
        return $side === ReplicationRules::CLOUD ? ReplicationRules::VESSEL : ReplicationRules::CLOUD;
    }

    private function message(string $table, string $what, string $side): string
    {
        $label = str_replace('_', ' ', $table);

        return $side === ReplicationRules::CLOUD
            ? "This is the vessel's own information ({$label}: {$what}). The vessel uses its own onboard server, so it can only be changed there. Nothing was saved."
            : "This information is maintained by the office ({$label}: {$what}) and cannot be changed on board. Changes made in the office reach the vessel by synchronisation. Nothing was saved.";
    }
}
