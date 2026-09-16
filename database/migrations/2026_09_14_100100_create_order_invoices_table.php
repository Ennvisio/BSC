<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The invoice header captured at the Invoice Verification stage. One row per
 * order - a requisition goes through one tender, one award, one PO and one
 * delivery, so it receives exactly one invoice (hence the unique order_id).
 *
 * Per-line figures live on order_items (unit_price, invoice_qty, line_total).
 * Subtotal is deliberately NOT stored - it's SUM(line_total), so storing it
 * would only create something that can drift away from the lines.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_invoices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('order_id')->unique();
            $table->string('invoice_no', 60)->nullable();
            $table->date('invoice_date')->nullable();
            // Invoice-level, entered once - not a per-item figure.
            $table->decimal('discount', 12, 2)->default(0);
            // Frozen as-billed amount, so a later line edit can't rewrite what
            // was actually paid.
            $table->decimal('payable', 12, 2)->default(0);
            $table->unsignedInteger('verified_by');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('verified_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_invoices');
    }
};
