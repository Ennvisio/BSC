<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SRD's "Official Remarks" on a requisition - free text any SRD-level officer
 * (GM and the four delegates) can write and revise once it has reached them,
 * separate from the ship's Reason of Requisition. Who last changed it, and
 * when, is kept alongside so the remark is attributable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->text('official_remarks')->nullable()->after('reason');
            $table->unsignedInteger('official_remarks_by')->nullable()->after('official_remarks');
            $table->timestamp('official_remarks_at')->nullable()->after('official_remarks_by');

            $table->foreign('official_remarks_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['official_remarks_by']);
            $table->dropColumn(['official_remarks', 'official_remarks_by', 'official_remarks_at']);
        });
    }
};
