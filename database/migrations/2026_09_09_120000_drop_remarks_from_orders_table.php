<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remarks is dropped from the wizard's step 1 form - Reason of Requisition
 * takes its place instead of sitting alongside it, so the column is now
 * unused everywhere.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('remarks');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->text('remarks')->nullable()->after('etd');
        });
    }
};
