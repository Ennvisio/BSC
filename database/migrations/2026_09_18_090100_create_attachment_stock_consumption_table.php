<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links evidence (a photo of the damaged item, an expired-label shot, a
 * defect report) to one consumption record. Same shape as
 * attachment_order_step - attachments.id is a real bigint (see
 * create_attachments_table), so the attachment side stays unsignedBigInteger
 * even though stock_consumption_id follows this new table's own bigint id().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachment_stock_consumption', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('stock_consumption_id');
            $table->unsignedBigInteger('attachment_id');
            $table->timestamps();

            $table->foreign('stock_consumption_id')->references('id')->on('stock_consumptions')->onDelete('cascade');
            $table->foreign('attachment_id')->references('id')->on('attachments')->onDelete('cascade');
            // MySQL's default auto-generated name for this pair - the table
            // name plus both column names - runs past its 64-char identifier
            // limit, so it needs a shorter name spelled out explicitly.
            $table->unique(['stock_consumption_id', 'attachment_id'], 'asc_consumption_attachment_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachment_stock_consumption');
    }
};
