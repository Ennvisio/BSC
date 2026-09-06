<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `orders.created_by_role` and `order_approvals.second_eng_app` /
 * `forwarded_to_agm_by_gm_srd` / `forwarded_to_am_by_agm_srd` are used
 * throughout RoleController/HomeController/RequisitionController and already
 * exist on this database, but were never captured in a committed migration -
 * added by hand at some point outside `php artisan migrate`. This formalizes
 * them into version control so a fresh install of this app doesn't silently
 * end up missing columns the approval workflow depends on. Guarded with
 * hasColumn() so it's a no-op here (columns already exist) but a real create
 * on a database that doesn't have them yet.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'created_by_role')) {
                $table->string('created_by_role')->default('')->after('created_by');
            }
        });

        Schema::table('order_approvals', function (Blueprint $table) {
            if (! Schema::hasColumn('order_approvals', 'second_eng_app')) {
                $table->integer('second_eng_app')->nullable()->after('cheif_ofcr_app');
            }
            if (! Schema::hasColumn('order_approvals', 'forwarded_to_agm_by_gm_srd')) {
                $table->integer('forwarded_to_agm_by_gm_srd')->nullable()->after('chief_eng_app');
            }
            if (! Schema::hasColumn('order_approvals', 'forwarded_to_am_by_agm_srd')) {
                $table->integer('forwarded_to_am_by_agm_srd')->nullable()->after('forwarded_to_agm_by_gm_srd');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_approvals', function (Blueprint $table) {
            $table->dropColumn(['second_eng_app', 'forwarded_to_agm_by_gm_srd', 'forwarded_to_am_by_agm_srd']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('created_by_role');
        });
    }
};
