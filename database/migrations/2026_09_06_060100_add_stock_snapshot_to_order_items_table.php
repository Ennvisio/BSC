<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The paper requisition form carries "Opening Stock", "Quantity of Last
 * Supply" and "Date of Last Supply" per line. Those are SNAPSHOTS taken when
 * the requisition is raised, not live figures: a requisition is an audit
 * document, so reprinting one from six months ago has to show what was true
 * then, not whatever the stock happens to be today.
 *
 * Hence these columns duplicate what vessel_items already holds - that
 * duplication is the point.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->integer('opening_stock')->nullable()->after('item_qty');
            $table->integer('last_supply_qty')->nullable()->after('opening_stock');
            $table->date('last_supply_date')->nullable()->after('last_supply_qty');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['opening_stock', 'last_supply_qty', 'last_supply_date']);
        });
    }
};
