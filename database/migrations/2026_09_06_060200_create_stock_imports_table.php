<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit history for bulk stock uploads - deliberately the same shape as
 * item_imports, since a stock upload is the same kind of operation (a large
 * spreadsheet processed in chunks) and the history screens should read alike.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_imports', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('vessel_id');
            $table->unsignedInteger('category_id')->nullable();
            $table->unsignedInteger('uploaded_by');
            $table->string('filename');
            $table->string('status')->default('processing');
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->longText('error_log')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_imports');
    }
};
