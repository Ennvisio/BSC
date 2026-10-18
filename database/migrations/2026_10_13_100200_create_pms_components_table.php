<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PMS components: the equipment tree jobs attach to, per vessel, grouped by
 * location. parent_id null = a top-level system.
 *
 * vessel_id is unsignedInteger rather than foreignId(): vessels.id is a
 * legacy increments() key (int unsigned), and a bigint column can't carry a
 * foreign key to it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pms_components', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->unsignedInteger('vessel_id');
            $t->foreignId('location_id')->constrained('pms_locations');
            $t->uuid('parent_id')->nullable();
            $t->string('name');
            $t->string('type')->nullable();
            $t->foreignId('category_id')->constrained('pms_component_categories');
            $t->foreignId('manufacturer_id')->nullable()->constrained();
            $t->string('serial_no')->nullable();
            $t->string('part_no')->nullable();
            $t->string('class_code')->nullable();
            $t->string('internal_code')->nullable();
            $t->date('installation_date')->nullable();
            $t->foreignId('condition_id')->constrained('pms_conditions');
            $t->boolean('has_counter')->default(false);
            $t->boolean('is_critical')->default(false);
            $t->boolean('is_class_relevant')->default(false);
            $t->boolean('is_inactive')->default(false);
            $t->text('remark')->nullable();
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
            $t->softDeletes();

            $t->foreign('vessel_id')->references('id')->on('vessels');
            $t->foreign('parent_id')->references('id')->on('pms_components');
            $t->index(['vessel_id', 'location_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pms_components');
    }
};
