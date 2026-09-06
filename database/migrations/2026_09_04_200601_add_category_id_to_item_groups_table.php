<?php

use App\Category;
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
        Schema::table('item_groups', function (Blueprint $table) {
            $table->unsignedInteger('category_id')->nullable()->after('parent_id');
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
        });

        // Every existing item_groups row was created before category-scoping
        // existed, all under the "Imported Catalog" (IMP) placeholder.
        $importedCatalogId = Category::where('symbol', 'IMP')->value('id');
        if ($importedCatalogId) {
            DB::table('item_groups')->update(['category_id' => $importedCatalogId]);
        }

        Schema::table('item_groups', function (Blueprint $table) {
            // parent_id's self-referencing FK needs an index to stay valid -
            // it was relying on unique(parent_id,name) for that (parent_id is
            // its leftmost column); add a dedicated one before dropping it.
            $table->index('parent_id');
            $table->dropUnique(['parent_id', 'name']);
            $table->unique(['category_id', 'parent_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('item_groups', function (Blueprint $table) {
            $table->dropUnique(['category_id', 'parent_id', 'name']);
            $table->unique(['parent_id', 'name']);
            $table->dropIndex(['parent_id']);
            $table->dropForeign(['category_id']);
            $table->dropColumn('category_id');
        });
    }
};
