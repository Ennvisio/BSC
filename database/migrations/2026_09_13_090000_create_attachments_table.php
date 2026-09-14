<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A user's personal attachment library - one row per file they've ever
 * uploaded, independent of any requisition. Linked to specific order lines
 * through attachment_order_item, so the same spec sheet can back more than
 * one item without a re-upload.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('uploaded_by');
            $table->string('title');
            $table->string('original_filename');
            $table->string('path');
            $table->string('mime_type');
            // Derived once at upload time from mime_type, not re-derived on
            // every render: 'image' | 'pdf' | 'doc' - drives which colored
            // chip/icon a file gets everywhere it's shown.
            $table->string('kind', 20);
            $table->unsignedInteger('file_size');
            $table->timestamps();

            $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
