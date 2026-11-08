<?php

namespace Tests\Feature\Inventory;

use App\Services\StockService;
use Tests\Concerns\BuildsRequisitionSchema;
use Tests\TestCase;

/**
 * The item stock card (/inventory/stock/{item}): access follows the same
 * InventoryGate rule as Current Stock, the movements list reads straight
 * off the stock_movements ledger with each row's own balance_after (never
 * recomputed), and each one links back to its source.
 */
class StockItemCardTest extends TestCase
{
    use BuildsRequisitionSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRequisitionSchema();
    }

    public function test_any_ship_role_can_view_their_own_vessels_item_card(): void
    {
        $category = $this->makeCategory();
        $item = $this->makeItem($category->id);
        $officer = $this->makeUser('chief-officer', $this->vesselId);

        $this->actingAs($officer)->get('/inventory/stock/'.$item->id)->assertOk();
    }

    public function test_a_fleet_wide_role_must_choose_a_vessel(): void
    {
        $category = $this->makeCategory();
        $item = $this->makeItem($category->id);
        $gm = $this->makeUser('gm-srd');

        $this->actingAs($gm)->get('/inventory/stock/'.$item->id)->assertStatus(422);
    }

    public function test_a_role_outside_the_gate_cannot_view_the_card(): void
    {
        $category = $this->makeCategory();
        $item = $this->makeItem($category->id);
        $techSuper = $this->makeUser('technical-superintendent');

        $this->actingAs($techSuper)->get('/inventory/stock/'.$item->id)->assertStatus(403);
    }

    public function test_movements_show_in_order_with_running_balance_and_link_to_their_source(): void
    {
        $category = $this->makeCategory();
        $item = $this->makeItem($category->id);
        $order = $this->makeOrder(['rcv_date' => '2026-01-05']);
        $line = $this->makeOrderItem($order->id, $item->id, 10, ['rcv_item_qty' => 10]);
        $officer = $this->makeUser('master', $this->vesselId);
        $stock = new StockService;

        $stock->addFromReceipt($this->vesselId, $item->id, 10, $officer->id, \Carbon\Carbon::parse('2026-01-05'), $order->id, $line->id);
        $stock->consume($this->vesselId, $item->id, 3, 'used', 'Testing consumption', '2026-01-08', $officer->id);

        $response = $this->actingAs($officer)->get('/inventory/stock/'.$item->id.'?from=2020-01-01&to=2026-12-31');

        $response->assertOk();
        $response->assertSeeText($order->req_no);
        $response->assertSeeText('10'); // the receipt's balance_after
        $response->assertSeeText('7');  // the consumption's balance_after
        $response->assertSeeText('Testing consumption');
    }

    public function test_date_range_excludes_movements_outside_it(): void
    {
        $category = $this->makeCategory();
        $item = $this->makeItem($category->id);
        $officer = $this->makeUser('master', $this->vesselId);
        $stock = new StockService;

        $stock->setStock($this->vesselId, $item->id, ['stock_qty' => 10], $officer->id);
        $stock->consume($this->vesselId, $item->id, 1, 'used', 'outside the window', '2025-01-01', $officer->id);
        $stock->consume($this->vesselId, $item->id, 1, 'used', 'inside the window', '2026-06-01', $officer->id);

        $response = $this->actingAs($officer)
            ->get('/inventory/stock/'.$item->id.'?from=2026-01-01&to=2026-12-31');

        $response->assertOk();
        $response->assertSeeText('inside the window');
        $response->assertDontSeeText('outside the window');
    }
}
