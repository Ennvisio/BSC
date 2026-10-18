<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PMS job definitions - the template a job is generated from - with their
 * repeat intervals and checklist. vessel_id is unsignedInteger because
 * vessels.id is a legacy int unsigned key (same as pms_components).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pms_job_definitions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->unsignedInteger('vessel_id');
            $t->uuid('component_id');
            $t->string('name');
            $t->foreignId('job_type_id')->constrained('pms_job_types');
            $t->foreignId('rank_id')->constrained('ranks');
            $t->enum('performed_by', ['Crew', 'External', 'ClassSurveyor'])->default('Crew');
            $t->enum('perform_when', ['Anytime', 'AtSea', 'InPort', 'Docking'])->default('Anytime');
            $t->boolean('is_single')->default(false);
            $t->enum('due_type', ['Date', 'RunningHours'])->nullable();
            $t->date('due_date')->nullable();
            $t->decimal('due_running_hours', 12, 1)->nullable();
            $t->date('start_date')->nullable();
            $t->unsignedSmallInteger('warning_period')->default(7);
            $t->enum('warning_unit', ['Days', 'Weeks', 'Months', 'RunningHours'])->default('Days');
            $t->string('internal_code')->nullable();
            $t->string('class_code')->nullable();
            $t->unsignedSmallInteger('staff_needed')->nullable();
            $t->unsignedSmallInteger('hours_needed')->nullable();
            $t->unsignedSmallInteger('minutes_needed')->nullable();
            $t->text('description')->nullable();
            $t->boolean('requires_approval')->default(false);
            $t->boolean('is_critical')->default(false);
            $t->boolean('is_class_relevant')->default(false);
            $t->boolean('is_safety_relevant')->default(false);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->softDeletes();

            $t->foreign('vessel_id')->references('id')->on('vessels');
            $t->foreign('component_id')->references('id')->on('pms_components');
        });

        Schema::create('pms_job_intervals', function (Blueprint $t) {
            $t->id();
            $t->uuid('job_definition_id');
            $t->unsignedInteger('every');
            $t->enum('period', ['Day', 'Week', 'Month', 'Year', 'RunningHour']);
            $t->timestamps();

            $t->foreign('job_definition_id')->references('id')->on('pms_job_definitions')->cascadeOnDelete();
        });

        Schema::create('pms_job_checklist_items', function (Blueprint $t) {
            $t->id();
            $t->uuid('job_definition_id');
            $t->unsignedSmallInteger('position');
            $t->string('text');

            $t->foreign('job_definition_id')->references('id')->on('pms_job_definitions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pms_job_checklist_items');
        Schema::dropIfExists('pms_job_intervals');
        Schema::dropIfExists('pms_job_definitions');
    }
};
