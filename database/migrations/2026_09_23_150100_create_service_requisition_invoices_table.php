<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The invoice header captured at Invoice Verification. One row per service
 * requisition - it goes through one tender, one award, one PO and one piece of
 * work, so it receives exactly one invoice (hence the unique column).
 *
 * Per-line figures live on service_requisition_items. Subtotal is deliberately
 * NOT stored - it's SUM(line_total), so storing it would only create something
 * that can drift away from the lines it claims to sum.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_requisition_invoices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('service_requisition_id')->unique('srq_invoice_req_unique');
            $table->string('invoice_no', 60)->nullable();
            $table->date('invoice_date')->nullable();
            // Invoice-level, entered once - not a per-item figure.
            $table->decimal('discount', 12, 2)->default(0);
            // Frozen as-billed amount, so a later line edit can't rewrite what
            // was actually approved for payment.
            $table->decimal('payable', 12, 2)->default(0);
            $table->unsignedInteger('verified_by');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->foreign('service_requisition_id', 'srq_invoice_req_fk')
                ->references('id')->on('service_requisitions')->onDelete('cascade');
            $table->foreign('verified_by', 'srq_invoice_user_fk')
                ->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_requisition_invoices');
    }
};
