<?php

namespace App\Sync\Engine;

use App\Sync\SyncRegistry;
use Illuminate\Support\Facades\DB;

/**
 * Which tables travel between servers, in which direction, and who wins a
 * conflict: config/sync.php 'replication' and 'enabled_tables'.
 *
 * A table is synchronised only when it has a rule AND is enabled. Changes to
 * every other table are still captured (Checkpoint 2) but stay where they
 * were made; see `sync:resend` for enabling a table later.
 */
class ReplicationRules
{
    public const DOWN = 'down';

    public const UP = 'up';

    public const BOTH = 'both';

    public const CLOUD = 'cloud';

    public const VESSEL = 'vessel';

    public const LATEST = 'latest';

    /** Never sent: local identity and the sync columns, which travel separately. */
    public const SYSTEM_COLUMNS = ['id', SyncRegistry::UUID_COLUMN, SyncRegistry::VERSION_COLUMN];

    public function __construct(private SyncRegistry $registry)
    {
    }

    public const SHARED = 'shared';

    public const REVIEW = 'review';

    public const MERGE = 'merge';

    /** @return array<string, array> every rule, with every key filled in */
    public function defined(): array
    {
        $rules = [];

        foreach ((array) config('sync.replication', []) as $table => $rule) {
            $direction = $rule['direction'] ?? '';
            $columns = isset($rule['columns']) ? (array) $rule['columns'] : null;
            $rules[$table] = [
                'direction' => $direction,
                'conflict' => match ($direction) {
                    self::DOWN => self::CLOUD,
                    self::UP => self::VESSEL,
                    default => $rule['conflict'] ?? '',
                },
                'references' => (array) ($rule['references'] ?? []),
                'natural_key' => array_values((array) ($rule['natural_key'] ?? [])),
                'local_columns' => array_values((array) ($rule['local_columns'] ?? [])),
                // Checkpoint 6: business records.
                'columns' => $columns === null ? null : [
                    self::CLOUD => array_values((array) ($columns[self::CLOUD] ?? [])),
                    self::VESSEL => array_values((array) ($columns[self::VESSEL] ?? [])),
                    self::SHARED => array_values((array) ($columns[self::SHARED] ?? [])),
                    'other' => $columns['other'] ?? self::SHARED,
                ],
                'review' => $rule['review'] ?? false,
                'together' => array_values(array_map('array_values', (array) ($rule['together'] ?? []))),
                'guarded' => (array) ($rule['guarded'] ?? []),
                'append_only' => (bool) ($rule['append_only'] ?? false),
                'creates' => $rule['creates'] ?? null,
                'deletes' => $rule['deletes'] ?? null,
                'row_owner' => $rule['row_owner'] ?? null,
                'owned_with' => $rule['owned_with'] ?? null,
                'on_duplicate' => $rule['on_duplicate'] ?? self::REVIEW,
                'hooks' => array_values((array) ($rule['hooks'] ?? [])),
                'redact' => (array) ($rule['redact'] ?? []),
                // Checkpoint 7: attachments.
                'strict_scope' => (bool) ($rule['strict_scope'] ?? false),
                'carries' => (array) ($rule['carries'] ?? []),
                'departures' => (bool) ($rule['departures'] ?? false),
            ];
        }

        return $rules;
    }

    /** @return list<string> enabled tables that have a rule, in registry order (parents first where it matters) */
    public function enabled(): array
    {
        $listed = $this->listed();

        return array_values(array_filter(array_keys($this->defined()), fn ($t) => in_array($t, $listed, true)));
    }

    /**
     * SYNC_TABLES as a list of tables: "@name" stands for the tables of
     * module "name" (config/sync.php 'modules').
     *
     * @return list<string>
     */
    public function listed(): array
    {
        $listed = [];
        foreach (array_filter(array_map('trim', explode(',', (string) config('sync.enabled_tables', '')))) as $entry) {
            if (str_starts_with($entry, '@')) {
                array_push($listed, ...array_values((array) config('sync.modules.'.substr($entry, 1), [])));
            } else {
                $listed[] = $entry;
            }
        }

        return array_values(array_unique($listed));
    }

    public function isEnabled(string $table): bool
    {
        return in_array($table, $this->enabled(), true);
    }

    public function rule(string $table): ?array
    {
        return $this->defined()[$table] ?? null;
    }

    /** Enabled tables the cloud sends to vessels. @return list<string> */
    public function downTables(): array
    {
        return array_values(array_filter($this->enabled(), fn ($t) => $this->rule($t)['direction'] !== self::UP));
    }

    /** Enabled tables vessels send to the cloud. @return list<string> */
    public function upTables(): array
    {
        return array_values(array_filter($this->enabled(), fn ($t) => $this->rule($t)['direction'] !== self::DOWN));
    }

    /** @return array<string, string> column => referenced table */
    public function references(string $table): array
    {
        return $this->rule($table)['references'] ?? [];
    }

    /** @return list<string> */
    public function naturalKey(string $table): array
    {
        return $this->rule($table)['natural_key'] ?? [];
    }

    /** @return list<string> columns that never travel for this table */
    public function unsentColumns(string $table): array
    {
        return array_values(array_unique([...self::SYSTEM_COLUMNS, ...($this->rule($table)['local_columns'] ?? [])]));
    }

    /**
     * Whether this table is merged column by column (it has a 'columns'
     * rule): each column has an owner, and only columns changed on both
     * sides count as a conflict.
     */
    public function mergesColumns(string $table): bool
    {
        return ($this->rule($table)['columns'] ?? null) !== null;
    }

    /** cloud, vessel or shared: who may change this column of a column-merged table. */
    public function ownerOf(string $table, string $column): string
    {
        $columns = $this->rule($table)['columns'] ?? null;
        if ($columns === null) {
            return self::SHARED;
        }
        foreach ([self::CLOUD, self::VESSEL, self::SHARED] as $owner) {
            if (in_array($column, $columns[$owner], true)) {
                return $owner;
            }
        }

        return $columns['other'];
    }

    /**
     * Pairs [from, to] in which the side that does not own $column may still
     * change it, e.g. the office approving a PMS job (WaitingApproval to
     * Completed) on a job the vessel otherwise keeps.
     *
     * @return list<array{0: string, 1: string}>
     */
    public function guardedTransitions(string $table, string $column): array
    {
        return array_values((array) ($this->rule($table)['guarded'][$column] ?? []));
    }

    /**
     * Groups of shared columns that only make sense together (a status and
     * the rejection details): a conflict on one decides the whole group.
     *
     * @return list<list<string>>
     */
    public function columnGroups(string $table): array
    {
        return $this->rule($table)['together'] ?? [];
    }

    /** Whether a conflict on this table (or on this column of it) waits for a person. */
    public function needsReview(string $table, ?string $column = null): bool
    {
        $review = $this->rule($table)['review'] ?? false;

        return is_array($review) ? $column !== null && in_array($column, $review, true) : (bool) $review;
    }

    /** A permanent record (a ledger line, an audit entry): created once, never changed or removed by sync. */
    public function isAppendOnly(string $table): bool
    {
        return (bool) ($this->rule($table)['append_only'] ?? false);
    }

    /** cloud, vessel, or null for both: which side may create rows of this table. */
    public function creator(string $table): ?string
    {
        return $this->rule($table)['creates'] ?? null;
    }

    /** cloud, vessel, or null for both: which side may delete rows of this table. */
    public function deleter(string $table): ?string
    {
        return $this->rule($table)['deletes'] ?? null;
    }

    /** ADR-006: the only tables a vessel's delete is accepted for (config sync.vessel_deletes). */
    public function vesselMayDelete(string $table): bool
    {
        return in_array($table, (array) config('sync.vessel_deletes', []), true);
    }

    /**
     * The columns that retire a row of $table, with their retired value
     * ('not_null' = any value). Empty when the table has none.
     *
     * @return array<string, mixed>
     */
    public function retireColumns(string $table): array
    {
        return (array) (config('sync.retire_columns', [])[$table] ?? []);
    }

    /**
     * The columns of $table only the office changes (PD-73): a vessel's
     * change to one of them is refused and flagged. Empty when none.
     *
     * @return list<string>
     */
    public function officeOnlyColumns(string $table): array
    {
        return array_values((array) (config('sync.office_only_columns', [])[$table] ?? []));
    }

    /**
     * Who owns one row, when a table holds rows of both sides (requisition
     * form Part A is the ship's, Parts B and C the office's). Null when the
     * table has no such rule.
     *
     * @param  array<string, mixed>  $values  the row's values (sent or local)
     */
    public function rowOwner(string $table, array $values): ?string
    {
        $rule = $this->rule($table)['row_owner'] ?? null;
        if ($rule === null) {
            return null;
        }
        $value = $values[$rule['column']] ?? null;

        return $rule['values'][(string) $value] ?? ($rule['other'] ?? null);
    }

    /**
     * Who owns one row whose owner is that of another row (owned_with): a
     * document link belongs to the side that owns its procurement stage
     * (PD-22, PD-59 B). Looked up by the parent's local id, so $values must
     * hold local ids. Null when the table has no such rule or the parent is
     * not here.
     *
     * @param  array<string, mixed>  $values
     */
    public function parentOwner(string $table, array $values): ?string
    {
        $rule = $this->rule($table)['owned_with'] ?? null;
        $id = $rule === null ? null : ($values[$rule['column']] ?? null);
        if ($id === null) {
            return null;
        }
        $parent = DB::table($rule['table'])->where('id', $id)->first();

        return $parent === null ? null : $this->rowOwner($rule['table'], (array) $parent);
    }

    /** The owner of one stored row: by its own column (row_owner) or its parent's (owned_with). */
    public function ownerOfRow(string $table, array $values): ?string
    {
        return $this->rowOwner($table, $values) ?? $this->parentOwner($table, $values);
    }

    /** @return array{column: string, table: string}|null */
    public function ownedWith(string $table): ?array
    {
        return $this->rule($table)['owned_with'] ?? null;
    }

    /** review (keep both apart for a person) or merge (one record, the second UUID an alias). */
    public function onDuplicate(string $table): string
    {
        return $this->rule($table)['on_duplicate'] ?? self::REVIEW;
    }

    /** @return list<class-string<ApplyHook>> */
    public function hooks(string $table): array
    {
        $hooks = $this->rule($table)['hooks'] ?? [];
        // Records that point at files register the files they need.
        if ($this->rule($table) !== null && \App\Sync\Files\FileColumns::forTable($table) !== []) {
            $hooks[] = \App\Sync\Files\FileRefsHook::class;
        }

        return $hooks;
    }

    /**
     * Values sent instead of the real ones when the cloud sends a row that
     * does not belong to the receiving vessel (shore users' password hashes).
     *
     * @return array<string, mixed>
     */
    /**
     * A row of this table is sent to a vessel only when it belongs to that
     * vessel or was carried to it before (attachments), never to every
     * vessel because it belongs to none.
     */
    public function strictScope(string $table): bool
    {
        return (bool) ($this->rule($table)['strict_scope'] ?? false);
    }

    /**
     * Referenced rows sent along with a row of this table when the cloud
     * sends it to a vessel: reference column => table.
     *
     * @return array<string, string>
     */
    public function carries(string $table): array
    {
        return $this->rule($table)['carries'] ?? [];
    }

    /**
     * A vessel accepts a change the cloud addressed to it although the row
     * now belongs to another vessel: the row left this vessel (a crew
     * member moved). See App\Sync\VesselDepartures.
     */
    public function acceptsDepartures(string $table): bool
    {
        return (bool) ($this->rule($table)['departures'] ?? false);
    }

    public function redactions(string $table): array
    {
        return $this->rule($table)['redact'] ?? [];
    }

    /**
     * Decides a conflict. $localSide is the side deciding (cloud or vessel).
     * For "latest", the newer updated_at wins and a tie, or a missing time,
     * goes to the cloud.
     *
     * Deletes no longer come here (Checkpoint 14, PD-68 option 1): a delete
     * always stands and a deleted row is never brought back, so the losing
     * change is kept in the conflict record for a person instead. The two
     * delete flags are kept for callers outside the applier.
     *
     * @return bool true when the local version is kept
     */
    public function keepsLocal(string $table, string $localSide, ?string $localUpdatedAt, ?string $remoteUpdatedAt, bool $localDeleted, bool $remoteDeleted): bool
    {
        $policy = $this->rule($table)['conflict'] ?? self::CLOUD;

        if ($policy === self::CLOUD || $policy === self::VESSEL) {
            return $policy === $localSide;
        }

        if ($localDeleted !== $remoteDeleted) {
            return $remoteDeleted;
        }

        if ($localUpdatedAt !== null && $remoteUpdatedAt !== null && $localUpdatedAt !== $remoteUpdatedAt) {
            return strcmp($localUpdatedAt, $remoteUpdatedAt) > 0;
        }

        return $localSide === self::CLOUD;
    }

    /**
     * Tables a reference may point at without being synchronised: every
     * server already holds the same rows (copied when the vessel was set
     * up) and they rarely change.
     *
     * @return list<string>
     */
    public function alwaysPresent(): array
    {
        return array_values((array) config('sync.reference_without_sync', []));
    }

    /**
     * Enabled tables that refer to a table that is not enabled: a row
     * created there after the vessel was set up never reaches the other
     * side, so a change referring to it waits (deferred) until it fails.
     *
     * @return list<string>
     */
    public function warnings(): array
    {
        $warnings = [];
        foreach ($this->enabled() as $table) {
            foreach ($this->references($table) as $column => $referenced) {
                if (! $this->isEnabled($referenced) && ! in_array($referenced, $this->alwaysPresent(), true)) {
                    $warnings[] = "\"{$table}.{$column}\" refers to \"{$referenced}\", which is not enabled.";
                }
            }
        }

        return $warnings;
    }

    /** @return list<string> problems with the rules themselves */
    public function definitionErrors(): array
    {
        $errors = [];

        foreach ($this->defined() as $table => $rule) {
            if (! $this->registry->isSynced($table)) {
                $errors[] = "Replication rule for \"{$table}\", which is not a synchronised table.";
            }
            if (! in_array($rule['direction'], [self::DOWN, self::UP, self::BOTH], true)) {
                $errors[] = "\"{$table}\" needs a direction of down, up or both.";
            }
            if (! in_array($rule['conflict'], [self::CLOUD, self::VESSEL, self::LATEST], true)) {
                $errors[] = "\"{$table}\" needs a conflict rule of cloud, vessel or latest.";
            }
            $sides = [self::CLOUD, self::VESSEL];
            if ($rule['columns'] !== null && ! in_array($rule['columns']['other'], [...$sides, self::SHARED], true)) {
                $errors[] = "\"{$table}\" columns.other must be cloud, vessel or shared.";
            }
            if ($rule['columns'] !== null && $rule['direction'] !== self::BOTH) {
                $errors[] = "\"{$table}\" has column owners, which only mean something for a table that travels both ways.";
            }
            // PD-68 A (decided 2026-11-23): an office retirement stands
            // against a vessel's offline edit. That rule is applied where
            // whole rows are compared, not in the column-by-column merge.
            if ($rule['columns'] !== null && $this->retireColumns($table) !== []) {
                $errors[] = "\"{$table}\" has column owners and retire columns; the PD-68 retirement rule is not applied to column-merged tables.";
            }
            foreach (['creates', 'deletes'] as $key) {
                if ($rule[$key] !== null && ! in_array($rule[$key], $sides, true)) {
                    $errors[] = "\"{$table}\" {$key} must be cloud or vessel.";
                }
            }
            if (! in_array($rule['on_duplicate'], [self::REVIEW, self::MERGE], true)) {
                $errors[] = "\"{$table}\" on_duplicate must be review or merge.";
            }
            if ($rule['on_duplicate'] === self::MERGE && $rule['natural_key'] === []) {
                $errors[] = "\"{$table}\" merges duplicates but has no natural_key.";
            }
            if ($rule['row_owner'] !== null && ! is_string($rule['row_owner']['column'] ?? null)) {
                $errors[] = "\"{$table}\" row_owner needs a column.";
            }
            if ($rule['owned_with'] !== null) {
                $parent = $rule['owned_with']['table'] ?? null;
                if (! is_string($rule['owned_with']['column'] ?? null) || ! is_string($parent)) {
                    $errors[] = "\"{$table}\" owned_with needs a column and a table.";
                } elseif (($this->rule($parent)['row_owner'] ?? null) === null) {
                    $errors[] = "\"{$table}\" is owned with \"{$parent}\", which has no row_owner.";
                }
            }
            if (is_array($rule['review'])) {
                foreach ($rule['review'] as $column) {
                    if ($this->ownerOf($table, $column) !== self::SHARED) {
                        $errors[] = "\"{$table}.{$column}\" is reviewed on conflict but is not a shared column.";
                    }
                }
            }
            foreach ($rule['hooks'] as $hook) {
                if (! is_string($hook) || ! is_subclass_of($hook, ApplyHook::class)) {
                    $errors[] = "\"{$table}\" hook ".json_encode($hook).' is not an '.ApplyHook::class.'.';
                }
            }
            foreach ($rule['carries'] as $column => $carried) {
                if (($rule['references'][$column] ?? null) !== $carried) {
                    $errors[] = "\"{$table}\" carries {$column} as {$carried}, but {$column} is not a reference to {$carried}.";
                }
            }
            foreach ($rule['references'] as $column => $referenced) {
                if (! $this->registry->isSynced($referenced)) {
                    $errors[] = "\"{$table}.{$column}\" refers to \"{$referenced}\", which has no sync UUID.";
                }
            }
        }

        // PD-73: an office-only column means something only on a table a
        // vessel sends.
        foreach (array_keys((array) config('sync.office_only_columns', [])) as $table) {
            if (! isset($this->defined()[$table])) {
                $errors[] = "\"{$table}\" has office-only columns but no replication rule.";
            } elseif ($this->defined()[$table]['direction'] === self::DOWN) {
                $errors[] = "\"{$table}\" has office-only columns but only travels down, so a vessel never sends it.";
            }
        }

        foreach (array_filter(array_map('trim', explode(',', (string) config('sync.enabled_tables', '')))) as $entry) {
            if (str_starts_with($entry, '@') && config('sync.modules.'.substr($entry, 1)) === null) {
                $errors[] = "SYNC_TABLES names module \"{$entry}\", which config/sync.php 'modules' does not define.";
            }
        }
        foreach (array_diff($this->listed(), array_keys($this->defined())) as $table) {
            $errors[] = "SYNC_TABLES lists \"{$table}\", which has no replication rule.";
        }


        return $errors;
    }
}
