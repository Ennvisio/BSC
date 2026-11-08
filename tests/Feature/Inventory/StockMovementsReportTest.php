<?php

namespace Tests\Feature\Inventory;

use App\Services\StockService;
use Tests\Concerns\BuildsRequisitionSchema;
use Tests\TestCase;

/**
 * /inventory/movements: fleet-level filters over stock_movements (unlike
 * Current Stock/the item card, a fleet-wide viewer may leave vessel
 * unpicked and see the whole fleet - App\Support\InventoryGate::
 * resolveOptionalVesselId()), plus the requisition-to-consumption trace.
 */
class StockMovementsReportTest extends TestCase
{
    use BuildsRequisitionSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRequisitionSchema();
    }

    public function test_any_ship_role_can_view_their_own_vessels_movements(): void
    {
        $officer = $this->makeUser('chief-officer', $this->vesselId);

        $this->actingAs($officer)->get('/inventory/movements')->assertOk();
    }

    public function test_a_fleet_wide_role_can_view_the_whole_fleet_with_no_vessel_chosen(): void
    {
        $gm = $this->makeUser('gm-srd');

        $this->actingAs($gm)->getJson('/inventory/movements/data?draw=1&start=0&length=25')->assertOk();
    }

    public function test_a_role_outside_the_gate_cannot_view_movements(): void
    {
        $techSuper = $this->makeUser('technical-superintendent');

        $this->actingAs($techSuper)->get('/inventory/movements')->assertStatus(403);
    }

    public function test_type_filter_narrows_to_one_consumption_type(): void
    {
        $category = $this->makeCategory();
        $item = $this->makeItem($category->id);
        $officer = $this->makeUser('master', $this->vesselId);
        $stock = new StockService;

        $stock->setStock($this->vesselId, $item->id, ['stock_qty' => 10], $officer->id);
        $stock->consume($this->vesselId, $item->id, 2, 'used', 'normal use', '2026-01-10', $officer->id);
        $stock->consume($this->vesselId, $item->id, 1, 'damaged', 'dropped overboard', '2026-01-11', $officer->id);

        $response = $this->actingAs($officer)
            ->getJson('/inventory/movements/data?type=damaged&from=2020-01-01&to=2026-12-31&draw=1&start=0&length=25')
            ->assertOk();

        $rows = $response->json('data');
        $this->assertCount(1, $rows);
        $this->assertSame(-1, $rows[0]['qty']);
    }

    public function test_summary_aggregates_received_vs_consumed_and_top_consumed(): void
    {
        $category = $this->makeCategory();
        $itemA = $this->makeItem($category->id);
        $itemB = $this->makeItem($category->id);
        $officer = $this->makeUser('master', $this->vesselId);
        $stock = new StockService;

        $stock->addFromReceipt($this->vesselId, $itemA->id, 20, $officer->id, \Carbon\Carbon::parse('2026-03-05'));
        $stock->consume($this->vesselId, $itemA->id, 5, 'used', 'a', '2026-03-10', $officer->id);
        $stock->setStock($this->vesselId, $itemB->id, ['stock_qty' => 10], $officer->id);
        $stock->consume($this->vesselId, $itemB->id, 8, 'used', 'b', '2026-03-12', $officer->id);

        $response = $this->actingAs($officer)
            ->getJson('/inventory/movements/summary?from=2026-01-01&to=2026-12-31')
            ->assertOk();

        $byMonth = collect($response->json('by_month'))->firstWhere('month', '2026-03');
        $this->assertNotNull($byMonth);
        $this->assertSame(20, $byMonth['received']);
        $this->assertSame(13, $byMonth['consumed']);

        $top = $response->json('top_consumed');
        $this->assertSame($itemB->name, $top[0]['name']);
        $this->assertSame(8, $top[0]['qty']);
    }

    public function test_requisition_trace_shows_received_vs_consumed_per_line(): void
    {
        $category = $this->makeCategory();
        $item = $this->makeItem($category->id);
        $order = $this->makeOrder(['rcv_date' => '2026-01-05']);
        $this->makeOrderItem($order->id, $item->id, 10, ['rcv_item_qty' => 10]);
        $officer = $this->makeUser('master', $this->vesselId);
        $stock = new StockService;

        $stock->addFromReceipt($this->vesselId, $item->id, 10, $officer->id, \Carbon\Carbon::parse('2026-01-05'), $order->id);
        $stock->consume($this->vesselId, $item->id, 4, 'used', 'for the trace test', '2026-01-08', $officer->id, ['order_id' => $order->id]);

        $response = $this->actingAs($officer)
            ->getJson('/inventory/movements/requisition-trace?order_id='.$order->id)
            ->assertOk();

        $line = $response->json('lines.0');
        $this->assertSame(10, $line['required_qty']);
        $this->assertSame(10, $line['received_qty']);
        $this->assertSame(4, $line['consumed_qty']);
        $this->assertSame(6, $line['remaining_qty']);
    }

    public function test_ship_user_cannot_trace_another_vessels_requisition(): void
    {
        $otherVesselId = $this->makeVessel('MT OTHER VESSEL')->id;
        $category = $this->makeCategory();
        $item = $this->makeItem($category->id);
        $order = $this->makeOrder(['vessel_id' => $otherVesselId]);
        $this->makeOrderItem($order->id, $item->id, 5);

        $officer = $this->makeUser('chief-officer', $this->vesselId);

        $this->actingAs($officer)
            ->getJson('/inventory/movements/requisition-trace?order_id='.$order->id)
            ->assertStatus(403);
    }

    public function test_requisition_search_matches_by_req_no(): void
    {
        $order = $this->makeOrder(['req_no' => 'REQ/SEARCHME/01']);
        $officer = $this->makeUser('chief-officer', $this->vesselId);

        $response = $this->actingAs($officer)
            ->getJson('/inventory/movements/requisition-search?q=SEARCHME')
            ->assertOk();

        $this->assertCount(1, $response->json());
        $this->assertSame($order->req_no, $response->json('0.req_no'));
    }
}
