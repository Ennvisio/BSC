<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the columns the app filters/joins on most that had none:
 * - items (~90k rows): every list/count filters status and category_id.
 * - order_approvals / order_items: orders are listed through
 *   whereHas('orderApproval') and loaded with their items, both looked up by
 *   order_id, which was unindexed.
 * - orders: vessel + ord_status + status scoping used by every requisition list.
 * - vessel_certificates / vessel_surveys: per-vessel and status lookups.
 *
 * Each index is skipped if a same-named one already exists, so re-running on
 * a database that got any of these by hand is harmless.
 */
return new class extends Migration
{
    private const INDEXES = [
        'items' => [
            'items_status_index' => ['status'],
            'items_category_id_status_index' => ['category_id', 'status'],
        ],
        'orders' => [
            'orders_vessel_ord_status_status_index' => ['vessel_id', 'ord_status', 'status'],
            'orders_ord_status_status_index' => ['ord_status', 'status'],
            'orders_created_by_index' => ['created_by'],
        ],
        'order_approvals' => [
            'order_approvals_order_id_index' => ['order_id'],
        ],
        'order_items' => [
            'order_items_order_id_index' => ['order_id'],
        ],
        'vessel_certificates' => [
            'vessel_certificates_vessel_id_status_index' => ['vessel_id', 'status'],
        ],
        'vessel_surveys' => [
            'vessel_surveys_vessel_id_status_index' => ['vessel_id', 'status'],
            'vessel_surveys_survey_id_index' => ['survey_id'],
        ],
        'vessels' => [
            'vessels_status_index' => ['status'],
        ],
        'categories' => [
            'categories_status_index' => ['status'],
        ],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($indexes as $name => $columns) {
                if (! Schema::hasColumns($table, $columns) || $this->hasIndex($table, $name)) {
                    continue;
                }
                Schema::table($table, fn ($t) => $t->index($columns, $name));
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach (array_keys($indexes) as $name) {
                if ($this->hasIndex($table, $name)) {
                    Schema::table($table, fn ($t) => $t->dropIndex($name));
                }
            }
        }
    }

    private function hasIndex(string $table, string $name): bool
    {
        return ! empty(DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$name]));
    }
};
