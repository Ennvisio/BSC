<?php

use App\Category;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('is_catalog')->default(false)->after('symbol');
        });

        // The 5 categories from the management circular on revised requisition
        // forms. Paint already existed (id 4) - reused as-is. The other 4 are
        // new. Category has no $fillable, so attributes are set individually
        // rather than mass-assigned - matches the existing app convention
        // (see ItemCatalogImport::importCategoryId(), pre-refactor).
        $toCreate = [
            ['name' => 'Spares', 'symbol' => 'SPR'],
            ['name' => 'Stores', 'symbol' => 'STR'],
            ['name' => 'Chemicals', 'symbol' => 'CHM'],
            ['name' => 'Lub Oil', 'symbol' => 'LUB'],
        ];

        foreach ($toCreate as $data) {
            $category = Category::where('name', $data['name'])->first() ?: new Category;
            $category->name = $data['name'];
            $category->symbol = $data['symbol'];
            $category->is_catalog = true;
            $category->status = true;
            $category->created_by = 'system';
            $category->save();
        }

        // Paint and the existing "Imported Catalog" (IMP) placeholder both
        // already carry real catalog-tree data - flag them catalog-backed too
        // so existing browsing/requisitioning keeps working unchanged.
        Category::whereIn('symbol', ['PNT', 'IMP'])->update(['is_catalog' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Category::whereIn('symbol', ['SPR', 'STR', 'CHM', 'LUB'])->delete();

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('is_catalog');
        });
    }
};
