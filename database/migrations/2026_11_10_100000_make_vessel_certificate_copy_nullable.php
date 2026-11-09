<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A certificate can be recorded before its scan is to hand, so the copy is
 * optional on the Add form (CertificateFormValidate). Reversing this needs
 * any rows saved without a copy to be given one first.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vessel_certificates', function (Blueprint $t) {
            $t->string('cert_copy')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('vessel_certificates', function (Blueprint $t) {
            $t->string('cert_copy')->nullable(false)->change();
        });
    }
};
