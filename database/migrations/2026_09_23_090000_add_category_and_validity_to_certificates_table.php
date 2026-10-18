<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Category and renewal pattern for a certificate TYPE (the shared master row
 * every vessel's own vessel_certificates instance points at - see
 * App\Certificate::CATEGORIES), taken from the fleet's own TEC-04 Certificate
 * Checklist: each named certificate belongs to exactly one of that sheet's
 * sections (Registry/Tonnage, Statutory, Environmental, ...), and either
 * renews on a fixed N-year cycle or never expires at all - both are facts
 * about the certificate itself, true for every vessel that holds it, not
 * something that varies per vessel or per renewal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->string('category')->nullable()->after('name');
            // 1, 2, 5, etc. - null when there's no fixed cycle (is_permanent)
            // or the cycle isn't a simple year count.
            $table->unsignedTinyInteger('validity_years')->nullable()->after('category');
            $table->boolean('is_permanent')->default(false)->after('validity_years');
        });

        // A permanent certificate's vessel_certificates row has no expiry to
        // record - exp_date has been NOT NULL since 2019, which would have
        // forced every permanent certificate to carry a fake date.
        Schema::table('vessel_certificates', function (Blueprint $table) {
            $table->date('exp_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropColumn(['category', 'validity_years', 'is_permanent']);
        });

        Schema::table('vessel_certificates', function (Blueprint $table) {
            $table->date('exp_date')->nullable(false)->change();
        });
    }
};
