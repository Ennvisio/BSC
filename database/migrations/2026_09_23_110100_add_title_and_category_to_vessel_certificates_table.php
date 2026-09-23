<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A vessel's certificate now carries its own title and category, entered by
 * the Master/Chief Engineer recording it - there's no shared certificate-name
 * catalog to pick from any more (the old certificates table stays in place,
 * unused, rather than being dropped with real rows still in it).
 *
 * Existing rows keep their name: title is backfilled from whatever the old
 * certificates row was called, so nothing on file loses its identity.
 * category_id stays null for those - nobody ever recorded a category for
 * them, and guessing one would be inventing data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vessel_certificates', function (Blueprint $table) {
            $table->string('title')->nullable()->after('vessel_id');
            $table->unsignedBigInteger('category_id')->nullable()->after('title');

            $table->foreign('category_id')->references('id')->on('certificate_categories')->onDelete('set null');
        });

        // Carry the old catalog name across as this record's own title.
        DB::statement('
            UPDATE vessel_certificates vc
            JOIN certificates c ON c.id = vc.certificate_id
            SET vc.title = c.name
            WHERE vc.title IS NULL
        ');

        // No longer written to - the title above replaces it - but kept (and
        // its existing values with it) rather than dropped.
        DB::statement('ALTER TABLE vessel_certificates MODIFY certificate_id INT UNSIGNED NULL');
    }

    public function down(): void
    {
        Schema::table('vessel_certificates', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn(['title', 'category_id']);
        });
    }
};
