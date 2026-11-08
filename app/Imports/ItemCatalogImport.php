<?php

namespace App\Imports;

use App\Services\CatalogService;
use Illuminate\Support\Collection;
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
 * vessel links with a single insertOrIgnore() - not one query per row. The
 * group-path resolution and the upsert/link mechanics themselves live in
 * CatalogService, shared with manual single-item entry
 * (ItemImportController's "Add item manually" tab) so neither path can
 * drift from the other's behaviour.
 */
class ItemCatalogImport implements ToCollection, WithChunkReading, WithHeadingRow
{
    private int $vesselId;
    private int $categoryId;
    private string $uploadedByName;
    private CatalogService $catalog;

    /** @var array<string,int> "{categoryId}|{parentId}|{name}" => item_groups.id, loaded once */
    private array $groupMap = [];

    public int $rowCount = 0;
    public int $importedCount = 0;
    public int $failedCount = 0;
    /** @var array<int,string> */
    public array $errors = [];

    public function __construct(int $vesselId, int $categoryId, string $uploadedByName, ?CatalogService $catalog = null)
    {
        $this->vesselId = $vesselId;
        $this->categoryId = $categoryId;
        $this->uploadedByName = $uploadedByName;
        $this->catalog = $catalog ?? new CatalogService;
        $this->groupMap = $this->catalog->loadGroupMap();
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

        $itemIds = $this->catalog->upsertItems($prepared);
        $this->catalog->linkVesselToItems($this->vesselId, $itemIds->values());

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
            'item_group_id' => $this->catalog->resolveGroupPath($segments, $this->categoryId, $this->groupMap),
            'category_id' => $this->categoryId,
            'name' => $itemName,
            'unit' => trim((string) ($row['unit_code'] ?? '')) ?: 'PC',
            'account_number' => $this->nullableCell($row, 'account_number'),
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

    private function nullableCell(Collection $row, string $key): ?string
    {
        $value = trim((string) ($row[$key] ?? ''));

        return $value === '' ? null : $value;
    }
}
