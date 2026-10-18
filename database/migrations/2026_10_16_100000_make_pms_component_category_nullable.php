<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An imported component whose category isn't in the master list is kept
 * without one rather than refused (see App\Services\PmsComponentImporter).
 * The Add / Edit Component form still requires a category.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pms_components', function (Blueprint $t) {
            $t->unsignedBigInteger('category_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('pms_components', function (Blueprint $t) {
            $t->unsignedBigInteger('category_id')->nullable(false)->change();
        });
    }
};
