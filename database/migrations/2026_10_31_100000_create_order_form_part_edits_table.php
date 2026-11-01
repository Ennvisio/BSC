<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot of a Part A answer set taken right before Master/Chief Engineer
 * overwrites it (see OrderFormPartController::updatePartA()) - so a
 * correction replaces order_form_parts.answers without losing what the ship
 * originally declared. One row per edit, oldest first; order_form_parts
 * itself still carries only the latest edited_by/edited_at, same as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_form_part_edits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_form_part_id');
            $table->json('answers');
            $table->unsignedInteger('edited_by')->nullable();
            $table->timestamp('edited_at');
            $table->timestamps();

            $table->foreign('order_form_part_id')->references('id')->on('order_form_parts')->onDelete('cascade');
            $table->foreign('edited_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_form_part_edits');
    }
};
