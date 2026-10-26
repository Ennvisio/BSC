<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * For the PMS reports. (vessel_id, status, due_date) already serves one
 * vessel's open jobs; these cover the same questions asked fleet-wide
 * (status first, no vessel) and job history by completion date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pms_jobs', function (Blueprint $t) {
            $t->index(['status', 'due_date']);
            $t->index(['status', 'completed_at']);
            $t->index(['vessel_id', 'status', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('pms_jobs', function (Blueprint $t) {
            $t->dropIndex(['status', 'due_date']);
            $t->dropIndex(['status', 'completed_at']);
            $t->dropIndex(['vessel_id', 'status', 'completed_at']);
        });
    }
};
