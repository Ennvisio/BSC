<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A short code per vessel (JOY, ARJ, AGJ...), which prefixes every new
 * requisition number - JOY/DK/STR/08/2026 rather than DK/STR/08/2026 - so a
 * number says which ship it belongs to without opening it.
 *
 * Nullable, because vessels that already exist have none until someone sets
 * one on the edit form; unique, since two ships sharing a code would make the
 * prefix meaningless (MySQL allows any number of NULLs under a unique index).
 */
return new class extends Migration
{
    /** Name fragment => acronym, for the vessels already in the fleet list. */
    private const KNOWN = [
        'JOYJATRA' => 'JOY',
        'ARJAN' => 'ARJ',
        'AGRAJATRA' => 'AGJ',
        'AGRADOOT' => 'AGD',
        'AGRAGOTI' => 'AGG',
        'PROGOTI' => 'PRG',
        'NABOJATRA' => 'NBJ',
    ];

    public function up(): void
    {
        Schema::table('vessels', function (Blueprint $table) {
            $table->string('acronym', 5)->nullable()->unique()->after('name');
        });

        // Only fills a blank, and only where the name unambiguously matches -
        // anything not in the list is left for a person to set.
        foreach (self::KNOWN as $fragment => $acronym) {
            DB::table('vessels')
                ->whereNull('acronym')
                ->whereRaw('UPPER(name) LIKE ?', ['%'.$fragment.'%'])
                ->limit(1)
                ->update(['acronym' => $acronym]);
        }
    }

    public function down(): void
    {
        Schema::table('vessels', function (Blueprint $table) {
            $table->dropUnique(['acronym']);
            $table->dropColumn('acronym');
        });
    }
};
