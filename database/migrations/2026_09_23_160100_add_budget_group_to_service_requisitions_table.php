<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which service budget the requisition draws on. Nullable because the few
 * raised before this existed have no answer, and inventing one for them would
 * be worse than leaving it blank.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_requisitions', function (Blueprint $table) {
            // budget_groups.id is a legacy increments() - int(10) unsigned.
            $table->unsignedInteger('budget_group_id')->nullable()->after('service_type');

            $table->foreign('budget_group_id', 'srq_budget_group_fk')
                ->references('id')->on('budget_groups');
        });
    }

    public function down(): void
    {
        Schema::table('service_requisitions', function (Blueprint $table) {
            $table->dropForeign('srq_budget_group_fk');
            $table->dropColumn('budget_group_id');
        });
    }
};
