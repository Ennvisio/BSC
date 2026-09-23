<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Service requisitions budget against a different set of groups than item
 * requisitions do - Dry-Dock and Plate Renewal are not things you draw from
 * Deck Store or Victualing. One table with a `kind` rather than a second
 * table, so the existing super-admin screen manages both and the two never
 * silently mix in a picker.
 *
 * Everything already on file is an item group: those 19 rows are Deck store,
 * Engine Spare, Lub Oil and the like, all of them stores categories.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_groups', function (Blueprint $table) {
            $table->string('kind', 20)->default('item')->after('name')->index();
        });

        $now = now();
        $rows = [
            'Repair Maintenance & Survey',
            'Accidental Damage Repair',
            'Plate Renewal',
            'Dry-Dock',
            'Fleet Communication',
        ];

        foreach ($rows as $name) {
            // Guarded rather than a blind insert - re-running this on a
            // database that already has them would otherwise duplicate the set.
            if (DB::table('budget_groups')->where('name', $name)->exists()) {
                continue;
            }

            DB::table('budget_groups')->insert([
                'name' => $name,
                'kind' => 'service',
                'status' => true,
                'created_by' => 'system',
                'updated_by' => '',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('budget_groups')->where('kind', 'service')->delete();

        Schema::table('budget_groups', function (Blueprint $table) {
            $table->dropIndex(['kind']);
            $table->dropColumn('kind');
        });
    }
};
