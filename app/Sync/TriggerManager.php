<?php

namespace App\Sync;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Installs, removes and inspects the change-capture triggers.
 *
 * Installing is all or nothing: if any trigger cannot be created (for
 * example the MySQL account lacks the TRIGGER privilege), every sync
 * trigger is removed again, so the database is never left half-captured.
 */
class TriggerManager
{
    public function __construct(private SyncRegistry $registry, private TriggerBuilder $builder)
    {
    }

    private function connection(): Connection
    {
        return DB::connection();
    }

    private function driver(): string
    {
        return $this->connection()->getDriverName();
    }

    /**
     * Registered tables present in this database. Partial schemas (tests)
     * simply have fewer.
     *
     * @return list<string>
     */
    public function presentTables(): array
    {
        return array_values(array_filter($this->registry->tableNames(), fn ($table) => Schema::hasTable($table)));
    }

    /**
     * Present tables whose vessel lookup reads a table that does not exist.
     * Their triggers would make every write to them fail, so they are not
     * captured. In a complete schema this list is empty.
     *
     * @return list<string>
     */
    public function tablesWithMissingReferences(): array
    {
        return array_values(array_filter($this->presentTables(), function ($table) {
            foreach ($this->registry->referencedTables($table) as $referenced) {
                if (! Schema::hasTable($referenced)) {
                    return true;
                }
            }

            return false;
        }));
    }

    /** @return list<string> present tables that can be captured */
    public function capturableTables(): array
    {
        return array_values(array_diff($this->presentTables(), $this->tablesWithMissingReferences()));
    }

    /**
     * Present tables that are missing their sync columns, meaning the
     * Checkpoint 2 migration has not run.
     *
     * @return list<string>
     */
    public function tablesMissingColumns(): array
    {
        return array_values(array_filter($this->presentTables(), function ($table) {
            $columns = Schema::getColumnListing($table);

            return ! in_array(SyncRegistry::VERSION_COLUMN, $columns, true)
                || ! in_array($this->registry->uuidColumn($table), $columns, true);
        }));
    }

    /** @return int the number of tables now captured */
    public function install(): int
    {
        $driver = $this->driver();
        SyncSql::assertSupported($driver);

        if (! Schema::hasTable('sync_outbox')) {
            throw new RuntimeException('The sync_outbox table is missing. Run "php artisan migrate" first.');
        }

        if (! Schema::hasTable('sync_deletions') || ! Schema::hasColumn('sync_outbox', 'actor_uuid')) {
            throw new RuntimeException('The sync_deletions table or the outbox actor column is missing. Run "php artisan migrate" first.');
        }

        $missing = $this->tablesMissingColumns();
        if ($missing !== []) {
            throw new RuntimeException('These tables have no sync columns yet: '.implode(', ', $missing).'. Run "php artisan migrate" first.');
        }

        SyncContext::registerSqliteFunctions($this->connection());

        $tables = $this->capturableTables();

        try {
            foreach ($tables as $table) {
                foreach ($this->builder->dropStatements($driver, $table) as $sql) {
                    $this->connection()->unprepared($sql);
                }
                foreach ($this->builder->createStatements($driver, $table) as $sql) {
                    $this->connection()->unprepared($sql);
                }
            }
        } catch (Throwable $e) {
            $this->drop();

            throw new RuntimeException('Installing the sync triggers failed, so all of them were removed again. '.$e->getMessage(), 0, $e);
        }

        return count($tables);
    }

    /** Removes every sync trigger, including any left by tables no longer registered. */
    public function drop(): int
    {
        $driver = $this->driver();
        $quote = $driver === 'mysql' ? '`' : '"';
        $names = $this->installedTriggerNames();

        foreach ($names as $name) {
            $this->connection()->unprepared("DROP TRIGGER IF EXISTS {$quote}{$name}{$quote}");
        }

        return count($names);
    }

    /** @return list<string> */
    public function installedTriggerNames(): array
    {
        $driver = $this->driver();

        $rows = match ($driver) {
            'mysql' => $this->connection()->select(
                'SELECT trigger_name AS name FROM information_schema.triggers WHERE trigger_schema = DATABASE() AND trigger_name LIKE ?',
                [TriggerBuilder::PREFIX.'%']
            ),
            'sqlite' => $this->connection()->select(
                "SELECT name FROM sqlite_master WHERE type = 'trigger' AND name LIKE ?",
                [TriggerBuilder::PREFIX.'%']
            ),
            default => [],
        };

        $names = array_map(fn ($row) => $row->name ?? $row->NAME ?? $row->TRIGGER_NAME, $rows);
        sort($names);

        return $names;
    }

    /**
     * Per present table: whether every expected trigger exists.
     *
     * @return array<string, array{expected: int, installed: int}>
     */
    public function status(): array
    {
        $driver = $this->driver();
        $installed = array_flip($this->installedTriggerNames());
        $status = [];

        foreach ($this->capturableTables() as $table) {
            $expected = $this->builder->triggerNames($driver, $table);
            $status[$table] = [
                'expected' => count($expected),
                'installed' => count(array_filter($expected, fn ($name) => isset($installed[$name]))),
            ];
        }

        return $status;
    }

    public function isFullyInstalled(): bool
    {
        $status = $this->status();

        return $status !== [] && array_filter($status, fn ($s) => $s['installed'] !== $s['expected']) === [];
    }
}
