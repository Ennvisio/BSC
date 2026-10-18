<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PMS: who is responsible for a job (CE, 2E, CO...). Not the same thing as
 * roles.role - that's an app login role, this is a shipboard rank a job can
 * be assigned to whether or not anyone holding it has a login.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ranks')) {
            return;
        }

        Schema::create('ranks', function (Blueprint $t) {
            $t->id();
            $t->string('code', 10)->unique();
            $t->string('name');
            $t->enum('department', ['Deck', 'Engine', 'Electrical', 'Catering'])->nullable();
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ranks');
    }
};
