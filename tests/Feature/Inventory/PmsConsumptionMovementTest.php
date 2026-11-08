<?php

namespace Tests\Feature\Inventory;

use App\PmsJob;
use App\Services\PmsJobScheduler;
use App\Services\PmsPartsService;
use App\Services\StockService;
use App\StockConsumption;
use App\StockMovement;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsPmsSchema;
use Tests\TestCase;

/**
 * A PMS job completed with used parts goes through the exact same
 * StockService::consume() every other consumption does (see
 * PmsPartsService::completeWithUsedParts()) - this locks in that it writes
 * the ledger row too, linked back to the same stock_consumptions row
 * PmsUsedPart already links to.
 */
class PmsConsumptionMovementTest extends TestCase
{
    use BuildsPmsSchema;

    private PmsJobScheduler $scheduler;

    private PmsPartsService $parts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildPmsSchema();
        $this->scheduler = new PmsJobScheduler;
        $this->parts = new PmsPartsService(new StockService, $this->scheduler);
        Carbon::setTestNow('2026-01-10');
        CarbonImmutable::setTestNow('2026-01-10');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function jobWithParts(array $parts, array $definition = []): PmsJob
    {
        $def = $this->makeDefinition($this->makeComponent(['name' => 'FUEL PUMP']), $definition + ['name' => 'Overhaul pump', 'start_date' => '2026-01-10'], [[1, 'Month']]);
        $this->parts->syncTemplate($def, $parts);

        return $this->scheduler->definitionSaved($def->fresh());
    }

    public function test_completing_a_pms_job_with_used_parts_writes_one_consumption_movement(): void
    {
        $item = $this->makeItem('Gasket', 'SPR', 10);
        $job = $this->jobWithParts([]);

        $this->parts->completeWithUsedParts(
            $job,
            $this->completionData(['completed_at' => '2026-01-10']),
            [['item_id' => $item->id, 'quantity' => 3]],
            $this->userId
        );

        $movement = StockMovement::sole();
        $this->assertSame(StockMovement::TYPE_CONSUMPTION, $movement->type);
        $this->assertSame(-3, $movement->qty);
        $this->assertSame(StockConsumption::sole()->id, $movement->stock_consumption_id);
        $this->assertSame((int) DB::table('vessel_items')->where('item_id', $item->id)->value('stock_qty'), $movement->balance_after);
        $this->assertSame(7, $movement->balance_after);
    }
}
