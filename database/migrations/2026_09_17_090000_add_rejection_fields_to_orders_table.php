<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rejection is terminal: whoever currently holds a requisition can reject it
 * with a reason, and it stops there - it never returns to anyone's queue and
 * the originator raises a fresh one instead. One rejection per requisition,
 * so these live as columns rather than an order_rejections history table.
 *
 * status='rejected' is the marker every query keys off, alongside the
 * existing 'draft'/'delivered'/'received' terminal values.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // users.id is legacy increments() - int(10) unsigned, not bigint.
            $table->unsignedInteger('rejected_by')->nullable()->after('rcv_date');
            // Snapshotted like created_by_role: the rejecter's role at the
            // time, so the record still reads correctly after someone is
            // moved to a different post.
            $table->string('rejected_by_role', 50)->nullable()->after('rejected_by');
            // Where in the chain it died ("Awaiting GM (SRD)", "Tendering").
            // Stored rather than recomputed - once status is 'rejected',
            // currentStageLabel() can no longer derive the stage it was at.
            $table->string('rejected_at_stage', 100)->nullable()->after('rejected_by_role');
            $table->timestamp('rejected_at')->nullable()->after('rejected_at_stage');
            $table->text('rejection_reason')->nullable()->after('rejected_at');

            $table->foreign('rejected_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['rejected_by']);
            $table->dropColumn([
                'rejected_by', 'rejected_by_role', 'rejected_at_stage',
                'rejected_at', 'rejection_reason',
            ]);
        });
    }
};
