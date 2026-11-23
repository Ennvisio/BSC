<?php

namespace App\Sync;

use InvalidArgumentException;

/**
 * Reads the synchronised-table registry in config/sync.php. Every other sync
 * class asks this one which tables are captured and how.
 */
class SyncRegistry
{
    public const UUID_COLUMN = 'sync_uuid';

    public const VERSION_COLUMN = 'sync_version';

    public const PRIMARY_KEY = 'id';

    public const CLASSES = ['A', "A'", 'B', 'C', 'D', 'E', 'F'];

    /** @return array<string, array{class: string, uuid: string, vessel: ?string}> */
    public function tables(): array
    {
        $tables = [];

        foreach ((array) config('sync.tables', []) as $table => $definition) {
            $tables[$table] = [
                'class' => $definition['class'] ?? '',
                'uuid' => $definition['uuid'] ?? self::UUID_COLUMN,
                'vessel' => $definition['vessel'] ?? null,
            ];
        }

        return $tables;
    }

    /** @return list<string> */
    public function tableNames(): array
    {
        return array_keys($this->tables());
    }

    /** @return array<string, string> table => reason */
    public function excluded(): array
    {
        return (array) config('sync.excluded', []);
    }

    public function isSynced(string $table): bool
    {
        return array_key_exists($table, $this->tables());
    }

    public function definition(string $table): array
    {
        return $this->tables()[$table]
            ?? throw new InvalidArgumentException("Table \"{$table}\" is not registered for sync.");
    }

    /** The column holding the row's global id: sync_uuid, or `id` for UUID-keyed tables. */
    public function uuidColumn(string $table): string
    {
        return $this->definition($table)['uuid'];
    }

    /** Whether the table got a sync_uuid column (as opposed to reusing a UUID primary key). */
    public function hasSyncUuidColumn(string $table): bool
    {
        return $this->uuidColumn($table) === self::UUID_COLUMN;
    }

    /**
     * The SQL for the row's vessel, with {row} replaced by NEW or OLD, or
     * the literal NULL for fleet-wide tables.
     */
    public function vesselExpression(string $table, string $row): string
    {
        if (! in_array($row, ['NEW', 'OLD'], true)) {
            throw new InvalidArgumentException('Row reference must be NEW or OLD.');
        }

        $expression = $this->definition($table)['vessel'];

        return $expression === null ? 'NULL' : str_replace('{row}', $row, $expression);
    }

    /**
     * Other tables the vessel expression reads, which must exist for the
     * table's triggers to work.
     *
     * @return list<string>
     */
    public function referencedTables(string $table): array
    {
        $expression = (string) $this->definition($table)['vessel'];
        preg_match_all('/\b(?:FROM|JOIN)\s+`?([a-z][a-z0-9_]*)`?/i', $expression, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * Problems in the registry itself: an unknown class, a malformed table
     * name, or a table both synced and excluded.
     *
     * @return list<string>
     */
    public function definitionErrors(): array
    {
        $errors = [];

        foreach ($this->tables() as $table => $definition) {
            if (! preg_match('/^[a-z][a-z0-9_]{0,54}$/', $table)) {
                $errors[] = "\"{$table}\" is not a valid table name for sync.";
            }
            if (! in_array($definition['class'], self::CLASSES, true)) {
                $errors[] = "\"{$table}\" has an unknown class \"{$definition['class']}\".";
            }
            if (! in_array($definition['uuid'], [self::UUID_COLUMN, self::PRIMARY_KEY], true)) {
                $errors[] = "\"{$table}\" must use sync_uuid or id as its uuid column.";
            }
            if (array_key_exists($table, $this->excluded())) {
                $errors[] = "\"{$table}\" is both synchronised and excluded.";
            }
        }

        return $errors;
    }
}
