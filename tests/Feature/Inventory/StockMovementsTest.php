<?php

namespace Tests\Feature\Inventory;

use App\Services\StockService;
use App\StockMovement;
use App\VesselItem;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsRequisitionSchema;
use Tests\TestCase;

/**
 * Every StockService write - receipt, consumption, manual set, bulk upload -
 * writes exactly one stock_movements row whose balance_after always matches
 * the live vessel_items.stock_qty it just produced. See
 * App\Services\StockService's class doc and the create_stock_movements_table
 * migration for what each type means.
 */
class StockMovementsTest extends TestCase
{
    use BuildsRequisitionSchema;

    private StockService $stock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRequisitionSchema();
        $this->stock = new StockService;
    }

    private function liveStockQty(int $itemId): int
    {
        return (int) VesselItem::where('vessel_id', $this->vesselId)->where('item_id', $itemId)->value('stock_qty');
    }

    public function test_a_receipt_writes_exactly_one_movement_and_balance_matches(): void
    {
        $item = $this->makeItem($this->makeCategory()->id);
        $order = $this->makeOrder(['rcv_date' => now()]);
        $line = $this->makeOrderItem($order->id, $item->id, 5, ['rcv_item_qty' => 5]);
        $user = $this->makeUser('master', $this->vesselId);

        $this->stock->addFromReceipt($this->vesselId, $item->id, 5, $user->id, now(), $order->id, $line->id);

        $movement = StockMovement::sole();
        $this->assertSame(StockMovement::TYPE_RECEIPT, $movement->type);
        $this->assertSame(5, $movement->qty);
        $this->assertSame($order->id, $movement->order_id);
        $this->assertSame($line->id, $movement->order_item_id);
        $this->assertSame($this->liveStockQty($item->id), $movement->balance_after);
        $this->assertSame(5, $movement->balance_after);
    }

    public function test_a_consumption_writes_exactly_one_movement_and_balance_matches(): void
    {
        $item = $this->makeItem($this->makeCategory()->id);
        DB::table('vessel_items')->insert(['vessel_id' => $this->vesselId, 'item_id' => $item->id, 'stock_qty' => 10]);
        $user = $this->makeUser('chief-officer', $this->vesselId);

        $this->stock->consume($this->vesselId, $item->id, 3, 'used', 'Testing', '2026-01-10', $user->id);

        $movement = StockMovement::sole();
        $this->assertSame(StockMovement::TYPE_CONSUMPTION, $movement->type);
        $this->assertSame(-3, $movement->qty);
        $this->assertNotNull($movement->stock_consumption_id);
        $this->assertSame($this->liveStockQty($item->id), $movement->balance_after);
        $this->assertSame(7, $movement->balance_after);
    }

    public function test_a_manual_set_writes_exactly_one_movement_and_balance_matches(): void
    {
        $item = $this->makeItem($this->makeCategory()->id);
        $user = $this->makeUser('master', $this->vesselId);

        $this->stock->setStock($this->vesselId, $item->id, ['stock_qty' => 12], $user->id);

        $movement = StockMovement::sole();
        $this->assertSame(StockMovement::TYPE_ADJUSTMENT, $movement->type);
        $this->assertSame(12, $movement->qty);
        $this->assertSame($this->liveStockQty($item->id), $movement->balance_after);
        $this->assertSame(12, $movement->balance_after);
    }

    public function test_setting_opening_stock_writes_its_own_movement(): void
    {
        $item = $this->makeItem($this->makeCategory()->id);
        $user = $this->makeUser('master', $this->vesselId);

        $this->stock->setStock($this->vesselId, $item->id, ['opening_stock' => 8], $user->id);

        $movement = StockMovement::sole();
        $this->assertSame(StockMovement::TYPE_OPENING, $movement->type);
        $this->assertSame(8, $movement->qty);
        // opening_stock never moves the ROB balance on its own.
        $this->assertSame(0, $this->liveStockQty($item->id));
    }

    public function test_restating_the_same_figure_writes_nothing(): void
    {
        $item = $this->makeItem($this->makeCategory()->id);
        $user = $this->makeUser('master', $this->vesselId);

        $this->stock->setStock($this->vesselId, $item->id, ['stock_qty' => 12], $user->id);
        $this->stock->setStock($this->vesselId, $item->id, ['stock_qty' => 12], $user->id);

        $this->assertSame(1, StockMovement::where('item_id', $item->id)->count());
    }

    public function test_a_bulk_upload_writes_one_adjustment_movement_per_changed_item(): void
    {
        $item = $this->makeItem($this->makeCategory()->id);

        $this->stock->bulkSet($this->vesselId, [['item_id' => $item->id, 'stock_qty' => 20]]);

        $movement = StockMovement::sole();
        $this->assertSame(StockMovement::TYPE_ADJUSTMENT, $movement->type);
        $this->assertSame(20, $movement->qty);
        $this->assertSame($this->liveStockQty($item->id), $movement->balance_after);
        $this->assertSame(20, $movement->balance_after);
    }

    public function test_a_bulk_upload_restating_the_same_figure_writes_nothing(): void
    {
        $item = $this->makeItem($this->makeCategory()->id);
        $this->stock->bulkSet($this->vesselId, [['item_id' => $item->id, 'stock_qty' => 20]]);

        $this->stock->bulkSet($this->vesselId, [['item_id' => $item->id, 'stock_qty' => 20]]);

        $this->assertSame(1, StockMovement::where('item_id', $item->id)->count());
    }

    public function test_backfill_twice_gives_the_same_result(): void
    {
        $item = $this->makeItem($this->makeCategory()->id);
        DB::table('vessel_items')->insert([
            'vessel_id' => $this->vesselId, 'item_id' => $item->id, 'stock_qty' => 5, 'opening_stock' => 5,
        ]);

        $first = $this->stock->backfillMovementsForVessel($this->vesselId);
        $countAfterFirst = StockMovement::count();

        $this->assertGreaterThan(0, array_sum($first));
        $this->assertSame(5, $this->liveStockQty($item->id));

        $second = $this->stock->backfillMovementsForVessel($this->vesselId);

        $this->assertSame(0, array_sum($second), 'a second run should find nothing left to add');
        $this->assertSame($countAfterFirst, StockMovement::count());
    }

    public function test_backfill_rebuilds_a_receipt_and_a_consumption_in_order_with_a_reconciling_adjustment(): void
    {
        $item = $this->makeItem($this->makeCategory()->id);
        $order = $this->makeOrder(['rcv_date' => '2026-01-05']);
        $line = $this->makeOrderItem($order->id, $item->id, 10, ['rcv_item_qty' => 10]);
        $user = $this->makeUser('chief-officer', $this->vesselId);

        DB::table('stock_consumptions')->insert([
            'vessel_id' => $this->vesselId, 'item_id' => $item->id, 'consumption_type' => 'used',
            'qty' => 4, 'consumed_on' => '2026-01-08', 'purpose' => 'Testing', 'recorded_by' => $user->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // The real figure on board today, exactly what a receipt (+10) and a
        // consumption (-4) would have produced if the ledger had existed
        // from the start - no drift, so no reconciliation row either.
        DB::table('vessel_items')->insert(['vessel_id' => $this->vesselId, 'item_id' => $item->id, 'stock_qty' => 6]);

        $counts = $this->stock->backfillMovementsForVessel($this->vesselId);

        $this->assertSame(1, $counts['receipt']);
        $this->assertSame(1, $counts['consumption']);
        $this->assertSame(0, $counts['adjustment']);

        $rows = StockMovement::where('item_id', $item->id)->orderBy('moved_on')->get();
        $this->assertSame(StockMovement::TYPE_RECEIPT, $rows[0]->type);
        $this->assertSame(10, $rows[0]->qty);
        $this->assertSame(10, $rows[0]->balance_after);
        $this->assertSame($line->id, $rows[0]->order_item_id);
        $this->assertSame(StockMovement::TYPE_CONSUMPTION, $rows[1]->type);
        $this->assertSame(-4, $rows[1]->qty);
        $this->assertSame(6, $rows[1]->balance_after);
    }
}
