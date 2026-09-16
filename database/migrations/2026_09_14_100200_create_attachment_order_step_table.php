<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documents hung off a procurement STEP rather than off the order, so each
 * file is automatically labelled by the stage it belongs to: tender docs on
 * Tendering, PO copy on Purchase Order, the ship's acknowledgement receipt on
 * Receipt & Verification, the invoice on Invoice Verification.
 *
 * Mirrors attachment_order_item exactly - same Attachment model, same upload
 * and view endpoints, only the link differs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachment_order_step', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('order_procurement_step_id');
            $table->unsignedBigInteger('attachment_id');
            $table->timestamps();

            $table->unique(['order_procurement_step_id', 'attachment_id'], 'attachment_step_unique');

            $table->foreign('order_procurement_step_id', 'aos_step_fk')
                ->references('id')->on('order_procurement_steps')->onDelete('cascade');
            $table->foreign('attachment_id', 'aos_attachment_fk')
                ->references('id')->on('attachments')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachment_order_step');
    }
};
