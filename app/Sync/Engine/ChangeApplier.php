<?php

namespace App\Sync\Engine;

use App\Sync\Models\SyncConflict;
use App\Sync\SyncContext;
use App\Sync\SyncRegistry;
use Illuminate\Support\Facades\DB;

/**
 * Applies one received change to the application tables.
 *
 * The caller runs this inside a savepoint, so anything it throws leaves no
 * trace. The write happens with the sender as origin: the capture triggers
 * record it as coming from the sender, so it is never sent back there, and
 * the row keeps the version the sender gave it.
 *
 * Conflict: the receiver has its own change to the row that the sender had
 * not seen when it sent this one (ReceivingSide::pendingLocalChange). The
 * table's rule picks the winner; every conflict is recorded either way.
 *
 * Business records (Checkpoint 6, config/sync.php) add:
 *  - row rules: which side may create or delete rows, which side owns a
 *    given row (form Part A is the ship's), and permanent records (ledger
 *    and audit rows) that are never changed or removed once written;
 *  - column owners: a column the receiver owns is never overwritten by the
 *    sender, except for a listed transition (the office approving a PMS
 *    job). Shared columns are merged one by one against the values both
 *    sides last agreed on (RowBases): only a column changed on both sides
 *    is a conflict, decided by the table's rule and, where the rule says,
 *    flagged for a person;
 *  - duplicates merged into one record ('on_duplicate' => 'merge'), the
 *    second UUID kept as an alias;
 *  - hooks with module logic (the stock ledger).
 *
 * Deletion safety (Checkpoint 14, ADR-003 rule 5, ADR-006, PD-68 option 1):
 *  - a deleted row is never brought back: a change arriving for a row
 *    deleted here (sync_deletions) is kept in a conflict for review;
 *  - a delete always stands against a change made here meanwhile, which is
 *    kept in the conflict record for review;
 *  - a vessel's delete is accepted only for the draft edits and replaced
 *    child rows in sync.vessel_deletes; a vessel's change that retires a
 *    record (sync.retire_columns) or changes an office-only column
 *    (sync.office_only_columns, PD-73) is not taken; both are flagged;
 *  - a record the office retired stays retired when it meets a change the
 *    vessel made offline; the vessel's other values follow the table's rule;
 *  - a vessel never applies a delete from the cloud of a record it owns,
 *    nor one that would cascade into rows it holds (PD-60).
 * A refused change is logged with the pull and flagged for review by the
 * caller (InboxProcessor).
 * Each change is written as made by the user who made it on the sender.
 */
class ChangeApplier
{
    public const APPLIED = 'applied';          // written
    public const UNCHANGED = 'unchanged';      // already exactly like this here
    public const TOOK_REMOTE = 'took_remote';  // conflict: written, the local change lost
    public const KEPT_LOCAL = 'kept_local';    // conflict: not written, the local change won
    public const MERGED = 'merged';            // conflict: some columns from each side
    public const SKIPPED = 'skipped';          // nothing to do (deleting a row that is not here)

    public function __construct(
        private SyncRegistry $registry,
        private ReplicationRules $rules,
        private RowCodec $codec,
        private RowBases $bases,
        private SyncAliases $aliases,
    ) {
    }

    /**
     * @param  array{event_id: string, table: string, row_uuid: string, op: string, version: ?int, vessel_id: ?int, values: ?array}  $event
     * @return array{status: string, note: ?string, conflict: ?int}
     *
     * @throws MissingReference the change must wait for a row it refers to
     * @throws InvalidChange the change can never be applied as sent
     */
    public function apply(array $event, string $source, ReceivingSide $side): array
    {
        return SyncContext::asActor($event['actor'] ?? null, fn () => $this->applyAsActor($event, $source, $side));
    }

    private function applyAsActor(array $event, string $source, ReceivingSide $side): array
    {
        $table = $event['table'];
        if ($this->rules->rule($table) === null || ! $side->accepts($table)) {
            throw new InvalidChange("Changes to {$table} are not accepted here.");
        }

        $uuidColumn = $this->registry->uuidColumn($table);
        $local = DB::table($table)->where($uuidColumn, $event['row_uuid'])->first();
        if ($local === null && ($target = $this->aliases->target($table, $event['row_uuid'])) !== null) {
            $local = DB::table($table)->where($uuidColumn, $target)->first();
        }

        $this->assertSenderOwnsRow($table, $event, $local, $side);

        return $event['op'] === 'delete'
            ? $this->delete($event, $source, $side, $local)
            : $this->upsert($event, $source, $side, $local);
    }

    private function delete(array $event, string $source, ReceivingSide $side, ?object $local): array
    {
        $table = $event['table'];
        $sender = $this->sender($side);

        if ($local === null) {
            return $this->result(self::SKIPPED, 'Already absent.');
        }

        if ($sender === ReplicationRules::VESSEL && ! $this->rules->vesselMayDelete($table)) {
            throw new InvalidChange("ADR-006: records are not deleted on a vessel, so a delete of a {$table} row from the vessel is not accepted.");
        }

        if ($this->rules->isAppendOnly($table)) {
            throw new InvalidChange("{$table} rows are permanent records: a delete from the {$sender} is not accepted.");
        }

        // A vessel is the authority for the records it owns (ADR-001
        // principle 1), so a delete of one that comes from the cloud is not
        // applied on board: it is refused, logged with the pull and flagged
        // for review (owner's instruction, 2026-11-23; PD-34, vessel side).
        if ($side->name() === ReplicationRules::VESSEL && ($why = $this->vesselOwns($table, $event, $local)) !== null) {
            throw new InvalidChange("This {$table} row is the vessel's own record ({$why}), so a delete from the cloud is not applied here.");
        }

        // PD-60 (approved 2026-11-23): a vessel never applies a delete of its
        // own vessel row, nor one that would cascade into the rows it holds
        // (the database would remove them without recording or sending it).
        if ($side->name() === ReplicationRules::VESSEL) {
            if ($table === 'vessels' && (int) $local->id === (int) config('navisuite.vessel_id')) {
                throw new InvalidChange('PD-60: this vessel does not delete its own vessel record.');
            }
            if (($children = $this->cascadingChildren($table, $local->id)) !== []) {
                throw new InvalidChange("PD-60: deleting this {$table} row would also delete rows held here (".implode(', ', $children).'), so it is not applied.');
            }
        }
        if (($deleter = $this->rules->deleter($table)) !== null && $deleter !== $sender) {
            throw new InvalidChange("Only the {$deleter} deletes {$table} rows.");
        }
        if ($table === 'users' && ($why = \App\Support\UserRecordGuard::refusal((int) $local->id)) !== null) {
            throw new InvalidChange($why);
        }

        $localUuid = $this->uuidOf($table, $local);
        $pending = $side->pendingLocalChange($table, $localUuid);

        // PD-68 option 1: the deletion stands. Keeping the row here instead
        // would leave it on one side only for good, because the sender
        // never takes it back (no resurrection). The change made here is
        // kept in the conflict record for a person.
        $conflict = null;
        if ($pending !== null) {
            $conflict = $this->recordConflict(SyncConflict::KIND_DELETE_UPDATE, $event, $source, $side, SyncConflict::TOOK_REMOTE, $local, null, true,
                "Deleted by the {$sender} while changed here: the deletion stands. The change made here is kept in this record.");
        }

        $side->checkScope($table, $event, $local->id, null);

        SyncContext::asOrigin($source, fn () => DB::table($table)->where('id', $local->id)->delete());
        if ($this->rules->mergesColumns($table)) {
            $this->bases->forget($table, $localUuid);
        }
        $this->runHooksAfter($table, $event, $source, $local, null, $side);

        return $this->result($conflict ? self::TOOK_REMOTE : self::APPLIED, null, $conflict);
    }

    private function upsert(array $event, string $source, ReceivingSide $side, ?object $local): array
    {
        $table = $event['table'];
        if (! is_array($event['values'])) {
            throw new InvalidChange('An insert or update needs values.');
        }

        $decoded = $this->codec->decode($table, $event['values']);
        if (($owner = $this->rules->parentOwner($table, $decoded['values'])) !== null && $owner !== $this->sender($side)) {
            throw new InvalidChange("This {$table} row belongs to a record kept by the {$owner}; a change from the {$this->sender($side)} is not accepted.");
        }
        $values = $this->runHooksBefore($table, $decoded['values'], $local, $side);
        $note = $decoded['ignored'] === [] ? null : 'Columns not on this server, ignored: '.implode(', ', $decoded['ignored']).'.';
        $remoteUpdatedAt = isset($event['values']['updated_at']) ? (string) $event['values']['updated_at'] : null;
        $version = max(1, (int) ($event['version'] ?? 1));
        $sender = $this->sender($side);
        $conflict = null;

        if ($local === null) {
            // No resurrection: a row deleted here stays deleted, however
            // long ago, and the change is kept for a person (PD-68 option 1).
            $pending = $side->pendingLocalChange($table, $event['row_uuid']);
            if (($pending !== null && $pending->operation === 'delete') || $this->deletedHere($table, $event['row_uuid'])) {
                $conflict = $this->recordConflict(SyncConflict::KIND_UPDATE_DELETE, $event, $source, $side, SyncConflict::KEPT_LOCAL, null, $event['values'], true,
                    "Changed by the {$sender} after it was deleted here: a deleted record is not brought back. The change is kept in this record.");

                return $this->result(self::KEPT_LOCAL, 'Deleted here: the record is not brought back.', $conflict);
            }

            $existing = $this->naturalKeyMatch($table, $values);
            if ($existing === null) {
                if (($creator = $this->rules->creator($table)) !== null && $creator !== $sender) {
                    throw new InvalidChange("Only the {$creator} creates {$table} rows.");
                }

                return $this->insert($event, $source, $side, $values, $version, $note, $conflict);
            }

            if ($this->rules->onDuplicate($table) !== ReplicationRules::MERGE) {
                // The caller records it, outside the savepoint it rolls back.
                throw new DuplicateNaturalKey($table, $this->rules->naturalKey($table), $existing, $event, $source, $side->name());
            }

            // The same record, created on both sides: one record from now
            // on. The sender's UUID becomes an alias of the one here.
            $this->aliases->add($table, $event['row_uuid'], $this->uuidOf($table, $existing), $source);
            $conflict = $this->recordConflict(SyncConflict::KIND_DUPLICATE_KEY, $event, $source, $side, SyncConflict::MERGED, $existing, $event['values'], false,
                'Created on both sides; merged into the record here ('.implode(', ', $this->rules->naturalKey($table)).').');
            $note = trim(($note ?? '').' Merged with the same record created here.');
            $local = $existing;
            $values = $this->runHooksBefore($table, $decoded['values'], $local, $side);

            // A permanent record listed on both sides (a certificate copy
            // from before the history began): one record, kept exactly as
            // it is here, since a permanent record is never changed.
            if ($this->rules->isAppendOnly($table)) {
                return $this->result(self::MERGED, $note, $conflict);
            }
        }

        $localUuid = $this->uuidOf($table, $local);
        $values = $this->withoutReceiverColumns($table, $values, $local, $side);

        // ADR-006: a vessel does not retire records. PD-73: nor does it
        // change an office-only column (the component "Inactive" switch),
        // either way. Those values are not taken; the rest of the change
        // is, and the refusal is flagged.
        $refused = false;
        $retiring = $sender === ReplicationRules::VESSEL ? $this->retiring($table, $local, $values) : [];
        $officeOnly = $sender === ReplicationRules::VESSEL ? array_diff_key($this->officeOnlyChanges($table, $local, $values), $retiring) : [];
        if ($retiring !== [] || $officeOnly !== []) {
            $values = array_diff_key($values, $retiring, $officeOnly);
            $reasons = [];
            $notes = [];
            if ($retiring !== []) {
                $reasons[] = 'ADR-006: records are not retired on a vessel; not taken: '.implode(', ', array_keys($retiring)).'.';
                $notes[] = 'Not taken (ADR-006): '.implode(', ', array_keys($retiring)).'.';
            }
            if ($officeOnly !== []) {
                $reasons[] = 'PD-73: only the office changes this on a vessel; not taken: '.implode(', ', array_keys($officeOnly)).'.';
                $notes[] = 'Not taken (PD-73): '.implode(', ', array_keys($officeOnly)).'.';
            }
            $conflict = $this->recordConflict(SyncConflict::KIND_REFUSED, $event, $source, $side, SyncConflict::KEPT_LOCAL, $local, $event['values'], true,
                implode(' ', $reasons));
            $note = trim(($note ?? '').' '.implode(' ', $notes));
            $refused = true;
        }

        if ($this->codec->same($local, $values)) {
            $this->rememberBase($source, $table, $localUuid, $event['values']);

            return $this->result($refused ? self::KEPT_LOCAL : self::UNCHANGED, $note, $conflict);
        }

        if ($this->rules->isAppendOnly($table)) {
            throw new InvalidChange("{$table} rows are permanent records: a change from the {$sender} is not accepted.");
        }

        $status = $refused ? self::MERGED : self::APPLIED;
        $pending = $side->pendingLocalChange($table, $localUuid);
        if ($pending !== null && $this->rules->mergesColumns($table)) {
            $merge = $this->mergeColumns($event, $source, $side, $local, $values);
            $values = $merge['values'];
            $conflict = $merge['conflict'] ?? $conflict;
            $status = $merge['status'];
            if ($values === [] || $this->codec->same($local, $values)) {
                $this->rememberBase($source, $table, $localUuid, $event['values']);

                return $this->result($status === self::APPLIED ? self::UNCHANGED : $status, $note, $conflict);
            }
        } elseif ($pending !== null && ($retired = $this->officeRetirement($table, $local, $values, $side)) !== []) {
            // PD-68 option 1: the office's retirement stands; the vessel's
            // other values follow the table's rule.
            $keep = $this->rules->keepsLocal($table, $side->name(), $local->updated_at ?? null, $remoteUpdatedAt, false, false);
            $values = $keep ? array_intersect_key($values, $retired) : $values;
            $values = array_replace($values, $retired);
            $conflict = $this->recordConflict(SyncConflict::KIND_UPDATE_UPDATE, $event, $source, $side, SyncConflict::MERGED, $local, $event['values'], true,
                'Retired by the office while changed on the vessel: the record stays retired ('.implode(', ', array_keys($retired)).'). '
                .($keep ? 'The other values of this server are kept.' : 'The other values come from the '.$sender.'.'));
            if ($this->codec->same($local, $values)) {
                $this->rememberBase($source, $table, $localUuid, $event['values']);

                return $this->result(self::KEPT_LOCAL, 'Changed on both sides: the record stays retired.', $conflict);
            }
            $status = self::MERGED;
        } elseif ($pending !== null) {
            $keep = $this->rules->keepsLocal($table, $side->name(), $local->updated_at ?? null, $remoteUpdatedAt, false, false);
            $conflict = $this->recordConflict(SyncConflict::KIND_UPDATE_UPDATE, $event, $source, $side, $keep ? SyncConflict::KEPT_LOCAL : SyncConflict::TOOK_REMOTE, $local, $event['values'], $this->rules->needsReview($table));
            if ($keep) {
                return $this->result(self::KEPT_LOCAL, 'Changed on both sides: the change here is kept.', $conflict);
            }
            $status = self::TOOK_REMOTE;
        }

        $side->checkScope($table, $event, $local->id, null);

        $values['sync_version'] = max((int) $local->sync_version, $version);
        SyncContext::asOrigin($source, fn () => DB::table($table)->where('id', $local->id)->update($values));
        $side->checkScope($table, $event, null, $local->id);

        $this->rememberBase($source, $table, $localUuid, $event['values']);
        $this->runHooksAfter($table, $event, $source, $local, DB::table($table)->where('id', $local->id)->first(), $side);

        return $this->result($status, $note, $conflict);
    }

    private function insert(array $event, string $source, ReceivingSide $side, array $values, int $version, ?string $note, ?int $conflict): array
    {
        $table = $event['table'];
        $uuidColumn = $this->registry->uuidColumn($table);

        $row = $values + [$uuidColumn => $event['row_uuid'], 'sync_version' => $version];
        if ($uuidColumn === 'id') {
            $row['id'] = $event['row_uuid'];
        }

        SyncContext::asOrigin($source, fn () => DB::table($table)->insert($row));
        $after = DB::table($table)->where($uuidColumn, $event['row_uuid'])->first();
        $side->checkScope($table, $event, null, $after->id);

        $this->rememberBase($source, $table, $event['row_uuid'], $event['values']);
        $this->runHooksAfter($table, $event, $source, null, $after, $side);

        return $this->result($conflict ? self::TOOK_REMOTE : self::APPLIED, $note, $conflict);
    }

    /**
     * Both sides changed this row. Columns each side owns are already
     * settled; this goes through the shared ones.
     *
     * @return array{values: array, status: string, conflict: ?int}
     */
    private function mergeColumns(array $event, string $source, ReceivingSide $side, object $local, array $values): array
    {
        $table = $event['table'];
        $remote = $event['values'];
        $mine = $this->codec->snapshot($table, $local) ?? [];
        $base = $this->bases->get($source, $table, $this->uuidOf($table, $local));
        $keepLocal = $this->rules->keepsLocal($table, $side->name(), $local->updated_at ?? null, isset($remote['updated_at']) ? (string) $remote['updated_at'] : null, false, false);

        // Per shared column: 'remote' (only the sender changed it), 'local'
        // (only changed here) or 'both'.
        $decision = [];
        foreach (array_keys($values) as $column) {
            if ($column === 'updated_at' || $this->rules->ownerOf($table, $column) !== ReplicationRules::SHARED) {
                continue;
            }
            $l = $this->normalise($mine[$column] ?? null);
            $r = $this->normalise($remote[$column] ?? null);
            if ($l === $r) {
                continue;
            }
            $decision[$column] = 'both'; // changed on both sides, or no way to tell
            if ($base !== null && array_key_exists($column, $base)) {
                $b = $this->normalise($base[$column]);
                $decision[$column] = $l === $b ? 'remote' : ($r === $b ? 'local' : 'both');
            }
        }

        // A conflict on one column of a group decides the whole group: a
        // status never ends up next to another side's rejection details.
        foreach ($this->rules->columnGroups($table) as $group) {
            if (in_array('both', array_intersect_key($decision, array_flip($group)), true)) {
                foreach ($group as $column) {
                    if (isset($decision[$column])) {
                        $decision[$column] = 'both';
                    }
                }
            }
        }

        $both = array_keys(array_filter($decision, fn ($d) => $d === 'both'));
        foreach ($decision as $column => $d) {
            if ($d === 'local') {
                unset($values[$column]);
            }
        }

        if ($both === []) {
            $values = $this->laterUpdatedAt($local, $values);

            return ['values' => $values, 'status' => self::APPLIED, 'conflict' => null];
        }

        if ($keepLocal) {
            foreach ($both as $column) {
                unset($values[$column]);
            }
        }
        $values = $this->laterUpdatedAt($local, $values);

        $remoteOnly = array_diff(array_keys($values), ['updated_at', ...$both]);
        $resolution = $keepLocal
            ? ($remoteOnly === [] ? SyncConflict::KEPT_LOCAL : SyncConflict::MERGED)
            : SyncConflict::TOOK_REMOTE;
        $review = array_filter($both, fn ($c) => $this->rules->needsReview($table, $c)) !== [];

        $winner = $keepLocal ? 'this server' : "the {$this->sender($side)}";
        $conflict = $this->recordConflict(SyncConflict::KIND_UPDATE_UPDATE, $event, $source, $side, $resolution, $local, $remote, $review,
            'Changed on both sides: '.implode(', ', $both).". Kept the values of {$winner}".($review ? '; waiting for a person to confirm.' : '.'));

        return [
            'values' => $values,
            'status' => $keepLocal ? ($resolution === SyncConflict::KEPT_LOCAL ? self::KEPT_LOCAL : self::MERGED) : self::TOOK_REMOTE,
            'conflict' => $conflict,
        ];
    }

    /** The receiver's own columns are never taken from the sender, except a listed transition. */
    private function withoutReceiverColumns(string $table, array $values, object $local, ReceivingSide $side): array
    {
        if (! $this->rules->mergesColumns($table)) {
            return $values;
        }

        foreach ($values as $column => $value) {
            if ($column === 'updated_at' || $this->rules->ownerOf($table, $column) !== $side->name()) {
                continue;
            }
            $from = $this->normalise($local->{$column} ?? null);
            $to = $this->normalise($value);
            if ($from === $to) {
                continue;
            }
            $allowed = array_filter($this->rules->guardedTransitions($table, $column), fn ($pair) => $this->normalise($pair[0]) === $from && $this->normalise($pair[1]) === $to);
            if ($allowed === []) {
                unset($values[$column]);
            }
        }

        return $values;
    }

    /** @var array<string, array<string, list<array{0: string, 1: string}>>> connection => table => cascading [child table, column] */
    private array $cascades = [];

    /**
     * The tables holding rows that a delete of $table row $id would take
     * with it through an ON DELETE CASCADE key.
     *
     * @return list<string>
     */
    private function cascadingChildren(string $table, int|string $id): array
    {
        $connection = DB::connection()->getName();
        if (! isset($this->cascades[$connection])) {
            $map = [];
            foreach (\Illuminate\Support\Facades\Schema::getTables() as $t) {
                foreach (\Illuminate\Support\Facades\Schema::getForeignKeys($t['name']) as $key) {
                    if (strtolower((string) $key['on_delete']) === 'cascade' && count($key['columns']) === 1) {
                        $map[$key['foreign_table']][] = [$t['name'], $key['columns'][0]];
                    }
                }
            }
            $this->cascades[$connection] = $map;
        }

        $children = [];
        foreach ($this->cascades[$connection][$table] ?? [] as [$child, $column]) {
            if (DB::table($child)->where($column, $id)->exists()) {
                $children[] = $child;
            }
        }

        return array_values(array_unique($children));
    }

    /** A row of $table deleted on this server at any time (the trigger keeps the record). */
    private function deletedHere(string $table, string $uuid): bool
    {
        return DB::table('sync_deletions')->where('table_name', $table)->where('row_uuid', strtolower($uuid))->exists();
    }

    /** Whether $value is the retired state of a retire column. */
    private function isRetiredValue(mixed $value, mixed $retired): bool
    {
        return $retired === 'not_null' ? $value !== null && $value !== '' : $this->normalise($value) === $this->normalise($retired);
    }

    /**
     * The sent values that would retire $local (an active record).
     *
     * @return array<string, mixed>
     */
    private function retiring(string $table, object $local, array $values): array
    {
        $retiring = [];
        foreach ($this->rules->retireColumns($table) as $column => $retired) {
            if (array_key_exists($column, $values) && ! $this->isRetiredValue($local->{$column} ?? null, $retired)
                && $this->isRetiredValue($values[$column], $retired)) {
                $retiring[$column] = $values[$column];
            }
        }

        return $retiring;
    }

    /**
     * The sent values that would change an office-only column of $local
     * (PD-73), in either direction.
     *
     * @return array<string, mixed>
     */
    private function officeOnlyChanges(string $table, object $local, array $values): array
    {
        $changes = [];
        foreach ($this->rules->officeOnlyColumns($table) as $column) {
            if (array_key_exists($column, $values) && $this->normalise($values[$column]) !== $this->normalise($local->{$column} ?? null)) {
                $changes[$column] = $values[$column];
            }
        }

        return $changes;
    }

    /**
     * Where the office retired this record and the vessel changed it
     * meanwhile: the retired values that must stand. On the vessel, the
     * office's retiring values as sent; on the cloud, its own retired values
     * the vessel's (older) values would undo.
     *
     * @return array<string, mixed>
     */
    private function officeRetirement(string $table, object $local, array $values, ReceivingSide $side): array
    {
        $stand = [];
        foreach ($this->rules->retireColumns($table) as $column => $retired) {
            if (! array_key_exists($column, $values)) {
                continue;
            }
            $localRetired = $this->isRetiredValue($local->{$column} ?? null, $retired);
            $remoteRetired = $this->isRetiredValue($values[$column], $retired);
            if ($side->name() === ReplicationRules::VESSEL && $remoteRetired && ! $localRetired) {
                $stand[$column] = $values[$column];
            } elseif ($side->name() === ReplicationRules::CLOUD && $localRetired && ! $remoteRetired) {
                $stand[$column] = $local->{$column};
            }
        }

        return $stand;
    }

    private function laterUpdatedAt(object $local, array $values): array
    {
        if (isset($values['updated_at'], $local->updated_at) && strcmp((string) $local->updated_at, (string) $values['updated_at']) > 0) {
            unset($values['updated_at']);
        }

        return $values;
    }

    /** A table holding rows of both sides: the sender may only touch its own. */
    /**
     * Why a row is the vessel's own record, or null when it is not:
     *  - the table holds vessel-owned operational records (class C: stock,
     *    consumption, PMS, equipment, the vessel's item links);
     *  - only the vessel creates rows of the table (requisitions, their
     *    lines and approvals, PMS jobs);
     *  - the table keeps rows of both sides and this one is the vessel's
     *    (requisition form Part A, Receipt & Verification);
     *  - a file record uploaded by one of the vessel's crew (class F, its
     *    vessel being the uploader's).
     * Records both sides edit (certificates and surveys, PD-17) and shared
     * master data are not vessel-owned.
     */
    private function vesselOwns(string $table, array $event, object $local): ?string
    {
        // The same rule the cloud uses to refuse an office delete (PD-34).
        $vessel = $event['vessel_id'] ?? null;

        return app(\App\Sync\VesselOwnership::class)->reason($table, (array) $local, $vessel === null ? null : (int) $vessel);
    }

    private function assertSenderOwnsRow(string $table, array $event, ?object $local, ReceivingSide $side): void
    {
        $sender = $this->sender($side);
        foreach ([$local === null ? null : (array) $local, $event['values']] as $i => $values) {
            if ($values === null) {
                continue;
            }
            // The stored row holds local ids, so its parent can be looked up
            // (owned_with); the sent values are checked once decoded (upsert).
            $owner = $i === 0 ? $this->rules->ownerOfRow($table, $values) : $this->rules->rowOwner($table, $values);
            if ($owner !== null && $owner !== $sender) {
                throw new InvalidChange("This {$table} row is kept by the {$owner}; a change from the {$sender} is not accepted.");
            }
        }
    }

    private function naturalKeyMatch(string $table, array $values): ?object
    {
        $key = $this->rules->naturalKey($table);
        if ($key === [] || array_diff($key, array_keys($values)) !== []) {
            return null;
        }

        if (array_filter($key, fn ($column) => $values[$column] !== null) === []) {
            return null; // an empty key (an item without an article number) identifies nothing
        }

        $query = DB::table($table);
        foreach ($key as $column) {
            $values[$column] === null ? $query->whereNull($column) : $query->where($column, $values[$column]);
        }

        return $query->first();
    }

    private function runHooksBefore(string $table, array $values, ?object $local, ReceivingSide $side): array
    {
        foreach ($this->rules->hooks($table) as $hook) {
            $values = app($hook)->prepare($table, $values, $local, $side);
        }

        return $values;
    }

    private function runHooksAfter(string $table, array $event, string $source, ?object $before, ?object $after, ReceivingSide $side): void
    {
        foreach ($this->rules->hooks($table) as $hook) {
            app($hook)->applied($table, $event, $source, $before, $after, $side);
        }
    }

    private function rememberBase(string $source, string $table, string $uuid, array $remote): void
    {
        if ($this->rules->mergesColumns($table)) {
            $this->bases->remember($source, $table, $uuid, $remote);
        }
    }

    private function recordConflict(string $kind, array $event, string $source, ReceivingSide $side, string $resolution, ?object $local, ?array $remoteValues, bool $review = false, ?string $note = null): int
    {
        return SyncConflict::create([
            'event_id' => $event['event_id'],
            'source_node' => $source,
            'detected_by' => $side->name(),
            'table_name' => $event['table'],
            'row_uuid' => $event['row_uuid'],
            'kind' => $kind,
            'policy' => $this->rules->rule($event['table'])['conflict'] ?? null,
            'resolution' => $resolution,
            'local_version' => $local?->sync_version,
            'remote_version' => $event['version'] ?? null,
            'local_values' => $this->codec->snapshot($event['table'], $local),
            'remote_values' => $remoteValues,
            'status' => $review ? SyncConflict::STATUS_NEEDS_REVIEW : SyncConflict::STATUS_RESOLVED,
            'note' => $note,
            'created_at' => now()->format('Y-m-d H:i:s.u'),
        ])->id;
    }

    private function sender(ReceivingSide $side): string
    {
        return $side->name() === ReplicationRules::CLOUD ? ReplicationRules::VESSEL : ReplicationRules::CLOUD;
    }

    private function uuidOf(string $table, object $row): string
    {
        return strtolower((string) $row->{$this->registry->uuidColumn($table)});
    }

    private function normalise(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            is_bool($value) => $value ? '1' : '0',
            is_float($value) && floor($value) === $value => (string) (int) $value,
            default => (string) $value,
        };
    }

    private function result(string $status, ?string $note = null, ?int $conflict = null): array
    {
        return ['status' => $status, 'note' => $note, 'conflict' => $conflict];
    }
}
