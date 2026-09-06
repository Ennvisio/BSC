<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Reason of Requisition (filled by Master/Chief Engineer)" - a field on
 * every one of the revised paper requisition forms, not previously captured
 * anywhere. Filled in by whichever of Master/Chief Engineer is reviewing the
 * origin-stage approval, before forwarding it ashore.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->text('reason')->nullable()->after('port_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('reason');
        });
    }
};
