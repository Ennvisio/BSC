<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Certificate categories - the sections of the fleet's TEC-04 Certificate
 * Checklist (Registry/Tonnage, Statutory Certificates, ...). A real table now
 * rather than a hardcoded list, because super-admin maintains it: they create
 * the categories, and each vessel's Master/Chief Engineer then picks one when
 * recording a certificate of their own (see vessel_certificates.category_id).
 *
 * Seeded with the twelve sections the checklist already uses, so the list
 * isn't empty on day one - super-admin can add to or rename them from there.
 */
return new class extends Migration
{
    private const SEED = [
        'Registry/Tonnage',
        'Statutory Certificates',
        'Environmental Certificates',
        'ISM/ISPS/MLC',
        'Insurance',
        'Safety',
        'Crewing/Health',
        'Navigation and Communications',
        'Ship Inspections',
        'Cargo Installation & Equipment',
        'Mooring Lines and Equipment Certificates',
        'Other Cert',
    ];

    public function up(): void
    {
        Schema::create('certificate_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('status')->default(true);
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();
        });

        $now = now();
        DB::table('certificate_categories')->insert(array_map(fn ($name) => [
            'name' => $name,
            'status' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], self::SEED));
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_categories');
    }
};
