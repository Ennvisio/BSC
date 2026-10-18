<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A component's name is unique among its siblings: per vessel, per parent,
 * among rows that aren't soft-deleted.
 *
 * MySQL can't express that directly - it has no partial (WHERE deleted_at IS
 * NULL) indexes, and a unique index treats NULLs as distinct, so a plain
 * (vessel_id, parent_id, name) index would never catch two top-level
 * components (parent_id NULL) with the same name. unique_scope covers both:
 * the parent's id, 'root' for a top-level component, and NULL once the row
 * is soft-deleted - which takes it out of the uniqueness check, so a deleted
 * component's name can be reused.
 */
return new class extends Migration
{
    public function up(): void
    {
        // CASE rather than MySQL's IF() so the same expression also works on
        // SQLite (the test suite). SQLite can't ALTER in a STORED generated
        // column, so it gets a VIRTUAL one - same values, same index.
        $expression = "CASE WHEN deleted_at IS NULL THEN COALESCE(parent_id, 'root') ELSE NULL END";

        Schema::table('pms_components', function (Blueprint $t) use ($expression) {
            $column = $t->string('unique_scope', 36)->nullable()->after('sort_order');
            DB::getDriverName() === 'sqlite' ? $column->virtualAs($expression) : $column->storedAs($expression);
            $t->unique(['vessel_id', 'unique_scope', 'name'], 'pms_components_unique_name');
        });
    }

    public function down(): void
    {
        Schema::table('pms_components', function (Blueprint $t) {
            $t->dropUnique('pms_components_unique_name');
            $t->dropColumn('unique_scope');
        });
    }
};
