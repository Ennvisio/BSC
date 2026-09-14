<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Same pattern as assigned_to_ssm: DGM/AGM/AM/Superintendent (SRD) can each
 * have multiple people, so GM (SRD) no longer just picks a role to delegate
 * to - they pick one named person within it, and only that person sees it in
 * their panel. Rows forwarded before this existed have it null, and the SRD
 * queues treat null as "visible to everyone in that role", so nothing in
 * flight is stranded.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('order_approvals', function (Blueprint $table) {
            $table->integer('assigned_to_srd')->nullable()->after('forwarded_to_superintendent_srd');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_approvals', function (Blueprint $table) {
            $table->dropColumn('assigned_to_srd');
        });
    }
};
