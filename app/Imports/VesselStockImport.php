<?php

namespace App\Imports;

use App\Item;
use App\Services\StockService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Bulk stock upload for one vessel.
 *
 * Expected columns (header row): Article Number, plus any of Opening Stock,
 * Stock Qty and Minimum Qty. Item Name / Item Group / Unit are in the
 * downloadable template so whoever fills it in can tell which row is which,
 * and Item Name / Item Group are also used to pick the right item when an
 * article number is shared by several items.
 *
 * Matching starts from items.article_number, which is NOT unique (one
 * manufacturer part code can belong to several catalog items). When a number
 * matches more than one item the row is narrowed down - first to the items in
 * this vessel's catalog, then by Item Name, then by Item Group - and if it is
 * still ambiguous the row is reported as failed rather than guessed at. A whole
 * chunk resolves to item ids in two queries and writes in one upsert.
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
    /** The stock_imports row this upload is already filed under, so every movement it writes links back to it. */
    private ?int $stockImportId;

    public int $rowCount = 0;
    public int $updatedCount = 0;
    public int $skippedCount = 0;
    public int $failedCount = 0;
    /** @var array<int,string> */
    public array $errors = [];

    public function __construct(int $vesselId, ?int $userId, StockService $stock, ?int $stockImportId = null)
    {
        $this->vesselId = $vesselId;
        $this->userId = $userId;
        $this->stock = $stock;
        $this->stockImportId = $stockImportId;
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

            $parsed[] = [
                'row' => $this->rowCount,
                'article' => $articleNumber,
                'name' => $this->lower($row['item_name'] ?? ''),
                'group' => $this->lower($row['item_group'] ?? ''),
                'values' => $values,
            ];
        }

        if (empty($parsed)) {
            return;
        }

        // Every item that carries one of this chunk's article numbers, with
        // its group path, grouped by (lower-cased) article number. Two queries
        // per chunk regardless of how many items share a number.
        $byArticle = Item::query()
            ->leftJoin('item_groups', 'item_groups.id', '=', 'items.item_group_id')
            ->whereIn('items.article_number', array_unique(array_column($parsed, 'article')))
            ->get(['items.id', 'items.article_number', 'items.name', 'item_groups.path as group_path'])
            ->groupBy(fn ($item) => $this->lower($item->article_number));

        $inThisVessel = DB::table('vessel_items')
            ->where('vessel_id', $this->vesselId)
            ->whereIn('item_id', $byArticle->flatten(1)->pluck('id'))
            ->pluck('item_id')
            ->flip();

        // Keyed by item id so a sheet that lists the same item twice still
        // writes it once (the later row wins, as it always did).
        $payload = [];
        foreach ($parsed as $entry) {
            $pool = $byArticle->get($this->lower($entry['article']), collect());

            if ($pool->isEmpty()) {
                $this->failedCount++;
                $this->errors[] = 'Row '.$entry['row'].': Article Number "'.$entry['article'].'" is not in the catalog';
                continue;
            }

            if ($pool->count() > 1) {
                $pool = $this->narrow($pool, fn ($i) => isset($inThisVessel[$i->id]));
            }
            if ($pool->count() > 1 && $entry['name'] !== '') {
                $pool = $this->narrow($pool, fn ($i) => $this->lower($i->name) === $entry['name']);
            }
            if ($pool->count() > 1 && $entry['group'] !== '') {
                $pool = $this->narrow($pool, fn ($i) => $this->lower($i->group_path) === $entry['group']);
            }

            if ($pool->count() > 1) {
                $this->failedCount++;
                $this->errors[] = 'Row '.$entry['row'].': Article Number "'.$entry['article'].'" matches '.$pool->count()
                    .' items - use the Item Name and Item Group columns from the downloaded template so the right one can be picked';
                continue;
            }

            // The figures already hold only what the sheet actually supplied -
            // pass them through untouched so StockService can tell "left
            // blank" from "set to this".
            $itemId = $pool->first()->id;
            $payload[$itemId] = $entry['values'] + ['item_id' => $itemId];
        }

        $this->updatedCount += $this->stock->bulkSet($this->vesselId, array_values($payload), $this->userId, $this->stockImportId);
    }

    /** Keep only the candidates the filter accepts - but never narrow to nothing (an empty result means the filter had no opinion). */
    private function narrow(Collection $pool, callable $keep): Collection
    {
        $kept = $pool->filter($keep);

        return $kept->isEmpty() ? $pool : $kept->values();
    }

    private function lower($value): string
    {
        return mb_strtolower(trim((string) $value));
    }
}
