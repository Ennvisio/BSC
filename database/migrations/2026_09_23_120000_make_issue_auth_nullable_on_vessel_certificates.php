<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Issuing Authority is optional on the form now, and the column has been NOT
 * NULL since 2019 - leaving it that way would turn a blank field into a
 * database error at save time rather than an accepted empty value.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE vessel_certificates MODIFY issue_auth VARCHAR(191) NULL');
    }

    public function down(): void
    {
        DB::statement("UPDATE vessel_certificates SET issue_auth = '' WHERE issue_auth IS NULL");
        DB::statement('ALTER TABLE vessel_certificates MODIFY issue_auth VARCHAR(191) NOT NULL');
    }
};
