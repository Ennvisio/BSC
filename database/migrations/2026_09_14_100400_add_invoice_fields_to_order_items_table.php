<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-line invoice figures, captured by the SSM officer at Invoice
 * Verification.
 *
 * invoice_qty is a genuine FOURTH quantity alongside item_qty (requested),
 * del_item_qty (delivered) and rcv_item_qty (received) - not a duplicate of
 * any of them. The gap between what the vendor billed and what the Master
 * confirmed on board is the whole point of the verification stage.
 *
 * decimal, not int: every existing numeric column on this table is int(11),
 * and money in an int column silently truncates every paisa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->integer('invoice_qty')->nullable()->after('rcv_item_qty');
            $table->decimal('unit_price', 12, 2)->nullable()->after('invoice_qty');
            // unit_price * invoice_qty. Discount is invoice-level (see
            // order_invoices.discount), never per line.
            $table->decimal('line_total', 12, 2)->nullable()->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['invoice_qty', 'unit_price', 'line_total']);
        });
    }
};
