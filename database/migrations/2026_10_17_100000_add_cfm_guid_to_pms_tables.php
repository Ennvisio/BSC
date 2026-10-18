<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * cfm_guid marks a row brought in by pms:import-cfm - the CFM export's own
 * Guid for a component or a job, or a deterministic one PmsCfmImporter
 * derives for a job definition split out of a CFM "fleet" definition (one
 * CFM definition can stand for many components; each gets its own row here).
 * Nullable and unique: null for anything made by hand or by the Excel
 * import, and the one thing the importer checks before creating a row again,
 * so re-running the command only adds what's missing.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['pms_components', 'pms_job_definitions', 'pms_jobs'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('cfm_guid', 36)->nullable()->unique()->after('id');
            });
        }
    }

    public function down(): void
    {
        foreach (['pms_components', 'pms_job_definitions', 'pms_jobs'] as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->dropUnique($table.'_cfm_guid_unique');
                $t->dropColumn('cfm_guid');
            });
        }
    }
};
