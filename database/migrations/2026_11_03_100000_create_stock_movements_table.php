<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The stock ledger vessel_items.stock_qty never kept: WHO moved WHAT
 * quantity of WHICH item, WHEN, and WHY - one row per write, the same way
 * stock_consumptions is already the trail behind consumption instead of
 * leaving stock_qty a self-reported number with no history.
 *
 * Every write to vessel_items.stock_qty/opening_stock (see StockService)
 * writes exactly one of these in the same transaction as the balance
 * change:
 *   - 'opening'     the Master declaring/correcting the opening balance
 *                    (setStock/bulkSet touching opening_stock) - qty is the
 *                    declared figure itself, informational only; it does not
 *                    move stock_qty, so balance_after here just reflects
 *                    whatever stock_qty already was.
 *   - 'receipt'      a confirmed requisition delivery (addFromReceipt) -
 *                    qty is positive.
 *   - 'consumption'  an officer logging stock used/damaged/expired/lost, or
 *                    PMS job parts consumed (consume()) - qty is negative.
 *   - 'adjustment'   the Master restating stock_qty by hand, singly
 *                    (setStock) or via a spreadsheet (bulkSet) - qty is the
 *                    signed delta (new − old).
 *
 * This IS isms-02-stores phase 2's planned stock_movements ledger, built
 * now rather than later - phase 2 only ever adds movement types (transfer,
 * shore-warehouse) to this same table.
 *
 * vessel_id/item_id/order_id/order_item_id/user_id are all unsignedInteger,
 * not unsignedBigInteger: orders, order_items, vessels, items and users are
 * all legacy increments() tables (int(10) unsigned), same reasoning
 * stock_consumptions' own migration already documents. stock_consumptions
 * and stock_imports are both normal $table->id() (bigIncrements) tables, so
 * those two FKs are unsignedBigInteger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('vessel_id');
            $table->unsignedInteger('item_id');
            $table->string('type', 20);

            // Signed: + for in (opening/receipt), − for out (consumption),
            // either way for an adjustment. balance_after is always the real
            // vessel_items.stock_qty the moment this row was written, not a
            // value computed by summing qty - trustworthy even if a row is
            // ever corrected or re-run.
            $table->integer('qty');
            $table->integer('balance_after');
            $table->date('moved_on');

            // Where this movement came from, whichever one applies - every
            // write path sets exactly one of these (or none, for a plain
            // manual correction with no document behind it).
            $table->unsignedInteger('order_id')->nullable();
            $table->unsignedInteger('order_item_id')->nullable();
            $table->unsignedBigInteger('stock_consumption_id')->nullable();
            $table->unsignedBigInteger('stock_import_id')->nullable();

            $table->text('remarks')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->timestamps();

            $table->foreign('vessel_id')->references('id')->on('vessels')->onDelete('cascade');
            $table->foreign('item_id')->references('id')->on('items')->onDelete('cascade');
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('set null');
            $table->foreign('order_item_id')->references('id')->on('order_items')->onDelete('set null');
            $table->foreign('stock_consumption_id')->references('id')->on('stock_consumptions')->onDelete('set null');
            $table->foreign('stock_import_id')->references('id')->on('stock_imports')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');

            $table->index(['vessel_id', 'item_id', 'moved_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
