<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PMS jobs - the work orders generated from a definition, one open at a
 * time per definition. vessel_id / completed_by / approved_by are
 * unsignedInteger for the legacy int unsigned vessels.id / users.id keys.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pms_jobs', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->unsignedInteger('vessel_id');
            $t->uuid('component_id');
            $t->uuid('job_definition_id');
            $t->date('due_date')->nullable();
            $t->date('original_due_date')->nullable();
            $t->decimal('due_running_hours', 12, 1)->nullable();
            $t->date('warning_date')->nullable();
            $t->enum('status', ['Planned', 'DueSoon', 'Overdue', 'WaitingApproval', 'Completed', 'Cancelled'])->default('Planned');
            $t->date('completed_at')->nullable();
            $t->foreignId('done_by_rank_id')->nullable()->constrained('ranks');
            $t->decimal('rh_at_completion', 12, 1)->nullable();
            $t->foreignId('condition_after_id')->nullable()->constrained('pms_conditions');
            $t->text('remark')->nullable();
            $t->string('overdue_reason')->nullable();
            $t->text('overdue_remark')->nullable();
            $t->string('reschedule_reason')->nullable();
            $t->text('reschedule_remark')->nullable();
            $t->unsignedInteger('completed_by')->nullable();
            $t->unsignedInteger('approved_by')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->timestamps();

            $t->foreign('vessel_id')->references('id')->on('vessels');
            $t->foreign('component_id')->references('id')->on('pms_components');
            $t->foreign('job_definition_id')->references('id')->on('pms_job_definitions');
            $t->foreign('completed_by')->references('id')->on('users');
            $t->foreign('approved_by')->references('id')->on('users');
            $t->index(['vessel_id', 'status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pms_jobs');
    }
};
