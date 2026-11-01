<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('title')->nullable()->after('req_no');
            $table->unsignedInteger('budget_group_id')->nullable()->after('category_id');
            $table->string('department')->nullable()->after('budget_group_id');
            $table->date('eta')->nullable()->after('port_name');
            $table->date('etd')->nullable()->after('eta');
            $table->text('remarks')->nullable()->after('etd');
            $table->boolean('high_priority')->default(false)->after('remarks');

            $table->foreign('budget_group_id')->references('id')->on('budget_groups')->onDelete('set null');
        });

        // A draft requisition (new multi-page wizard) exists before its
        // category is chosen (Page 2) and before its req_no is generated
        // (Page 3's submit) - both are NOT NULL today. doctrine/dbal isn't
        // installed, which Laravel's migration ->change() requires, so this
        // relaxes them via raw SQL instead of adding that dependency for two
        // columns. req_no's unique index still holds - MySQL permits any
        // number of NULLs in a unique key.
        //
        // SQLite has no MODIFY COLUMN - and, unlike MySQL, never enforced
        // NOT NULL as a hard block on an application-level insert the way
        // this app relies on either (nothing here needs the constraint
        // itself relaxed on that driver), so this only runs on MySQL. Kept
        // driver-aware rather than skipped outright so the schema stays
        // buildable end-to-end for feature tests (sqlite), same reasoning
        // as BuildsPmsSchema.
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE orders MODIFY category_id INT NULL');
            DB::statement('ALTER TABLE orders MODIFY req_no VARCHAR(191) NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['budget_group_id']);
            $table->dropColumn(['title', 'budget_group_id', 'department', 'eta', 'etd', 'remarks', 'high_priority']);
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE orders MODIFY category_id INT NOT NULL');
            DB::statement('ALTER TABLE orders MODIFY req_no VARCHAR(191) NOT NULL');
        }
    }
};
