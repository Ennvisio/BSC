<?php

namespace Tests\Concerns;

use App\Sync\TriggerManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Adds the Checkpoint 2 sync tables and columns to whichever partial schema
 * a test has built, by running the real sync migrations, and optionally
 * installs the real capture triggers.
 */
trait BuildsSyncSchema
{
    protected const SYNC_MIGRATIONS = [
        '2026_11_21_100000_create_sync_tables.php',
        '2026_11_21_100100_add_sync_columns_to_synced_tables.php',
        '2026_11_23_100000_create_sync_engine_tables.php',
        '2026_11_26_100000_create_sync_deletions_table.php',
        '2026_11_26_130100_add_sync_columns_to_vessel_certificate_copies_table.php',
    ];

    protected function runSyncMigrations(): void
    {
        Artisan::call('migrate', [
            '--path' => array_map(fn ($m) => database_path('migrations/'.$m), self::SYNC_MIGRATIONS),
            '--realpath' => true,
            '--force' => true,
        ]);
    }

    protected function syncMigration(string $file): object
    {
        return require database_path('migrations/'.$file);
    }

    protected function installSyncTriggers(): int
    {
        return app(TriggerManager::class)->install();
    }

    protected function outbox(): Collection
    {
        return DB::table('sync_outbox')->orderBy('id')->get();
    }

    protected function clearOutbox(): void
    {
        DB::table('sync_outbox')->delete();
    }

    protected function assertValidUuid(?string $value, string $message = ''): void
    {
        $this->assertTrue(\App\Sync\SyncSql::isUuid($value), $message ?: var_export($value, true).' is not a UUID');
    }
}
