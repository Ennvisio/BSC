<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documents attached to one completed service procurement stage - tender
 * papers, the PO copy, the vessel's signed acknowledgement of the work.
 * Same pattern as attachment_order_step for the item workflow.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachment_service_req_step', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('step_id');
            // attachments.id - check the real width rather than assuming.
            $table->unsignedBigInteger('attachment_id');
            $table->timestamps();

            $table->unique(['step_id', 'attachment_id'], 'srq_step_att_unique');

            $table->foreign('step_id', 'srq_step_att_step_fk')
                ->references('id')->on('service_requisition_procurement_steps')->onDelete('cascade');
            $table->foreign('attachment_id', 'srq_step_att_att_fk')
                ->references('id')->on('attachments')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachment_service_req_step');
    }
};
