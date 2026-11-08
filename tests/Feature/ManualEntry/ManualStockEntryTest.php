<?php

namespace Tests\Feature\ManualEntry;

use App\StockImport;
use App\StockMovement;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsRequisitionSchema;
use Tests\TestCase;

/**
 * The "Enter manually" tab on /stock/upload - the same write path a
 * spreadsheet upload uses (StockController@storeManual -> one
 * StockService::bulkSet() call), just typed in for a few items instead of
 * filled into a sheet.
 */
class ManualStockEntryTest extends TestCase
{
    use BuildsRequisitionSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRequisitionSchema();
    }

    private function carryItem(int $itemId, array $overrides = []): void
    {
        DB::table('vessel_items')->insert($overrides + [
            'vessel_id' => $this->vesselId, 'item_id' => $itemId, 'stock_qty' => 0,
        ]);
    }

    public function test_master_saves_three_items_and_figures_plus_movements_are_correct(): void
    {
        $category = $this->makeCategory();
        $items = [$this->makeItem($category->id), $this->makeItem($category->id), $this->makeItem($category->id)];
        foreach ($items as $item) {
            $this->carryItem($item->id);
        }
        $master = $this->makeUser('master', $this->vesselId);

        $response = $this->actingAs($master)->postJson('/stock/manual', [
            'rows' => [
                ['item_id' => $items[0]->id, 'opening_stock' => 10, 'stock_qty' => 10, 'min_qty' => 2],
                ['item_id' => $items[1]->id, 'opening_stock' => 5, 'stock_qty' => 5],
                ['item_id' => $items[2]->id, 'stock_qty' => 20],
            ],
        ])->assertOk();

        $response->assertJsonFragment(['redirect' => route('stock.history')]);

        foreach ([$items[0]->id => 10, $items[1]->id => 5, $items[2]->id => 20] as $itemId => $expected) {
            $this->assertSame($expected, (int) DB::table('vessel_items')->where('item_id', $itemId)->value('stock_qty'));
        }
        $this->assertSame(2, (int) DB::table('vessel_items')->where('item_id', $items[0]->id)->value('min_qty'));

        // One bulkSet() call writes the 'opening' and 'adjustment' movements
        // exactly like an upload does - same ledger, same reconciliation.
        $this->assertSame(2, StockMovement::where('item_id', $items[0]->id)->count()); // opening + adjustment
        $this->assertSame(StockMovement::TYPE_OPENING, StockMovement::where('item_id', $items[0]->id)->where('type', 'opening')->value('type'));
        $this->assertSame(1, StockMovement::where('item_id', $items[1]->id)->where('type', 'opening')->count());
        $this->assertSame(1, StockMovement::where('item_id', $items[2]->id)->where('type', 'adjustment')->count());

        $import = StockImport::where('vessel_id', $this->vesselId)->sole();
        $this->assertSame(StockImport::SOURCE_MANUAL, $import->source);
        $this->assertSame('Manual entry', $import->filename);
        $this->assertSame('completed', $import->status);
        $this->assertSame(3, $import->row_count);

        $this->assertSame(
            $import->id,
            StockMovement::where('item_id', $items[0]->id)->where('type', 'adjustment')->value('stock_import_id')
        );
    }

    public function test_a_blank_figure_is_left_unchanged(): void
    {
        $category = $this->makeCategory();
        $item = $this->makeItem($category->id);
        $this->carryItem($item->id, ['stock_qty' => 7, 'min_qty' => 3]);
        $master = $this->makeUser('master', $this->vesselId);

        $this->actingAs($master)->postJson('/stock/manual', [
            'rows' => [
                // Only opening_stock supplied - stock_qty and min_qty left blank.
                ['item_id' => $item->id, 'opening_stock' => 15],
            ],
        ])->assertOk();

        $row = DB::table('vessel_items')->where('item_id', $item->id)->first();
        $this->assertSame(15, (int) $row->opening_stock);
        $this->assertSame(7, (int) $row->stock_qty);
        $this->assertSame(3, (int) $row->min_qty);

        // Only the opening_stock change produced a movement.
        $this->assertSame(1, StockMovement::where('item_id', $item->id)->count());
    }

    public function test_non_master_is_refused(): void
    {
        $category = $this->makeCategory();
        $item = $this->makeItem($category->id);
        $this->carryItem($item->id);
        $chiefEngineer = $this->makeUser('chief-engineer', $this->vesselId);

        $this->actingAs($chiefEngineer)->postJson('/stock/manual', [
            'rows' => [['item_id' => $item->id, 'stock_qty' => 5]],
        ])->assertStatus(403);

        $this->assertSame(0, StockMovement::count());
    }

    public function test_an_item_not_in_the_vessels_catalog_is_rejected(): void
    {
        $category = $this->makeCategory();
        $item = $this->makeItem($category->id);
        // Deliberately not carried - no vessel_items row for it.
        $master = $this->makeUser('master', $this->vesselId);

        $this->actingAs($master)->postJson('/stock/manual', [
            'rows' => [['item_id' => $item->id, 'stock_qty' => 5]],
        ])->assertStatus(422);

        $this->assertSame(0, StockMovement::count());
        $this->assertSame(0, DB::table('vessel_items')->where('item_id', $item->id)->count());
    }

    public function test_search_items_returns_only_this_vessels_catalog_including_zero_stock(): void
    {
        $category = $this->makeCategory();
        $item = $this->makeItem($category->id, ['name' => 'Zero Stock Widget']);
        $this->carryItem($item->id, ['stock_qty' => 0]);

        $otherVesselId = $this->makeVessel('MT OTHER VESSEL')->id;
        $otherItem = $this->makeItem($category->id, ['name' => 'Other Vessel Widget']);
        DB::table('vessel_items')->insert(['vessel_id' => $otherVesselId, 'item_id' => $otherItem->id, 'stock_qty' => 0]);

        $master = $this->makeUser('master', $this->vesselId);

        $response = $this->actingAs($master)
            ->getJson('/stock/manual/search-items?q=Widget')
            ->assertOk();

        $names = collect($response->json())->pluck('name');
        $this->assertTrue($names->contains('Zero Stock Widget'), 'a zero-stock item must still be searchable for manual entry');
        $this->assertFalse($names->contains('Other Vessel Widget'));
    }
}
