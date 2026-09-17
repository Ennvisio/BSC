<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The consumption ledger. vessel_items.stock_qty (see StockService) only
 * ever holds the running balance - this is where the actual record of WHO
 * used HOW MUCH of WHAT, WHEN, and WHY lives, the same way order_procurement_steps
 * is the trail behind orders.procurement_stage's running pointer.
 *
 * Every FK here targets a legacy increments() table (vessels/items/orders/
 * users all have int(10) unsigned ids, not this app's newer bigint ones), so
 * every one of these columns is unsignedInteger, not unsignedBigInteger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_consumptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('vessel_id');
            $table->unsignedInteger('item_id');

            // Optional: if this item was originally requisitioned for a
            // specific job, this ties the full loop together - requisitioned
            // -> received -> consumed - instead of the item just vanishing
            // from stock with no link back to why it was on board at all.
            $table->unsignedInteger('order_id')->nullable();

            // 'used' | 'damaged' | 'expired' | 'lost' | 'other'. All of them
            // deduct stock identically (see StockService::consume()) - this
            // is purely a reporting dimension, not a branch in the deduction
            // logic, the same way order_procurement_steps.outcome doesn't
            // change what advancing the stage actually does.
            $table->string('consumption_type', 20);

            $table->integer('qty');
            $table->date('consumed_on');

            // 'Deck' | 'Engine', same free-text convention orders.department
            // already uses - nullable since not every consuming role maps
            // cleanly to one department.
            $table->string('department')->nullable();

            $table->text('purpose');
            $table->text('remarks')->nullable();

            $table->unsignedInteger('recorded_by');
            $table->timestamps();

            $table->foreign('vessel_id')->references('id')->on('vessels')->onDelete('cascade');
            $table->foreign('item_id')->references('id')->on('items')->onDelete('cascade');
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('set null');
            $table->foreign('recorded_by')->references('id')->on('users')->onDelete('cascade');

            $table->index(['vessel_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_consumptions');
    }
};
