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
        Schema::create('vessel_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('vessel_id');
            $table->unsignedInteger('item_id');
            $table->timestamps();

            $table->foreign('vessel_id')->references('id')->on('vessels')->onDelete('cascade');
            $table->foreign('item_id')->references('id')->on('items')->onDelete('cascade');
            $table->unique(['vessel_id', 'item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vessel_items');
    }
};
