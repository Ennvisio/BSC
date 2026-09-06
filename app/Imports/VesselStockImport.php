<?php

namespace App\Imports;

use App\Item;
use App\Services\StockService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Bulk stock upload for one vessel.
 *
 * Expected columns (header row): Article Number, plus any of Opening Stock,
 * Stock Qty and Minimum Qty. Item Name/Unit may be present too - they're
 * ignored, they're only in the downloadable template so whoever fills it in
 * can tell which row is which.
 *
 * Matching is on items.article_number, which carries a unique index, so a
 * whole chunk resolves to item ids in one query and writes in one upsert.
 *
 * A BLANK cell means "leave this figure alone", never "set it to zero" -
 * without that rule a partially filled template would silently wipe the
 * figures of every item the person didn't get to. Each column is judged on
 * its own, so filling in one never disturbs the other two.
 */
class VesselStockImport implements ToCollection, WithChunkReading, WithHeadingRow
{
    /** Spreadsheet heading (as WithHeadingRow slugs it) => vessel_items column. */
    private const COLUMNS = [
        'opening_stock' => 'opening_stock',
        'stock_qty' => 'stock_qty',
        'minimum_qty' => 'min_qty',
    ];

    /** How each figure is named back to the person who filled the sheet in. */
    private const LABELS = [
        'opening_stock' => 'Opening Stock',
        'stock_qty' => 'Stock Qty',
        'min_qty' => 'Minimum Qty',
    ];

    private int $vesselId;
    private ?int $userId;
    private StockService $stock;

    public int $rowCount = 0;
    public int $updatedCount = 0;
    public int $skippedCount = 0;
    public int $failedCount = 0;
    /** @var array<int,string> */
    public array $errors = [];

    public function __construct(int $vesselId, ?int $userId, StockService $stock)
    {
        $this->vesselId = $vesselId;
        $this->userId = $userId;
        $this->stock = $stock;
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function collection(Collection $rows): void
    {
        $parsed = [];

        foreach ($rows as $row) {
            $this->rowCount++;

            $articleNumber = trim((string) ($row['article_number'] ?? ''));
            if ($articleNumber === '') {
                $this->failedCount++;
                $this->errors[] = 'Row '.$this->rowCount.': missing Article Number';
                continue;
            }

            // Each figure is independent: a key is only set when that cell
            // actually holds a number, so filling in one column never disturbs
            // the others. Someone can recount stock without touching their
            // opening balances or thresholds, or set thresholds without
            // recounting anything.
            $values = [];
            $invalid = null;

            foreach (self::COLUMNS as $heading => $figure) {
                $raw = trim((string) ($row[$heading] ?? ''));

                if ($raw === '') {
                    continue;
                }

                if (! is_numeric($raw)) {
                    $invalid = self::LABELS[$figure].' "'.$raw.'" is not a number';
                    break;
                }

                $values[$figure] = (int) $raw;
            }

            if ($invalid !== null) {
                $this->failedCount++;
                $this->errors[] = 'Row '.$this->rowCount.': '.$invalid;
                continue;
            }

            // Wholly untouched row - the whole point of the blank-means-skip
            // rule. Only counts as skipped when EVERY figure is absent.
            if (empty($values)) {
                $this->skippedCount++;
                continue;
            }

            $parsed[$articleNumber] = $values;
        }

        if (empty($parsed)) {
            return;
        }

        $itemIds = Item::whereIn('article_number', array_keys($parsed))
            ->pluck('id', 'article_number');

        $payload = [];
        foreach ($parsed as $articleNumber => $values) {
            if (! isset($itemIds[$articleNumber])) {
                $this->failedCount++;
                $this->errors[] = 'Article Number "'.$articleNumber.'" is not in the catalog';
                continue;
            }

            // $values already holds only the figures the sheet actually
            // supplied - pass them through untouched so StockService can tell
            // "left blank" from "set to this".
            $payload[] = $values + ['item_id' => $itemIds[$articleNumber]];
        }

        $this->updatedCount += $this->stock->bulkSet($this->vesselId, $payload, $this->userId);
    }
}
