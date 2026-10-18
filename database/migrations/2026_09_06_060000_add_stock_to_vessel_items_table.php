<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-vessel stock (ROB - remaining on board) hangs off the existing
 * vessel_items pivot, which already carries a unique (vessel_id, item_id)
 * index - exactly what bulk upsert()s need.
 *
 * Stock is deliberately SPARSE: a vessel_items row means "this vessel can
 * order this item", not "this vessel stocks it". Of ~24k catalog items per
 * vessel only a few hundred to a few thousand ever hold real quantity, so
 * stock_qty defaults to 0 and rows are only ever written for items that
 * actually have some.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vessel_items', function (Blueprint $table) {
            // Live ROB, moved by receipts and by the Master's own corrections.
            $table->integer('stock_qty')->default(0)->after('item_id');

            // The vessel's declared opening balance for the item, maintained
            // by hand alongside stock_qty rather than latched automatically.
            // The requisition form carries BOTH "Opening Stock" and "In Stock"
            // as separate columns, so this has to be a figure someone actually
            // states - derive it from stock_qty and the two columns print the
            // same number and neither means anything.
            $table->integer('opening_stock')->nullable()->after('stock_qty');

            // Reorder threshold - drives the "low stock" flag on the picker.
            $table->integer('min_qty')->nullable()->after('opening_stock');

            // Last time this item was actually supplied to the vessel, set by
            // receipt confirmation. Mirrors the paper form's "Quantity of Last
            // Supply" / "Date of Last Supply" columns.
            $table->integer('last_supply_qty')->nullable()->after('min_qty');
            $table->date('last_supply_date')->nullable()->after('last_supply_qty');

            $table->timestamp('stock_updated_at')->nullable()->after('last_supply_date');
            $table->unsignedInteger('stock_updated_by')->nullable()->after('stock_updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('vessel_items', function (Blueprint $table) {
            $table->dropColumn([
                'stock_qty', 'opening_stock', 'min_qty', 'last_supply_qty',
                'last_supply_date', 'stock_updated_at', 'stock_updated_by',
            ]);
        });
    }
};
