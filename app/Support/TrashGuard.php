<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PD-60 (approved 2026-11-23, option 1): the trash's permanent delete is
 * refused when anything still refers to the record, and for any record of
 * a vessel that runs its own server. The record then stays retired in the
 * trash, which is what "retire instead" means: every type the trash holds
 * is already retired (its status flag) before it appears there.
 *
 * Why: foreign keys cascade (an item takes its stock movements, consumptions
 * and vessel links with it; a requisition its form parts, steps and
 * invoices; a vessel its stock, equipment and service requisitions), and
 * where no key is declared the referring rows are left pointing at nothing.
 *
 * REFERENCES lists every column that refers to a trash table, declared
 * foreign key or not. A test checks that no declared foreign key is missing.
 */
final class TrashGuard
{
    /** Trash type => [model, table, column naming the vessel (null: not a vessel record), retired check]. */
    public const TYPES = [
        'survey' => [\App\VesselSurvey::class, 'vessel_surveys', 'vessel_id', 'status'],
        'certificate' => [\App\VesselCertificate::class, 'vessel_certificates', 'vessel_id', 'status'],
        'order' => [\App\Order::class, 'orders', 'vessel_id', 'ord_status'],
        'item' => [\App\Item::class, 'items', null, 'status'],
        'category' => [\App\Category::class, 'categories', null, 'status'],
        'vessel' => [\App\Vessel::class, 'vessels', 'id', 'status'],
    ];

    /** Referenced table => list of [referring table, column]. */
    public const REFERENCES = [
        'items' => [
            ['order_items', 'item_id'], ['pms_required_parts', 'item_id'], ['pms_used_parts', 'item_id'],
            ['stock_consumptions', 'item_id'], ['stock_movements', 'item_id'], ['vessel_items', 'item_id'],
        ],
        'categories' => [
            ['items', 'category_id'], ['item_groups', 'category_id'], ['item_imports', 'category_id'],
            ['orders', 'category_id'], ['stock_imports', 'category_id'],
        ],
        'orders' => [
            ['order_items', 'order_id'], ['order_approvals', 'order_id'], ['order_form_parts', 'order_id'],
            ['order_invoices', 'order_id'], ['order_procurement_steps', 'order_id'], ['pms_required_parts', 'order_id'],
            ['stock_consumptions', 'order_id'], ['stock_movements', 'order_id'],
        ],
        'vessels' => [
            ['boilers', 'vessel_id'], ['dimensions', 'vessel_id'], ['engines', 'vessel_id'], ['framework_descriptions', 'vessel_id'],
            ['item_imports', 'vessel_id'], ['orders', 'vessel_id'], ['pms_components', 'vessel_id'], ['pms_job_definitions', 'vessel_id'],
            ['pms_jobs', 'vessel_id'], ['roles', 'vessel_id'], ['service_requisitions', 'vessel_id'], ['stock_consumptions', 'vessel_id'],
            ['stock_imports', 'vessel_id'], ['stock_movements', 'vessel_id'], ['vessel_certificates', 'vessel_id'],
            ['vessel_equipment', 'vessel_id'], ['vessel_items', 'vessel_id'], ['vessel_particulars', 'vessel_id'], ['vessel_surveys', 'vessel_id'],
        ],
        'vessel_certificates' => [
            ['service_requisition_items', 'vessel_certificate_id'], ['vessel_certificate_copies', 'vessel_certificate_id'],
        ],
        'vessel_surveys' => [],
    ];

    public function __construct(private AppMode $mode)
    {
    }

    /**
     * Why $type record $id may not be deleted permanently, or null when it
     * may. An unknown type or a missing record is a refusal too.
     */
    public function refusal(string $type, int $id): ?string
    {
        if (! isset(self::TYPES[$type])) {
            return 'This kind of record cannot be deleted from the trash.';
        }
        [, $table, $vesselColumn, $retired] = self::TYPES[$type];

        $row = DB::table($table)->where('id', $id)->first();
        if ($row === null) {
            return 'The record is no longer in the trash.';
        }
        if ((bool) $row->{$retired}) {
            return 'Only a record in the trash can be deleted permanently. Retire it first.';
        }

        if ($vesselColumn !== null && $row->{$vesselColumn} !== null && $this->vesselRunsOwnServer((int) $row->{$vesselColumn})) {
            return 'This record belongs to a vessel that uses its own onboard server, so it cannot be deleted here. It stays retired in the trash.';
        }

        $users = $this->referringTables($table, $id);
        if ($users !== []) {
            $names = array_map(fn ($t) => str_replace('_', ' ', $t), $users);

            return 'Other records still refer to this one ('.implode(', ', $names)
                .'), so deleting it would remove or orphan them. It stays retired in the trash.';
        }

        return null;
    }

    /** @return list<string> the tables that still have a row referring to $table row $id */
    public function referringTables(string $table, int $id): array
    {
        $found = [];
        foreach (self::REFERENCES[$table] ?? [] as [$referring, $column]) {
            if ($referring === $table && $column === 'id') {
                continue;
            }
            if (Schema::hasTable($referring) && Schema::hasColumn($referring, $column)
                && DB::table($referring)->where($column, $id)->exists()) {
                $found[] = $referring;
            }
        }

        return array_values(array_unique($found));
    }

    private function vesselRunsOwnServer(int $vesselId): bool
    {
        return config('navisuite.cloud.lock_offline_vessels', true) && $this->mode->vesselIsOfflineEnabled($vesselId);
    }
}
