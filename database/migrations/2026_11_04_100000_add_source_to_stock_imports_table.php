<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tells a hand-entered batch (StockController@storeManual) apart from a real
 * spreadsheet upload in Stock History, which otherwise reads them both the
 * same way - same stock_imports row, same StockService::bulkSet() call
 * behind it. Existing rows default to 'upload', the only thing that ever
 * wrote one before this.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_imports', function (Blueprint $table) {
            $table->string('source', 20)->default('upload')->after('filename');
        });
    }

    public function down(): void
    {
        Schema::table('stock_imports', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
