<?php

namespace App\Services;

use App\Item;
use App\ItemGroup;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Catalog item + group-tree logic shared between the bulk Excel import
 * (App\Imports\ItemCatalogImport) and manual single-item entry
 * (ItemImportController's "Add item manually" tab) - one place resolves a
 * "A -> B -> C" path into item_groups nodes and upserts an item, so neither
 * path can drift from the other's behaviour (case-insensitive group
 * matching, the impa_code placeholder, the unit default, the upsert's
 * update-column list).
 */
class CatalogService
{
    /**
     * Every existing item_groups node, pre-loaded into the shape
     * resolveGroupPath() expects. The bulk import loads this once per file
     * (shared across every chunk); manual entry loads it once per request.
     *
     * @return array<string,int> "{categoryId}|{parentId}|{name}" => item_groups.id
     */
    public function loadGroupMap(): array
    {
        $map = [];
        foreach (ItemGroup::all(['id', 'category_id', 'parent_id', 'name']) as $group) {
            $map[$this->groupKey($group->category_id, $group->parent_id, $group->name)] = $group->id;
        }

        return $map;
    }

    /**
     * Walk every segment of a "->"-delimited path (already split), finding-
     * or-creating each item_groups node against $groupMap - the caller's
     * own in-memory cache, updated here so later calls (more rows in this
     * chunk, or this same request) reuse it instead of re-querying.
     *
     * @param  array<string>  $segments
     * @param  array<string,int>  $groupMap  passed by reference, see loadGroupMap()
     */
    public function resolveGroupPath(array $segments, int $categoryId, array &$groupMap): int
    {
        $parentId = null;
        $parentPath = '';
        $groupId = null;

        foreach ($segments as $name) {
            $key = $this->groupKey($categoryId, $parentId, $name);

            if (isset($groupMap[$key])) {
                $groupId = $groupMap[$key];
            } else {
                $path = $parentPath === '' ? $name : $parentPath.' -> '.$name;
                $group = ItemGroup::create([
                    'category_id' => $categoryId,
                    'parent_id' => $parentId,
                    'name' => $name,
                    'path' => $path,
                ]);
                $groupId = $group->id;
                $groupMap[$key] = $groupId;
            }

            $parentId = $groupId;
            $parentPath = $parentPath === '' ? $name : $parentPath.' -> '.$name;
        }

        return $groupId;
    }

    /**
     * item_groups.name uses a case-insensitive collation (utf8mb4_unicode_ci),
     * so MySQL's unique index treats "AND" and "and" as the same value - the
     * in-memory map has to match that or it'll attempt a real duplicate
     * INSERT (see e.g. "Electrodes for Nickel, Nickel-Alloys AND/and
     * Nickel-Alloy Steels" appearing both ways across one real spreadsheet).
     */
    public function groupKey(int $categoryId, ?int $parentId, string $name): string
    {
        return $categoryId.'|'.($parentId ?? 'root').'|'.mb_strtolower($name);
    }

    /**
     * One upsert for a whole batch of prepared item rows (a chunked
     * import's 500 at a time) or a single one (manual entry) - the same
     * match-by-article_number, don't-overwrite-impa_code-on-reimport rule
     * either way. Each row must already carry every column below.
     *
     * @param  array<int,array>  $rows
     * @return \Illuminate\Support\Collection<string,int>  article_number => items.id
     */
    public function upsertItems(array $rows): Collection
    {
        if (empty($rows)) {
            return collect();
        }

        // impa_code is deliberately left out of the update-columns list:
        // it's only ever set on the initial INSERT branch (placeholder
        // '-'), never overwritten on re-import/re-entry.
        Item::upsert(
            $rows,
            ['article_number'],
            ['item_group_id', 'category_id', 'name', 'unit', 'account_number', 'part_number',
                'drawing_number', 'hs_code', 'manufacturer', 'description', 'updated_at']
        );

        return Item::whereIn('article_number', array_column($rows, 'article_number'))
            ->pluck('id', 'article_number');
    }

    /**
     * Create or update one item by article_number and return it - the
     * manual-entry convenience wrapper around upsertItems() above.
     *
     * @param  array{item_group_id:int,category_id:int,name:string,unit?:?string,account_number?:?string,part_number?:?string,drawing_number?:?string,hs_code?:?string,manufacturer?:?string,description?:?string}  $attributes
     */
    public function upsertItem(string $articleNumber, array $attributes, string $createdBy): Item
    {
        $now = now();

        $row = $attributes + [
            'account_number' => null, 'part_number' => null, 'drawing_number' => null,
            'hs_code' => null, 'manufacturer' => null, 'description' => null,
        ] + [
            'article_number' => $articleNumber,
            'unit' => $attributes['unit'] ?? 'PC',
            'impa_code' => '-',
            'created_by' => $createdBy,
            'status' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $ids = $this->upsertItems([$row]);

        return Item::findOrFail($ids[$articleNumber]);
    }

    /**
     * One insertOrIgnore() for a whole batch of item ids (a chunked
     * import's) or a single one (manual entry) - a no-op for any item
     * already linked.
     */
    public function linkVesselToItems(int $vesselId, iterable $itemIds): void
    {
        $itemIds = collect($itemIds)->values();
        if ($itemIds->isEmpty()) {
            return;
        }

        $now = now();

        DB::table('vessel_items')->insertOrIgnore(
            $itemIds->map(fn ($id) => [
                'vessel_id' => $vesselId, 'item_id' => $id, 'created_at' => $now, 'updated_at' => $now,
            ])->all()
        );
    }

    public function linkToVessel(int $itemId, int $vesselId): void
    {
        $this->linkVesselToItems($vesselId, [$itemId]);
    }
}
