<?php

namespace Tests\Feature\ManualEntry;

use App\Imports\ItemCatalogImport;
use App\Imports\VesselStockImport;
use App\Item;
use App\Services\StockService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsRequisitionSchema;
use Tests\TestCase;

/**
 * items.article_number is a manufacturer part code, not a unique id: the same
 * code is reused for different parts, and for the same part in several
 * ship-type files. An item is identified by article number + item group + name
 * (CatalogService::itemKey()), and the stock upload never guesses between
 * items that share a code.
 */
class RepeatedArticleNumberTest extends TestCase
{
    use BuildsRequisitionSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRequisitionSchema();
    }

    private function row(string $path, string $name, string $article): Collection
    {
        return collect([
            'item_path' => $path, 'item_name' => $name, 'article_number' => $article,
            'unit_code' => 'PCE', 'part_number' => '1', 'manufacturer' => 'ACME',
        ]);
    }

    private function importCatalog(int $vesselId, int $categoryId, array $rows): ItemCatalogImport
    {
        $import = new ItemCatalogImport($vesselId, $categoryId, 'tester');
        $import->collection(collect($rows));

        return $import;
    }

    public function test_rows_sharing_an_article_number_stay_separate_items_but_identical_rows_merge(): void
    {
        $category = $this->makeCategory();

        $this->importCatalog($this->vesselId, $category->id, [
            $this->row('Deck->Crane', 'Pump', 'N/A'),
            $this->row('Deck->Crane', 'Thermometer', 'N/A'),   // same code, different part
            $this->row('Deck->Winch', 'Pump', 'N/A'),          // same code + name, different group
            $this->row('DECK->crane', 'PUMP', 'n/a'),          // identical to the first, only case differs
        ]);

        $this->assertSame(3, Item::whereRaw('lower(article_number) = ?', ['n/a'])->count());
        $this->assertSame(3, DB::table('vessel_items')->where('vessel_id', $this->vesselId)->count());
    }

    public function test_reimporting_the_same_file_does_not_create_copies(): void
    {
        $category = $this->makeCategory();
        $rows = [
            $this->row('Deck->Crane', 'Pump', 'X-1'),
            $this->row('Deck->Crane', 'Valve', 'X-1'),
        ];

        $this->importCatalog($this->vesselId, $category->id, $rows);
        $this->importCatalog($this->vesselId, $category->id, $rows);

        $this->assertSame(2, Item::where('article_number', 'X-1')->count());
    }

    public function test_two_vessels_importing_the_same_row_share_one_item(): void
    {
        $category = $this->makeCategory();
        $other = $this->makeVessel('MT OTHER');
        $rows = [$this->row('Deck->Crane', 'Pump', 'X-2')];

        $this->importCatalog($this->vesselId, $category->id, $rows);
        $this->importCatalog($other->id, $category->id, $rows);

        $this->assertSame(1, Item::where('article_number', 'X-2')->count());
        $this->assertSame(2, DB::table('vessel_items')->count());
    }

    private function stockImport(array $rows): VesselStockImport
    {
        $import = new VesselStockImport($this->vesselId, $this->userId ?? null, app(StockService::class));
        $import->collection(collect($rows)->map(fn ($r) => collect($r)));

        return $import;
    }

    private function stockOf(string $name, string $article): int
    {
        $item = Item::where('article_number', $article)->where('name', $name)->firstOrFail();

        return (int) DB::table('vessel_items')->where('vessel_id', $this->vesselId)->where('item_id', $item->id)->value('stock_qty');
    }

    public function test_stock_upload_picks_the_right_item_by_name_and_group_and_refuses_to_guess(): void
    {
        $category = $this->makeCategory();
        $this->importCatalog($this->vesselId, $category->id, [
            $this->row('Deck->Crane', 'Pump', 'Z-9'),
            $this->row('Deck->Winch', 'Pump', 'Z-9'),     // same code AND name, different group
            $this->row('Deck->Crane', 'Valve', 'Z-9'),    // same code, different name
        ]);

        $import = $this->stockImport([
            ['article_number' => 'Z-9', 'item_name' => 'Valve', 'stock_qty' => 4],
            ['article_number' => 'Z-9', 'item_name' => 'Pump', 'item_group' => 'Deck -> Winch', 'stock_qty' => 7],
            ['article_number' => 'Z-9', 'item_name' => 'Pump', 'stock_qty' => 9],   // ambiguous: two Pumps, no group given
            ['article_number' => 'Z-9', 'stock_qty' => 1],                           // ambiguous: three items, no name
            ['article_number' => 'NOPE', 'stock_qty' => 1],                          // not in catalog
        ]);

        $this->assertSame(2, $import->updatedCount);
        $this->assertSame(3, $import->failedCount);
        $this->assertSame(4, $this->stockOf('Valve', 'Z-9'));

        $winchPump = Item::where('article_number', 'Z-9')->where('name', 'Pump')
            ->whereHas('itemGroup', fn ($q) => $q->where('name', 'Winch'))->firstOrFail();
        $cranePump = Item::where('article_number', 'Z-9')->where('name', 'Pump')
            ->whereHas('itemGroup', fn ($q) => $q->where('name', 'Crane'))->firstOrFail();
        $stock = fn ($item) => (int) DB::table('vessel_items')->where('vessel_id', $this->vesselId)->where('item_id', $item->id)->value('stock_qty');
        $this->assertSame(7, $stock($winchPump));
        $this->assertSame(0, $stock($cranePump), 'an ambiguous row must not be written to either Pump');

        $this->assertStringContainsString('matches 2 items', implode("\n", $import->errors));
        $this->assertStringContainsString('matches 3 items', implode("\n", $import->errors));
        $this->assertStringContainsString('not in the catalog', implode("\n", $import->errors));
    }

    public function test_stock_upload_with_a_unique_article_number_needs_no_name(): void
    {
        $category = $this->makeCategory();
        $this->importCatalog($this->vesselId, $category->id, [$this->row('Deck->Crane', 'Gasket', 'U-1')]);

        $import = $this->stockImport([['article_number' => 'U-1', 'stock_qty' => 12]]);

        $this->assertSame(1, $import->updatedCount);
        $this->assertSame(0, $import->failedCount);
        $this->assertSame(12, $this->stockOf('Gasket', 'U-1'));
    }
}
