<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Spares requisition form opens with a block describing the machinery the
 * spares are for - Machinery Name, Maker Details, Model, Type, Maker Contact
 * Details - entered by the officer raising it. One set per requisition (a
 * spares requisition is raised for one piece of machinery); null for every
 * other category.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('machinery_name')->nullable()->after('port_name');
            $table->string('maker_details')->nullable()->after('machinery_name');
            $table->string('machinery_model', 120)->nullable()->after('maker_details');
            $table->string('machinery_type', 120)->nullable()->after('machinery_model');
            $table->string('maker_contact')->nullable()->after('machinery_type');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['machinery_name', 'maker_details', 'machinery_model', 'machinery_type', 'maker_contact']);
        });
    }
};
