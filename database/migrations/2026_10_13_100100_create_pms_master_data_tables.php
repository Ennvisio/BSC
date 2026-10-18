<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** PMS master data - the dropdown lists the component and job forms pick from. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pms_locations', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->timestamps();
        });

        Schema::create('pms_component_categories', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->boolean('is_critical')->default(false);
            $t->boolean('is_class_relevant')->default(false);
            $t->timestamps();
        });

        Schema::create('pms_job_types', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('abbreviation', 5);
            $t->timestamps();
        });

        Schema::create('pms_conditions', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->boolean('is_default')->default(false);
            $t->timestamps();
        });

        if (! Schema::hasTable('manufacturers')) {
            Schema::create('manufacturers', function (Blueprint $t) {
                $t->id();
                $t->string('name')->unique();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('manufacturers');
        Schema::dropIfExists('pms_conditions');
        Schema::dropIfExists('pms_job_types');
        Schema::dropIfExists('pms_component_categories');
        Schema::dropIfExists('pms_locations');
    }
};
