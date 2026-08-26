<?php

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
        Schema::table('items', function (Blueprint $table) {
            $table->unsignedBigInteger('item_group_id')->nullable()->after('category_id');

            // Separate from the existing impa_code column on purpose: real data
            // already has 24 groups of duplicate impa_code values (including a
            // literal "-" placeholder used 27 times), so it can't safely carry
            // a uniqueness constraint. article_number is the clean upsert key
            // imports use going forward; impa_code is left untouched.
            $table->string('article_number')->nullable()->unique()->after('impa_code');

            $table->string('part_number')->nullable()->after('unit');
            $table->string('drawing_number')->nullable()->after('part_number');
            $table->string('hs_code')->nullable()->after('drawing_number');
            $table->string('manufacturer')->nullable()->after('hs_code');
            $table->text('description')->nullable()->after('manufacturer');

            $table->foreign('item_group_id')->references('id')->on('item_groups')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign(['item_group_id']);
            $table->dropColumn([
                'item_group_id',
                'article_number',
                'part_number',
                'drawing_number',
                'hs_code',
                'manufacturer',
                'description',
            ]);
        });
    }
};
