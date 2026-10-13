<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subtotal used to be deliberately uncomputed - just SUM(line_total), so it
 * could never drift from the lines it summed. That assumed every line always
 * gets its own unit price, which isn't always true in practice: sometimes
 * the invoice only gives one lump figure, not a priced line per item. This
 * makes Subtotal its own entered figure, same as Payable already was - "the
 * as-billed amount that was actually verified", not a derivation that falls
 * apart the moment a line is left unpriced. Still pre-filled from summed
 * line totals when those are entered, for the common case where they are.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_invoices', function (Blueprint $table) {
            $table->decimal('subtotal', 12, 2)->default(0)->after('currency_code');
        });
    }

    public function down(): void
    {
        Schema::table('order_invoices', function (Blueprint $table) {
            $table->dropColumn('subtotal');
        });
    }
};
