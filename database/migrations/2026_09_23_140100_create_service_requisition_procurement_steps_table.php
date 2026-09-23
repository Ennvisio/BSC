<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The service procurement workflow's history: one row per stage as it
 * completes, append-only - the same shape as order_procurement_steps.
 *
 * service_requisitions.procurement_stage holds the CURRENT position (what is
 * awaiting action); this table is everything already done, with who and when.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_requisition_procurement_steps', function (Blueprint $table) {
            $table->bigIncrements('id');
            // service_requisitions.id is a modern $table->id() (bigint), but
            // users.id is a legacy increments() - int(10) unsigned - so the two
            // foreign keys are deliberately different widths.
            $table->unsignedBigInteger('service_requisition_id');
            $table->string('step', 40);
            // 'done', or 'skipped' for an Advance Payment that wasn't required.
            $table->string('outcome', 12)->default('done');
            $table->unsignedInteger('completed_by');
            $table->timestamp('completed_at')->nullable();
            $table->text('remarks')->nullable();
            // Stage-specific fields (tender ref, PO number, awarded vendor) -
            // only about half the stages have any, so real columns would be
            // mostly null.
            $table->json('meta')->nullable();
            $table->timestamps();

            // Forward-only flow: a stage completes exactly once. Named
            // explicitly - the generated name would run past MySQL's 64
            // character identifier limit.
            $table->unique(['service_requisition_id', 'step'], 'srq_step_unique');

            $table->foreign('service_requisition_id', 'srq_step_req_fk')
                ->references('id')->on('service_requisitions')->onDelete('cascade');
            $table->foreign('completed_by', 'srq_step_user_fk')
                ->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_requisition_procurement_steps');
    }
};
