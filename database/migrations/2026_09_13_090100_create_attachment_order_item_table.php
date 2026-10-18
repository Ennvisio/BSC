<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links one attachment to one requisition line. Many-to-many on both sides:
 * one file can back several lines (the same spec sheet reused across
 * requisitions), and one line can carry several files (a spec sheet plus a
 * photo on the same item).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachment_order_item', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('order_item_id');
            $table->unsignedBigInteger('attachment_id');
            $table->timestamps();

            $table->foreign('order_item_id')->references('id')->on('order_items')->onDelete('cascade');
            $table->foreign('attachment_id')->references('id')->on('attachments')->onDelete('cascade');
            $table->unique(['order_item_id', 'attachment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachment_order_item');
    }
};
