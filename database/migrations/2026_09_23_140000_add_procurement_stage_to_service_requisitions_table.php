<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a service requisition currently sits in the procurement workflow it
 * enters once GM (SRD) delegates it. Null means it has not been delegated yet
 * (or predates the workflow) and the approval columns still decide its stage.
 *
 * See App\ServiceProcurementStage for the sequence this holds a position in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_requisitions', function (Blueprint $table) {
            $table->string('procurement_stage', 40)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('service_requisitions', function (Blueprint $table) {
            $table->dropColumn('procurement_stage');
        });
    }
};
