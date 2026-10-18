<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Running-hour meter readings, for components with has_counter only.
 * entered_by is unsignedInteger for the same legacy-key reason as
 * pms_components.vessel_id (users.id is int unsigned).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pms_counter_readings', function (Blueprint $t) {
            $t->id();
            $t->uuid('component_id');
            $t->date('reading_date');
            $t->decimal('value', 12, 1);
            $t->unsignedInteger('entered_by');
            $t->timestamps();

            $t->foreign('component_id')->references('id')->on('pms_components');
            $t->foreign('entered_by')->references('id')->on('users');
            $t->unique(['component_id', 'reading_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pms_counter_readings');
    }
};
