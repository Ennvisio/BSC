<?php

use App\Services\CatalogService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * items.article_number stops being the unique identifier of an item.
 *
 * In the real spare-parts spreadsheets the "article number" is the
 * manufacturer's part code: the same code is reused for different parts
 * inside one file (values like "N/A" are even used as codes) and the same part
 * code appears in several ship-type files. A unique index forced the importer
 * to merge or overwrite all of those rows.
 *
 * Identity is now items.item_key = sha1(article number | item group | name),
 * all lower-cased - see CatalogService::itemKey(). Imports upsert on that key.
 * article_number keeps a plain (non-unique) index for searching.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropUnique('items_article_number_unique');
            $table->index('article_number');
            $table->string('item_key', 40)->nullable()->unique()->after('article_number');
        });

        // Existing rows get the same key the importer will compute, so a
        // re-import updates them instead of creating a second copy.
        DB::table('items')->orderBy('id')->chunkById(1000, function ($items) {
            foreach ($items as $item) {
                try {
                    DB::table('items')->where('id', $item->id)->update([
                        'item_key' => CatalogService::itemKey((string) $item->article_number, $item->item_group_id, (string) $item->name),
                    ]);
                } catch (\Illuminate\Database\QueryException $e) {
                    // Two legacy rows that share article + group + name: the
                    // later one keeps a NULL key (NULLs don't collide).
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropUnique(['item_key']);
            $table->dropColumn('item_key');
            $table->dropIndex(['article_number']);
            $table->unique('article_number');
        });
    }
};
