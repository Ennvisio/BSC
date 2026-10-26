<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A "dependent counter" component (CFM's CounterMode "DependentCounter"):
 * has_counter is still true, but it has no readings of its own - its
 * running hours are read from counter_parent_id's readings instead. Always
 * points straight at an "own meter" component (has_counter true,
 * counter_parent_id null itself) - never chained - that invariant is kept
 * by whatever sets this column, not by the database.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pms_components', function (Blueprint $t) {
            $t->uuid('counter_parent_id')->nullable()->after('has_counter');
            $t->foreign('counter_parent_id')->references('id')->on('pms_components');
            $t->index('counter_parent_id');
        });
    }

    public function down(): void
    {
        Schema::table('pms_components', function (Blueprint $t) {
            $t->dropForeign(['counter_parent_id']);
            $t->dropIndex(['counter_parent_id']);
            $t->dropColumn('counter_parent_id');
        });
    }
};
