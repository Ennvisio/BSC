<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Certificate copy history between servers (owner's instruction,
 * 2026-11-23): each copy is an immutable historical record whose file can
 * be checked wherever it arrives.
 *
 * - sha256 and file_size: what the file was when it was uploaded. The file
 *   channel checks every copy it fetches against them (App\Sync\Files).
 * - One record per file of a certificate: the same certificate and path
 *   are never listed twice. Stored names are random (SafeUpload), so two
 *   uploads never share a path.
 *
 * Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vessel_certificate_copies', function (Blueprint $table) {
            $table->char('sha256', 64)->nullable()->after('path');
            $table->unsignedBigInteger('file_size')->nullable()->after('sha256');
            $table->unique(['vessel_certificate_id', 'path'], 'vessel_certificate_copies_certificate_path_unique');
        });
    }

    public function down(): void
    {
        Schema::table('vessel_certificate_copies', function (Blueprint $table) {
            $table->dropUnique('vessel_certificate_copies_certificate_path_unique');
            $table->dropColumn(['sha256', 'file_size']);
        });
    }
};
