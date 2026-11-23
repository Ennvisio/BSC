<?php

namespace App\Console\Commands;

use App\Sync\SyncContext;
use App\Sync\SyncRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Sync\SyncSql;

/**
 * Gives a sync_uuid to rows that have none: rows written while the capture
 * triggers were not installed. Safe to run any number of times. It runs with
 * capture off, because assigning an id is not a change to the data.
 */
class SyncBackfillUuids extends Command
{
    protected $signature = 'sync:backfill-uuids';

    protected $description = 'Give a sync UUID to every synchronised row that has none';

    public function handle(SyncRegistry $registry): int
    {
        $driver = DB::connection()->getDriverName();
        SyncSql::assertSupported($driver);
        $uuid = SyncSql::uuidExpression($driver);
        $chunk = max(1, (int) config('sync.backfill_chunk', 5000));
        $total = 0;

        foreach ($registry->tableNames() as $table) {
            if (! $registry->hasSyncUuidColumn($table) || ! Schema::hasTable($table) || ! Schema::hasColumn($table, 'sync_uuid')) {
                continue;
            }

            $sql = $driver === 'mysql'
                ? "UPDATE `{$table}` SET `sync_uuid` = {$uuid} WHERE `sync_uuid` IS NULL LIMIT {$chunk}"
                : "UPDATE \"{$table}\" SET \"sync_uuid\" = {$uuid} WHERE rowid IN (SELECT rowid FROM \"{$table}\" WHERE \"sync_uuid\" IS NULL LIMIT {$chunk})";

            $filled = SyncContext::withoutCapture(function () use ($sql) {
                $count = 0;
                do {
                    $updated = DB::affectingStatement($sql);
                    $count += $updated;
                } while ($updated > 0);

                return $count;
            });

            if ($filled > 0) {
                $this->line("{$table}: {$filled}");
            }
            $total += $filled;
        }

        $this->info("Rows given a sync UUID: {$total}.");

        return self::SUCCESS;
    }
}
