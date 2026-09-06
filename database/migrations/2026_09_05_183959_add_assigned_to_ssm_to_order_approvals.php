<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DGM (SSM) no longer just opens a requisition up to every SSM officer at
 * once - they assign it to one named person (AGM / AM / Superintendent SSM),
 * and only that person sees it in their panel. This holds that person's user
 * id. Rows already past DGM before this existed have it null, and the SSM
 * queues treat null as "visible to all three", so nothing in flight is
 * stranded.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('order_approvals', function (Blueprint $table) {
            $table->integer('assigned_to_ssm')->nullable()->after('dgm_app_ssm');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_approvals', function (Blueprint $table) {
            $table->dropColumn('assigned_to_ssm');
        });
    }
};
