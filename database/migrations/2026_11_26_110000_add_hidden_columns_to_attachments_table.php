<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PD-61 (approved 2026-11-23, option 2 with option 3): a file that may
 * still be deleted is hidden from its uploader's library instead of being
 * removed, so no document is lost. Additive only: two nullable columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->timestamp('hidden_at')->nullable();
            $table->unsignedInteger('hidden_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->dropColumn(['hidden_at', 'hidden_by']);
        });
    }
};
