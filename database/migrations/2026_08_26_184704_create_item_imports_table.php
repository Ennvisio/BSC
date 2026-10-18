<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('item_imports', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('vessel_id');
            $table->unsignedInteger('uploaded_by');
            $table->string('filename');
            $table->string('status')->default('processing');
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('imported_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->longText('error_log')->nullable();
            $table->timestamps();

            $table->foreign('vessel_id')->references('id')->on('vessels')->onDelete('cascade');
            $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_imports');
    }
};
