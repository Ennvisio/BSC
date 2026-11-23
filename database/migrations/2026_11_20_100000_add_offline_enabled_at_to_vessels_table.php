<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a vessel was switched to its own onboard NaviSuite server. While it is
 * set, that vessel's ship crew can only view, not change, data on the cloud
 * (App\Http\Middleware\EnforceAppMode), so each vessel has one author.
 *
 * Additive and nullable: every existing vessel stays NULL, which is exactly
 * the behaviour before this column existed. It is set and cleared with
 * `php artisan navisuite:vessel-offline`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('vessels', 'offline_enabled_at')) {
            return;
        }

        Schema::table('vessels', function (Blueprint $table) {
            $table->timestamp('offline_enabled_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('vessels', 'offline_enabled_at')) {
            return;
        }

        Schema::table('vessels', function (Blueprint $table) {
            $table->dropColumn('offline_enabled_at');
        });
    }
};
