<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One vessel's equipment & maker list ("BSC Fleet - Equipment & Maker List"),
 * used as the pick-list for a Shore Repair service requisition. Per-vessel,
 * not shared across sister ships - two vessels of the same class can (and do
 * in practice) carry different makers for the same named equipment.
 *
 * vessels.id is a legacy increments() id (int(10) unsigned), so the foreign
 * key is unsignedInteger, not unsignedBigInteger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vessel_equipment', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('vessel_id');
            $table->string('name');
            $table->string('maker')->nullable();
            $table->boolean('status')->default(true);
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('vessel_id')->references('id')->on('vessels')->onDelete('cascade');
            // Re-uploading the same equipment name for a vessel updates that
            // row rather than creating a duplicate - matches how the item
            // catalog import matches on article_number.
            $table->unique(['vessel_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vessel_equipment');
    }
};
