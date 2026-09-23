<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who has signed off a service requisition, one row per requisition.
 *
 * Deliberately the same column names as order_approvals so the two chains
 * read identically: each column holds the user id of whoever took that step,
 * null meaning "not yet". The SSM columns are absent - a service requisition
 * ends at GM (SRD).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_requisition_approvals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('service_requisition_id');

            // Origin: whoever raised it, set on submit.
            $table->unsignedInteger('cheif_ofcr_app')->nullable();
            $table->unsignedInteger('second_eng_app')->nullable();

            // Ship review.
            $table->unsignedInteger('master_app')->nullable();
            $table->unsignedInteger('chief_eng_app')->nullable();

            // GM (SRD) and the four delegates GM can hand it to. A
            // forwarded_to_* column holds the id of the GM who delegated it;
            // the matching *_app holds the delegate who reviewed it back.
            $table->unsignedInteger('gm_app')->nullable();
            $table->unsignedInteger('assigned_to_srd')->nullable();
            $table->unsignedInteger('forwarded_to_dgm_srd')->nullable();
            $table->unsignedInteger('dgm_srd_app')->nullable();
            $table->unsignedInteger('forwarded_to_agm_by_gm_srd')->nullable();
            $table->unsignedInteger('agm_app')->nullable();
            $table->unsignedInteger('forwarded_to_am_by_agm_srd')->nullable();
            $table->unsignedInteger('ast_m_app')->nullable();
            $table->unsignedInteger('forwarded_to_superintendent_srd')->nullable();
            $table->unsignedInteger('superintendent_srd_app')->nullable();

            $table->timestamps();

            $table->foreign('service_requisition_id')->references('id')->on('service_requisitions')->onDelete('cascade');
            $table->unique('service_requisition_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_requisition_approvals');
    }
};
