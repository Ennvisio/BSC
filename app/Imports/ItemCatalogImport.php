<?php

namespace App\Imports;

use App\Category;
use App\Item;
use App\ItemGroup;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Bulk catalog import for one vessel.
 *
 * Expected columns (header row): Item Path, Item Name, Article Number,
 * Unit Code, Account Number, Description, Part Number, Drawing Number,
 * HS Code, Manufacturer.
 *
 * Item Path is a "->"-delimited breadcrumb (e.g. "00. Provisions->Beans
 * and Peas, Dry->BEANS BROAD DRY") - every segment becomes an item_groups
 * node; the actual item's name always comes from the separate Item Name
 * column, never from the path itself (two items can share the same
 * leaf folder - see e.g. "BEANS KIDNEY" holding two different items).
 *
 * Each chunk is processed as one batch: item_groups resolution is
 * in-memory (only genuinely new folders cause an insert), then the whole
 * chunk's items are written with a single upsert() and the whole chunk's
 * vessel links with a single insertOrIgnore() - not one query per row.
 */
class ItemCatalogImport implements ToCollection, WithChunkReading, WithHeadingRow
{
    private int $vesselId;
    private string $uploadedByName;

    /** @var array<string,int> "{parentId}|{name}" => item_groups.id, loaded once */
    private array $groupMap = [];

    private ?int $importCategoryId = null;

    public int $rowCount = 0;
    public int $importedCount = 0;
    public int $failedCount = 0;
    /** @var array<int,string> */
    public array $errors = [];

    public function __construct(int $vesselId, string $uploadedByName)
    {
        $this->vesselId = $vesselId;
        $this->uploadedByName = $uploadedByName;

        foreach (ItemGroup::all(['id', 'parent_id', 'name']) as $group) {
            $this->groupMap[$this->groupKey($group->parent_id, $group->name)] = $group->id;
        }
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function collection(Collection $rows): void
    {
        $now = now();
        $prepared = [];

        foreach ($rows as $row) {
            $this->rowCount++;

            try {
                $prepared[] = $this->prepareRow($row, $now);
            } catch (\Throwable $e) {
                $this->failedCount++;
                $this->errors[] = 'Row '.$this->rowCount.': '.$e->getMessage();
            }
        }

        if (empty($prepared)) {
            return;
        }

        // One upsert for the whole chunk. impa_code is deliberately left out
        // of the update-columns list: it's only ever set on the initial
        // INSERT branch (placeholder '-'), never overwritten on re-import.
        Item::upsert(
            $prepared,
            ['article_number'],
            ['item_group_id', 'category_id', 'name', 'unit', 'part_number',
                'drawing_number', 'hs_code', 'manufacturer', 'description', 'updated_at']
        );

        $itemIds = Item::whereIn('article_number', array_column($prepared, 'article_number'))
            ->pluck('id');

        DB::table('vessel_items')->insertOrIgnore(
            $itemIds->map(fn ($id) => [
                'vessel_id' => $this->vesselId,
                'item_id' => $id,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all()
        );

        $this->importedCount += count($prepared);
    }

    /**
     * Resolve one row into an upsert-ready array, or throw to mark it failed.
     * Also where new item_groups nodes get created, in-memory-map-first.
     */
    private function prepareRow(Collection $row, $now): array
    {
        $path = trim((string) ($row['item_path'] ?? ''));
        $itemName = trim((string) ($row['item_name'] ?? ''));
        $articleNumber = trim((string) ($row['article_number'] ?? ''));

        if ($path === '' || $itemName === '' || $articleNumber === '') {
            throw new \RuntimeException('missing Item Path, Item Name, or Article Number');
        }

        $segments = array_values(array_filter(array_map('trim', explode('->', $path)), fn ($s) => $s !== ''));
        if (empty($segments)) {
            throw new \RuntimeException('empty Item Path');
        }

        return [
            'article_number' => $articleNumber,
            'item_group_id' => $this->resolveGroupPath($segments),
            'category_id' => $this->importCategoryId(),
            'name' => $itemName,
            'unit' => trim((string) ($row['unit_code'] ?? '')) ?: 'PC',
            'part_number' => $this->nullableCell($row, 'part_number'),
            'drawing_number' => $this->nullableCell($row, 'drawing_number'),
            'hs_code' => $this->nullableCell($row, 'hs_code'),
            'manufacturer' => $this->nullableCell($row, 'manufacturer'),
            'description' => $this->nullableCell($row, 'description'),
            'impa_code' => '-',
            'created_by' => $this->uploadedByName,
            'status' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * Walk every segment of the path, finding-or-creating each item_groups
     * node against the in-memory map (populated once in the constructor,
     * updated immediately on every insert so later rows - in this chunk or
     * any later one - reuse it instead of re-querying the database).
     */
    private function resolveGroupPath(array $segments): int
    {
        $parentId = null;
        $parentPath = '';
        $groupId = null;

        foreach ($segments as $name) {
            $key = $this->groupKey($parentId, $name);

            if (isset($this->groupMap[$key])) {
                $groupId = $this->groupMap[$key];
            } else {
                $path = $parentPath === '' ? $name : $parentPath.' -> '.$name;
                $group = ItemGroup::create([
                    'parent_id' => $parentId,
                    'name' => $name,
                    'path' => $path,
                ]);
                $groupId = $group->id;
                $this->groupMap[$key] = $groupId;
            }

            $parentId = $groupId;
            $parentPath = $parentPath === '' ? $name : $parentPath.' -> '.$name;
        }

        return $groupId;
    }

    private function groupKey(?int $parentId, string $name): string
    {
        return ($parentId ?? 'root').'|'.$name;
    }

    private function nullableCell(Collection $row, string $key): ?string
    {
        $value = trim((string) ($row[$key] ?? ''));

        return $value === '' ? null : $value;
    }

    /**
     * Every import-created item needs a category_id (legacy NOT NULL column
     * used only for requisition req_no prefixing) even though its real
     * classification now lives in item_group_id. Route all imported items
     * through one dedicated placeholder category rather than guessing a
     * mapping onto the existing Deck/Engine/etc. requisition categories.
     */
    private function importCategoryId(): int
    {
        if ($this->importCategoryId === null) {
            // Category has no $fillable, so mass-assignment (firstOrCreate's
            // array form) is blocked - the existing app code always sets
            // attributes individually instead, so match that here too.
            $category = Category::where('symbol', 'IMP')->first();

            if (! $category) {
                $category = new Category;
                $category->name = 'Imported Catalog';
                $category->symbol = 'IMP';
                $category->created_by = $this->uploadedByName;
                $category->status = true;
                $category->save();
            }

            $this->importCategoryId = $category->id;
        }

        return $this->importCategoryId;
    }
}
