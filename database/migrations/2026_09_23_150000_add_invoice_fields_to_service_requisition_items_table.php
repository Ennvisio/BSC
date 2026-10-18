<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-line invoice figures, captured by the assigned SRD officer at Invoice
 * Verification - what each piece of service work was actually billed at.
 *
 * invoice_qty is a genuine second quantity alongside the requested one: the
 * gap between what the yard billed and what was ordered is the point of the
 * verification stage. decimal for money, never int, which would silently
 * truncate every paisa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_requisition_items', function (Blueprint $table) {
            $table->integer('invoice_qty')->nullable()->after('quantity');
            $table->decimal('unit_price', 12, 2)->nullable()->after('invoice_qty');
            // unit_price * invoice_qty. Discount is invoice-level (see
            // service_requisition_invoices.discount), never per line.
            $table->decimal('line_total', 12, 2)->nullable()->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('service_requisition_items', function (Blueprint $table) {
            $table->dropColumn(['invoice_qty', 'unit_price', 'line_total']);
        });
    }
};
