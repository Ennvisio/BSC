<?php

namespace Tests\Feature\Inventory;

use App\Services\StockService;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsRequisitionSchema;
use Tests\TestCase;

/**
 * Access to /inventory/stock (App\Support\InventoryGate) and the figures its
 * data() endpoint aggregates from stock_movements.
 */
class CurrentStockTest extends TestCase
{
    use BuildsRequisitionSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRequisitionSchema();
    }

    // ---------------------------------------------------------------- access

    public function test_any_ship_role_can_view_their_own_vessels_current_stock(): void
    {
        $officer = $this->makeUser('chief-officer', $this->vesselId);

        $this->actingAs($officer)->get('/inventory/stock')->assertOk();
    }

    /**
     * Stock Report stays Master/Chief Engineer only - Current Stock is wider
     * (see InventoryGate), but this older page is never widened by it.
     */
    public function test_chief_officer_still_cannot_view_the_older_stock_report(): void
    {
        $officer = $this->makeUser('chief-officer', $this->vesselId);

        $this->actingAs($officer)->get('/stock/report')->assertStatus(403);
    }

    public function test_a_fleet_wide_role_can_view_current_stock_for_any_vessel(): void
    {
        $gm = $this->makeUser('gm-srd');

        $this->actingAs($gm)->get('/inventory/stock')->assertOk();
    }

    public function test_a_fleet_wide_role_must_choose_a_vessel_before_data_loads(): void
    {
        $gm = $this->makeUser('gm-srd');
        $category = $this->makeCategory();

        $this->actingAs($gm)
            ->getJson('/inventory/stock/data?category_id='.$category->id)
            ->assertStatus(422);
    }

    public function test_a_role_outside_the_gate_cannot_view_current_stock(): void
    {
        $techSuper = $this->makeUser('technical-superintendent');

        $this->actingAs($techSuper)->get('/inventory/stock')->assertStatus(403);
    }

    // ---------------------------------------------------------------- data aggregation

    public function test_current_stock_data_aggregates_movements_from_the_ledger(): void
    {
        $category = $this->makeCategory();
        $item = $this->makeItem($category->id);
        $officer = $this->makeUser('master', $this->vesselId);
        $stock = new StockService;

        // Opening 10 is purely informational - it never feeds stock_qty, so
        // the running balance is 0 (+5 received -4 consumed = 1) before the
        // manual correction up to 20, which is what net_adjustments (19)
        // should read - the adjustment alone, not the running total.
        $stock->setStock($this->vesselId, $item->id, ['opening_stock' => 10], $officer->id);
        $stock->addFromReceipt($this->vesselId, $item->id, 5, $officer->id, now());
        $stock->consume($this->vesselId, $item->id, 4, 'used', 'Testing', now()->toDateString(), $officer->id);
        $stock->setStock($this->vesselId, $item->id, ['stock_qty' => 20], $officer->id);

        $response = $this->actingAs($officer)
            ->getJson('/inventory/stock/data?vessel_id='.$this->vesselId.'&category_id='.$category->id.'&draw=1&start=0&length=25')
            ->assertOk();

        $row = collect($response->json('data'))->firstWhere('name', $item->name);

        $this->assertNotNull($row);
        $this->assertSame(10, $row['opening_stock']);
        $this->assertSame(5, $row['total_received']);
        $this->assertSame(4, $row['total_consumed']);
        $this->assertSame(19, $row['net_adjustments']);
        $this->assertSame(20, $row['stock_qty']);
        $this->assertSame((int) DB::table('vessel_items')->where('item_id', $item->id)->value('stock_qty'), $row['stock_qty']);
    }

    public function test_low_stock_is_flagged(): void
    {
        $category = $this->makeCategory();
        $item = $this->makeItem($category->id);
        $officer = $this->makeUser('master', $this->vesselId);
        $stock = new StockService;

        $stock->setStock($this->vesselId, $item->id, ['stock_qty' => 2, 'min_qty' => 5], $officer->id);

        $response = $this->actingAs($officer)
            ->getJson('/inventory/stock/data?vessel_id='.$this->vesselId.'&category_id='.$category->id.'&draw=1&start=0&length=25')
            ->assertOk();

        $row = collect($response->json('data'))->firstWhere('name', $item->name);

        $this->assertTrue($row['low']);
    }
}
