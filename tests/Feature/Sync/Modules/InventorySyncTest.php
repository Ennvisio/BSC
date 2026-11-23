<?php

namespace Tests\Feature\Sync\Modules;

use App\Services\CatalogService;
use App\Services\StockService;
use App\StockConsumption;
use App\Sync\Models\SyncConflict;
use Illuminate\Support\Facades\DB;

/**
 * Inventory is the vessel's. Stock moves on board only through StockService
 * (a balance and a ledger line together); only the ledger lines travel, and
 * the office's balance is worked out from them. Balances are never copied.
 */
class InventorySyncTest extends BusinessSyncTestCase
{
    private function stockOn(string $server, string $article): int
    {
        return $this->on($server, fn () => (int) DB::table('vessel_items')->where('vessel_id', 7)->where('item_id', $this->itemId($article))->value('stock_qty'));
    }

    public function test_consumption_and_a_masters_correction_on_board_reach_the_office_as_ledger_lines(): void
    {
        $this->onShip(function () {
            $stock = app(StockService::class);
            $stock->consume(7, $this->itemId('ME-100'), 3, StockConsumption::TYPE_USED, 'Overhaul No. 2 unit', '2026-11-18', $this->userId('ce@mvtest.test'));
            $stock->setStock(7, $this->itemId('ME-100'), ['stock_qty' => 6], $this->userId('master@mvtest.test'));
        });
        $this->assertSame(6, $this->stockOn('ship', 'ME-100'));

        $this->assertCycleOk();

        $this->assertSame(6, $this->stockOn('cloud', 'ME-100'), '10 - 3 - 1');
        $this->onCloud(function () {
            $lines = DB::table('stock_movements')->where('vessel_id', 7)->orderBy('id')->get();
            $this->assertSame([['consumption', -3, 7], ['adjustment', -1, 6]], $lines->map(fn ($m) => [$m->type, (int) $m->qty, (int) $m->balance_after])->all());
            $consumption = DB::table('stock_consumptions')->sole();
            $onBoard = $this->onShip(fn () => DB::table('stock_consumptions')->sole());
            $this->assertSame(['Overhaul No. 2 unit', $onBoard->consumed_on, $onBoard->created_at, $this->userId('ce@mvtest.test')], [$consumption->purpose, $consumption->consumed_on, $consumption->created_at, (int) $consumption->recorded_by]);
            $this->assertStringStartsWith('2026-11-18', $consumption->consumed_on);
            $this->assertSame((int) $consumption->id, (int) $lines[0]->stock_consumption_id, 'the ledger line points at the office\'s copy of the consumption');
            $this->assertSame($this->userId('master@mvtest.test'), (int) DB::table('vessel_items')->where('vessel_id', 7)->where('item_id', $this->itemId('ME-100'))->value('stock_updated_by'));
            $this->assertSame(0, SyncConflict::query()->count());
        });

        $pushed = $this->onCloud(fn () => DB::table('sync_inbox')->where('table_name', 'vessel_items')->pluck('payload')->all());
        foreach ($pushed as $payload) {
            $this->assertArrayNotHasKey('stock_qty', json_decode($payload, true), 'a balance never travels');
        }
    }

    public function test_a_ledger_line_sent_twice_moves_the_office_balance_once(): void
    {
        $this->onShip(fn () => app(StockService::class)->consume(7, $this->itemId('ME-100'), 2, StockConsumption::TYPE_USED, 'Test', '2026-11-18', $this->userId('ce@mvtest.test')));
        $this->network = fn ($path, $n) => $path === '/api/sync/v1/push' && $n === 1 ? 'drop-response' : 'up';

        $this->assertFalse($this->syncCycle()['ok']);
        $this->assertCycleOk();
        $this->assertCycleOk();

        $this->assertSame(8, $this->stockOn('cloud', 'ME-100'));
        $this->assertSame(1, $this->onCloud(fn () => DB::table('stock_movements')->count()));
    }

    public function test_a_movement_that_does_not_add_up_is_flagged_and_the_vessels_figure_adopted(): void
    {
        // Someone changed the office copy's balance directly.
        $this->onCloud(fn () => DB::table('vessel_items')->where('vessel_id', 7)->update(['stock_qty' => 50]));
        $this->onShip(fn () => app(StockService::class)->consume(7, $this->itemId('ME-100'), 4, StockConsumption::TYPE_USED, 'Test', '2026-11-18', $this->userId('ce@mvtest.test')));

        $this->assertCycleOk();

        $this->assertSame(6, $this->stockOn('cloud', 'ME-100'), 'the ship counts what is on board');
        $conflict = $this->onCloud(fn () => SyncConflict::query()->sole());
        $this->assertSame(['stock_mismatch', 'needs_review', 'took_remote'], [$conflict->kind, $conflict->status, $conflict->resolution]);
        $this->assertSame(50, $conflict->local_values['stock_qty']);
        $this->assertSame(6, $conflict->remote_values['balance_after']);
    }

    public function test_with_keep_the_office_figure_stays_and_is_still_flagged(): void
    {
        config(['sync.inventory.on_mismatch' => 'keep']);
        $this->onCloud(fn () => DB::table('vessel_items')->where('vessel_id', 7)->update(['stock_qty' => 50]));
        $this->onShip(fn () => app(StockService::class)->consume(7, $this->itemId('ME-100'), 4, StockConsumption::TYPE_USED, 'Test', '2026-11-18', $this->userId('ce@mvtest.test')));

        $this->assertCycleOk();

        $this->assertSame(50, $this->stockOn('cloud', 'ME-100'));
        $this->assertSame('kept_local', $this->onCloud(fn () => SyncConflict::query()->sole()->resolution));
    }

    public function test_the_same_item_linked_to_the_vessel_on_both_sides_becomes_one_link(): void
    {
        // The office links ME-200 to MV TEST; meanwhile the crew receive one
        // into stock, which links it on board too.
        $this->onCloud(fn () => app(CatalogService::class)->linkToVessel($this->itemId('ME-200'), 7));
        $this->onShip(fn () => app(StockService::class)->setStock(7, $this->itemId('ME-200'), ['stock_qty' => 5, 'min_qty' => 2], $this->userId('master@mvtest.test')));

        $this->assertCycleOk();
        $this->assertCycleOk();

        foreach (['cloud', 'ship'] as $server) {
            $this->on($server, function () use ($server) {
                $links = DB::table('vessel_items')->where('vessel_id', 7)->where('item_id', $this->itemId('ME-200'))->get();
                $this->assertCount(1, $links, "{$server}: one link");
                $this->assertSame([5, 2], [(int) $links[0]->stock_qty, (int) $links[0]->min_qty], $server);
            });
        }
        $this->onCloud(fn () => $this->assertSame('merged', SyncConflict::query()->where('kind', 'duplicate_key')->sole()->resolution));
        $this->assertSame([], $this->inboxProblems('cloud'));
        $this->assertSame([], $this->inboxProblems('ship'));

        // Later movements still find the merged link.
        $this->onShip(fn () => app(StockService::class)->consume(7, $this->itemId('ME-200'), 1, StockConsumption::TYPE_USED, 'Test', '2026-11-19', $this->userId('ce@mvtest.test')));
        $this->assertCycleOk();
        $this->assertSame(4, $this->stockOn('cloud', 'ME-200'));
    }

    public function test_the_same_catalog_item_created_on_both_sides_is_merged_and_requisitions_use_it(): void
    {
        $this->onCloud(fn () => $this->addItem('ME-300', 'Exhaust valve'));
        $shipItem = $this->onShip(fn () => $this->addItem('ME-300', 'EXHAUST VALVE'));   // same key: case does not matter
        $orderId = $this->draftOnBoard(['ME-300' => 2]);
        $this->submitOnBoard($orderId);

        $this->assertCycleOk();
        $this->assertCycleOk();

        $this->assertSame(1, $this->onCloud(fn () => DB::table('items')->where('article_number', 'ME-300')->count()));
        $this->assertSame(1, $this->onShip(fn () => DB::table('items')->where('article_number', 'ME-300')->count()));
        $uuid = $this->onShip(fn () => $this->uuidFor('orders', $orderId));
        $this->onCloud(function () use ($uuid) {
            $line = DB::table('order_items')->where('order_id', $this->idOf('orders', $uuid))->sole();
            $this->assertSame($this->itemId('ME-300'), (int) $line->item_id, 'the line points at the office\'s item');
        });
        $this->assertNotNull($shipItem);
    }

    public function test_a_ledger_line_is_never_rewritten_by_sync(): void
    {
        $this->onShip(fn () => app(StockService::class)->consume(7, $this->itemId('ME-100'), 2, StockConsumption::TYPE_USED, 'Test', '2026-11-18', $this->userId('ce@mvtest.test')));
        $this->assertCycleOk();

        $this->onShip(fn () => DB::table('stock_movements')->update(['qty' => -9, 'balance_after' => 1, 'updated_at' => now()->addHour()]));
        $result = $this->syncCycle();

        $this->assertSame(1, $result['summary']['pushed']['rejected']);
        $this->assertSame(-2, $this->onCloud(fn () => (int) DB::table('stock_movements')->value('qty')));
        $this->assertSame(8, $this->stockOn('cloud', 'ME-100'));
    }
}
