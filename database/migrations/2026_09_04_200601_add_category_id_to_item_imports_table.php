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
        Schema::table('item_imports', function (Blueprint $table) {
            $table->unsignedInteger('category_id')->nullable()->after('vessel_id');
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
        });

        $importedCatalogId = Category::where('symbol', 'IMP')->value('id');
        if ($importedCatalogId) {
            DB::table('item_imports')->update(['category_id' => $importedCatalogId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('item_imports', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn('category_id');
        });
    }
};
