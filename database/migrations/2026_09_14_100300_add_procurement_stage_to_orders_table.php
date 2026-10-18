<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a requisition sits in the SSM procurement workflow - the stage that is
 * currently AWAITING action, not the last one completed. This is what decides
 * whose queue it's in, so it's denormalised onto orders (one indexed lookup)
 * rather than derived from the latest order_procurement_steps row every time.
 *
 * Null means the requisition hasn't reached procurement yet (DGM SSM hasn't
 * assigned it) OR it predates this feature - in both cases the old
 * status-based behaviour still applies, so nothing in flight is stranded.
 *
 * Deliberately separate from `status`: that column answers "where in the
 * approval chain" and is compared against literal strings ('delivered',
 * 'received') all over the app. The two only overlap at the two handoffs,
 * where both are written in the same transaction.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('procurement_stage', 40)->nullable()->after('status');
            $table->index('procurement_stage');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['procurement_stage']);
            $table->dropColumn('procurement_stage');
        });
    }
};
