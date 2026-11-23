<?php

use App\Sync\SyncContext;
use App\Sync\SyncSql;
use App\Sync\TriggerManager;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gives every synchronised row a global identity and a version (Checkpoint 2).
 *
 * - sync_uuid CHAR(36), unique: the row's id across the cloud and every
 *   vessel. Existing primary keys are untouched and stay the local key.
 * - sync_version INT, default 1: goes up on every change, for conflict
 *   detection.
 *
 * Existing rows get a UUID each, in batches so no table is locked for long.
 * The three PMS tables already have UUID primary keys, so they only get
 * sync_version.
 *
 * The table list is a snapshot of config/sync.php at the time of writing,
 * as migrations must not change when the config does. Each step checks
 * whether it has already happened, so a run interrupted half way can simply
 * be repeated. down() removes the capture triggers (which depend on these
 * columns) and then the columns.
 */
return new class extends Migration
{
    private const WITH_SYNC_UUID = [
        'attachment_order_item', 'attachment_order_step', 'attachment_service_req_step',
        'attachment_stock_consumption', 'attachments', 'boilers', 'budget_groups', 'categories',
        'certificate_categories', 'certificates', 'currencies', 'dimensions', 'engines',
        'framework_descriptions', 'item_groups', 'item_imports', 'items', 'manufacturers',
        'order_approvals', 'order_form_part_edits', 'order_form_parts', 'order_invoices',
        'order_item_quantity_changes', 'order_items', 'order_procurement_steps', 'orders',
        'pms_component_categories', 'pms_conditions', 'pms_counter_readings',
        'pms_job_checklist_items', 'pms_job_intervals', 'pms_job_types', 'pms_locations',
        'pms_required_parts', 'pms_used_parts', 'ranks', 'roles', 'service_requisition_approvals',
        'service_requisition_invoices', 'service_requisition_items',
        'service_requisition_procurement_steps', 'service_requisitions', 'stock_consumptions',
        'stock_imports', 'stock_movements', 'surveys', 'users', 'vessel_certificates',
        'vessel_equipment', 'vessel_items', 'vessel_particulars', 'vessel_surveys', 'vessels',
    ];

    /** Already keyed by UUID: the primary key is reused as the global id. */
    private const UUID_PRIMARY_KEY = ['pms_components', 'pms_job_definitions', 'pms_jobs'];

    private const CHUNK = 5000;

    public function up(): void
    {
        foreach (array_merge(self::WITH_SYNC_UUID, self::UUID_PRIMARY_KEY) as $table) {
            if (! Schema::hasTable($table)) {
                continue; // partial schemas (tests) have fewer tables
            }

            $withUuid = in_array($table, self::WITH_SYNC_UUID, true);

            if ($withUuid && ! Schema::hasColumn($table, 'sync_uuid')) {
                Schema::table($table, fn (Blueprint $t) => $t->char('sync_uuid', 36)->nullable());
            }

            if (! Schema::hasColumn($table, 'sync_version')) {
                Schema::table($table, fn (Blueprint $t) => $t->unsignedInteger('sync_version')->default(1));
            }

            if ($withUuid) {
                $this->backfill($table);

                if (! Schema::hasIndex($table, $table.'_sync_uuid_unique')) {
                    Schema::table($table, fn (Blueprint $t) => $t->unique('sync_uuid'));
                }
            }
        }
    }

    public function down(): void
    {
        // The capture triggers use these columns. Left in place, they would
        // make every write to these tables fail once the columns are gone.
        app(TriggerManager::class)->drop();

        foreach (array_merge(self::WITH_SYNC_UUID, self::UUID_PRIMARY_KEY) as $table) {
            if (! Schema::hasTable($table)) {
                continue;
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

    /** Gives every row without a sync_uuid its own, a batch at a time. */
    private function backfill(string $table): void
    {
        $driver = DB::connection()->getDriverName();
        $uuid = SyncSql::uuidExpression($driver);

        $sql = match ($driver) {
            'mysql' => "UPDATE `{$table}` SET `sync_uuid` = {$uuid} WHERE `sync_uuid` IS NULL LIMIT ".self::CHUNK,
            'sqlite' => "UPDATE \"{$table}\" SET \"sync_uuid\" = {$uuid} WHERE rowid IN (SELECT rowid FROM \"{$table}\" WHERE \"sync_uuid\" IS NULL LIMIT ".self::CHUNK.')',
        };

        // Filling in an id is not a change to the data: if the capture
        // triggers are already installed, they must not record it.
        SyncContext::withoutCapture(function () use ($sql) {
            do {
                $updated = DB::affectingStatement($sql);
            } while ($updated > 0);
        });
    }
};
