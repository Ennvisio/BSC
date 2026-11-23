<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PD-62 (approved 2026-11-23, option 1): every certificate copy is kept,
 * with who uploaded it and when. Replacing a copy no longer deletes the
 * previous file. Additive only: one new table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vessel_certificate_copies', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('vessel_certificate_id');
            $table->string('path');                              // images/cert_copy/...
            $table->unsignedInteger('uploaded_by')->nullable();  // null: uploaded before the history began
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamp('replaced_at')->nullable();        // null: the current copy
            $table->timestamps();

            $table->index('vessel_certificate_id');
            $table->foreign('vessel_certificate_id')->references('id')->on('vessel_certificates');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vessel_certificate_copies');
    }
};
