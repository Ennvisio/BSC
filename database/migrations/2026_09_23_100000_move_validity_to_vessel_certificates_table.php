<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Correction to 2026_09_23_090000: validity_years/is_permanent were put on
 * certificates (the catalog - name + category, shared fleet-wide). They
 * actually belong on vessel_certificates - whoever is recording a vessel's
 * own certificate is reading its validity/expiry straight off the physical
 * document in hand, the same way they already type in issue_date and
 * exp_date, not looking it up from a separate admin-maintained catalog.
 * category stays on certificates - that genuinely is fixed per certificate
 * name, shared by every vessel that holds it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropColumn(['validity_years', 'is_permanent']);
        });

        Schema::table('vessel_certificates', function (Blueprint $table) {
            $table->unsignedTinyInteger('validity_years')->nullable()->after('exp_date');
            $table->boolean('is_permanent')->default(false)->after('validity_years');
        });
    }

    public function down(): void
    {
        Schema::table('vessel_certificates', function (Blueprint $table) {
            $table->dropColumn(['validity_years', 'is_permanent']);
        });

        Schema::table('certificates', function (Blueprint $table) {
            $table->unsignedTinyInteger('validity_years')->nullable()->after('category');
            $table->boolean('is_permanent')->default(false)->after('validity_years');
        });
    }
};
