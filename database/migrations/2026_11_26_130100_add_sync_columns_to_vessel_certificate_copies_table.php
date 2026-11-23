<?php

use App\Sync\SyncContext;
use App\Sync\SyncSql;
use App\Sync\TriggerBuilder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gives the certificate copy history a global identity and a version, so
 * it can travel between servers, like the tables in
 * 2026_11_21_100100_add_sync_columns_to_synced_tables (same columns, same
 * backfill, same checks, so an interrupted run can be repeated).
 *
 * The capture triggers are installed afterwards by `sync:triggers install`,
 * as for every other table.
 */
return new class extends Migration
{
    /** Read by the test that every registered table gets its sync columns. */
    private const WITH_SYNC_UUID = ['vessel_certificate_copies'];

    private const CHUNK = 5000;

    public function up(): void
    {
        foreach (self::WITH_SYNC_UUID as $table) {
            if (! Schema::hasTable($table)) {
                continue; // partial schemas (tests) have fewer tables
            }
            if (! Schema::hasColumn($table, 'sync_uuid')) {
                Schema::table($table, fn (Blueprint $t) => $t->char('sync_uuid', 36)->nullable());
            }
            if (! Schema::hasColumn($table, 'sync_version')) {
                Schema::table($table, fn (Blueprint $t) => $t->unsignedInteger('sync_version')->default(1));
            }
            $this->backfill($table);
            if (! Schema::hasIndex($table, $table.'_sync_uuid_unique')) {
                Schema::table($table, fn (Blueprint $t) => $t->unique('sync_uuid'));
            }
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();
        foreach (self::WITH_SYNC_UUID as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            // Its capture triggers use these columns.
            if (in_array($driver, ['mysql', 'sqlite'], true)) {
                foreach (app(TriggerBuilder::class)->dropStatements($driver, $table) as $sql) {
                    DB::unprepared($sql);
                }
            }
            if (Schema::hasIndex($table, $table.'_sync_uuid_unique')) {
                Schema::table($table, fn (Blueprint $t) => $t->dropUnique($table.'_sync_uuid_unique'));
            }
            foreach (['sync_uuid', 'sync_version'] as $column) {
                if (Schema::hasColumn($table, $column)) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropColumn($column));
                }
            }
        }
    }

    private function backfill(string $table): void
    {
        $driver = DB::connection()->getDriverName();
        $uuid = SyncSql::uuidExpression($driver);

        $sql = match ($driver) {
            'mysql' => "UPDATE `{$table}` SET `sync_uuid` = {$uuid} WHERE `sync_uuid` IS NULL LIMIT ".self::CHUNK,
            'sqlite' => "UPDATE \"{$table}\" SET \"sync_uuid\" = {$uuid} WHERE rowid IN (SELECT rowid FROM \"{$table}\" WHERE \"sync_uuid\" IS NULL LIMIT ".self::CHUNK.')',
        };

        // Filling in an id is not a change to the data.
        SyncContext::withoutCapture(function () use ($sql) {
            do {
                $updated = DB::affectingStatement($sql);
            } while ($updated > 0);
        });
    }
};
