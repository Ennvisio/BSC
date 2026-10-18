<?php

namespace Tests\Feature\Pms;

use App\Manufacturer;
use App\Order;
use App\PmsJob;
use App\PmsRequiredPart;
use App\PmsUsedPart;
use App\Services\PmsJobScheduler;
use App\Services\PmsPartsService;
use App\Services\StockService;
use App\StockConsumption;
use App\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsPmsSchema;
use Tests\TestCase;

class PmsPartsTest extends TestCase
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

    private function user(): User
    {
        return User::find($this->userId);
    }

    /** A recurring job with these template parts, already generated. */
    private function jobWithParts(array $parts, array $definition = []): PmsJob
    {
        $def = $this->makeDefinition($this->makeComponent(['name' => 'FUEL PUMP']), $definition + ['name' => 'Overhaul pump', 'start_date' => '2026-01-10'], [[1, 'Month']]);
        $this->parts->syncTemplate($def, $parts);

        return $this->scheduler->definitionSaved($def->fresh());
    }

    private function manual(string $name, int $qty = 1): array
    {
        return ['part_name' => $name, 'quantity' => $qty, 'unit' => 'pcs'];
    }

    // ---------------------------------------------------------------- templates and copies

    public function test_a_catalogue_pick_fills_the_part_from_the_item(): void
    {
        $maker = Manufacturer::create(['name' => 'Sperre']);
        $item = $this->makeItem('Piston ring', 'SPR', null, ['part_number' => 'P-9', 'drawing_number' => 'D-3', 'manufacturer' => 'sperre', 'unit' => 'set']);

        $attributes = $this->parts->attributes(['item_id' => $item->id, 'part_name' => 'typed over', 'quantity' => 2, 'unit' => 'x', 'est_price' => '12.5']);

        $this->assertSame('Piston ring', $attributes['part_name']);
        $this->assertSame($item->article_number, $attributes['article_no']);
        $this->assertSame('P-9', $attributes['part_no']);
        $this->assertSame('D-3', $attributes['drawing_no']);
        $this->assertSame($maker->id, $attributes['manufacturer_id']);
        $this->assertSame('set', $attributes['unit']);
        $this->assertSame(2, $attributes['quantity']);
        $this->assertSame(12.5, $attributes['est_price'], 'the estimate stays the officer\'s own');
    }

    public function test_a_generated_job_gets_its_own_copy_of_the_definition_parts(): void
    {
        $item = $this->makeItem('Gasket');
        $job = $this->jobWithParts([['item_id' => $item->id, 'quantity' => 4], $this->manual('Special tool')]);

        $copies = PmsRequiredPart::where('job_id', $job->id)->orderBy('id')->get();
        $this->assertSame(['Gasket', 'Special tool'], $copies->pluck('part_name')->all());
        $this->assertNull($copies[0]->job_definition_id, 'a job part belongs to the job only');
        $this->assertSame(4, $copies[0]->quantity);

        // Editing the job's copy leaves the definition's template alone.
        $copies[0]->update(['quantity' => 9]);
        $template = PmsRequiredPart::where('job_definition_id', $job->job_definition_id)->orderBy('id')->get();
        $this->assertSame(4, $template[0]->quantity);
        $this->assertNull($template[0]->job_id);
    }

    public function test_the_next_job_copies_the_template_afresh(): void
    {
        $item = $this->makeItem('Gasket');
        $job = $this->jobWithParts([['item_id' => $item->id, 'quantity' => 4]]);
        PmsRequiredPart::where('job_id', $job->id)->update(['quantity' => 9, 'order_id' => $this->requisitionFor($job)->id]);

        Carbon::setTestNow('2026-02-10');
        CarbonImmutable::setTestNow('2026-02-10');
        $this->scheduler->complete($job, $this->completionData(['completed_at' => '2026-02-10']), $this->userId);
        $next = PmsJob::where('job_definition_id', $job->job_definition_id)->where('id', '!=', $job->id)->sole();

        $copy = PmsRequiredPart::where('job_id', $next->id)->sole();
        $this->assertSame(4, $copy->quantity, 'the template quantity, not the previous job\'s edit');
        $this->assertNull($copy->order_id, 'a new job needs the part again');
    }

    private function requisitionFor(PmsJob $job): Order
    {
        $order = new Order;
        $order->vessel_id = $this->vesselId;
        $order->req_date = now();
        $order->port_name = '';
        $order->created_by = $this->userId;
        $order->created_by_role = 'chief-officer';
        $order->save();

        return $order;
    }

    // ---------------------------------------------------------------- requisition

    public function test_creating_a_requisition_makes_a_prefilled_draft_and_links_the_parts(): void
    {
        $gasket = $this->makeItem('Gasket', 'SPR', 5);
        $seal = $this->makeItem('Seal', 'SPR');
        $job = $this->jobWithParts([['item_id' => $gasket->id, 'quantity' => 4], ['item_id' => $seal->id, 'quantity' => 2], ['item_id' => $seal->id, 'quantity' => 1]]);
        $ids = PmsRequiredPart::where('job_id', $job->id)->pluck('id')->all();
        DB::table('vessel_items')->where('item_id', $gasket->id)->update(['opening_stock' => 5]);

        $order = $this->parts->createRequisition($job, $ids, $this->user());

        $this->assertSame('draft', $order->status);
        $this->assertSame($this->vesselId, (int) $order->vessel_id);
        $this->assertSame("Requisition for job 'Overhaul pump'", $order->title);
        $this->assertSame('FUEL PUMP', $order->machinery_name);
        $this->assertSame($gasket->category_id, (int) $order->category_id);
        $lines = $order->orderItems()->orderBy('item_id')->get();
        $this->assertSame([$gasket->id => 4, $seal->id => 3], $lines->pluck('item_qty', 'item_id')->map(fn ($q) => (int) $q)->all(), 'same item adds up');
        $this->assertSame(5, (int) $lines->firstWhere('item_id', $gasket->id)->opening_stock, 'stock snapshot taken like the wizard does');
        $this->assertSame([$order->id], PmsRequiredPart::where('job_id', $job->id)->pluck('order_id')->unique()->all());
    }

    public function test_a_part_already_on_a_requisition_cannot_be_requisitioned_again(): void
    {
        $item = $this->makeItem('Gasket');
        $job = $this->jobWithParts([['item_id' => $item->id, 'quantity' => 4]]);
        $ids = PmsRequiredPart::where('job_id', $job->id)->pluck('id')->all();
        $this->parts->createRequisition($job, $ids, $this->user());

        try {
            $this->parts->createRequisition($job, $ids, $this->user());
            $this->fail('Expected the second requisition to be refused.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Already on a requisition', $e->errors()['parts'][0]);
        }
        $this->assertSame(1, Order::count());
    }

    public function test_custom_parts_and_mixed_categories_are_blocked_with_a_message(): void
    {
        $spare = $this->makeItem('Gasket', 'SPR');
        $paint = $this->makeItem('Primer', 'PNT');
        $job = $this->jobWithParts([['item_id' => $spare->id, 'quantity' => 1], ['item_id' => $paint->id, 'quantity' => 1], $this->manual('Odd bolt')]);
        $byName = PmsRequiredPart::where('job_id', $job->id)->pluck('id', 'part_name');

        try {
            $this->parts->createRequisition($job, [$byName['Gasket'], $byName['Odd bolt']], $this->user());
            $this->fail('A custom part has no item to requisition.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Odd bolt', $e->errors()['parts'][0]);
            $this->assertStringContainsString('catalogue', $e->errors()['parts'][0]);
        }

        try {
            $this->parts->createRequisition($job, [$byName['Gasket'], $byName['Primer']], $this->user());
            $this->fail('One requisition holds one category.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('one category', $e->errors()['parts'][0]);
        }

        $this->assertSame(0, Order::count(), 'nothing was created by the refused attempts');
        $this->assertSame(0, PmsRequiredPart::whereNotNull('order_id')->count());
    }

    // ---------------------------------------------------------------- released links

    /** A requisition draft holding one part of $item, plus the part and its job. */
    private function requisitioned(string $itemName = 'Gasket'): array
    {
        $item = $this->makeItem($itemName);
        $job = $this->jobWithParts([['item_id' => $item->id, 'quantity' => 4]]);
        $part = PmsRequiredPart::where('job_id', $job->id)->sole();
        $order = $this->parts->createRequisition($job, [$part->id], $this->user());

        return [$job, $part->fresh(), $order, $item];
    }

    public function test_a_link_to_a_requisition_that_still_holds_the_part_is_kept(): void
    {
        [$job, $part, $order] = $this->requisitioned();

        $this->assertSame(0, $this->parts->releaseStaleLinks([$job->id]));
        $this->assertSame($order->id, (int) $part->fresh()->order_id);
    }

    public function test_a_part_is_released_when_its_item_is_taken_off_the_requisition(): void
    {
        [$job, $part, $order, $item] = $this->requisitioned();

        // The officer drops the item from the draft in the wizard.
        $order->orderItems()->where('item_id', $item->id)->delete();

        $this->assertSame(1, $this->parts->releaseStaleLinks([$job->id]));
        $this->assertNull($part->fresh()->order_id);

        // ...and it can be requisitioned again, on a new draft.
        $again = $this->parts->createRequisition($job, [$part->id], $this->user());
        $this->assertNotSame($order->id, $again->id);
        $this->assertSame($again->id, (int) $part->fresh()->order_id);
    }

    public function test_a_part_is_released_when_the_draft_is_thrown_away(): void
    {
        [$job, $part, $order] = $this->requisitioned();

        DB::table('order_items')->where('order_id', $order->id)->delete();
        DB::table('orders')->where('id', $order->id)->delete();
        $this->parts->releaseStaleLinks([$job->id]);

        $this->assertNull($part->fresh()->order_id);
    }

    public function test_a_part_is_released_when_its_requisition_is_rejected(): void
    {
        [$job, $part, $order] = $this->requisitioned();
        $order->status = 'rejected';
        $order->save();

        $this->assertSame(1, $this->parts->releaseStaleLinks([$job->id]));
        $this->assertNull($part->fresh()->order_id);
        $this->assertSame(1, $order->orderItems()->count(), 'the rejected requisition itself is left as it was');

        $this->parts->createRequisition($job, [$part->id], $this->user()); // no "already on a requisition"
        $this->assertSame(2, Order::count());
    }

    public function test_a_submitted_requisition_that_still_has_the_line_keeps_its_parts(): void
    {
        [$job, $part, $order] = $this->requisitioned();
        $order->status = 'approved by chief-officer';
        $order->req_no = 'REQ/SPR/01/2026';
        $order->save();

        $this->assertSame(0, $this->parts->releaseStaleLinks([$job->id]));
        $this->assertSame($order->id, (int) $part->fresh()->order_id);
    }

    public function test_removing_a_part_from_a_draft_deletes_its_line_and_frees_it(): void
    {
        [$job, $part, $order] = $this->requisitioned();

        $this->parts->removeFromRequisition($part);

        $this->assertNull($part->fresh()->order_id);
        $this->assertSame(0, $order->orderItems()->count(), 'the item\'s line is gone from the draft');
        $this->assertSame(1, Order::count(), 'the draft itself stays');
    }

    public function test_removing_one_of_two_parts_of_the_same_item_only_lowers_the_line(): void
    {
        $item = $this->makeItem('Gasket');
        $job = $this->jobWithParts([['item_id' => $item->id, 'quantity' => 4], ['item_id' => $item->id, 'quantity' => 1]]);
        [$first, $second] = PmsRequiredPart::where('job_id', $job->id)->orderBy('id')->get()->all();
        $order = $this->parts->createRequisition($job, [$first->id, $second->id], $this->user());
        $this->assertSame(5, (int) $order->orderItems()->sole()->item_qty);

        $this->parts->removeFromRequisition($first->fresh());

        $this->assertSame(1, (int) $order->orderItems()->sole()->item_qty);
        $this->assertNull($first->fresh()->order_id);
        $this->assertSame($order->id, (int) $second->fresh()->order_id, 'the other part is still on it');
    }

    public function test_a_part_on_a_submitted_requisition_cannot_be_removed(): void
    {
        [, $part, $order] = $this->requisitioned();
        $order->status = 'approved by chief-officer';
        $order->save();

        try {
            $this->parts->removeFromRequisition($part);
            $this->fail('Only a draft can be changed.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('already been submitted', $e->errors()['part'][0]);
        }

        $this->assertSame($order->id, (int) $part->fresh()->order_id);
        $this->assertSame(1, $order->orderItems()->count());
    }

    public function test_removing_a_part_that_is_not_on_a_requisition_is_refused(): void
    {
        $item = $this->makeItem('Gasket');
        $job = $this->jobWithParts([['item_id' => $item->id, 'quantity' => 1]]);

        $this->expectException(ValidationException::class);
        $this->parts->removeFromRequisition(PmsRequiredPart::where('job_id', $job->id)->sole());
    }

    // ---------------------------------------------------------------- used parts

    public function test_completing_with_used_parts_takes_them_from_stock_and_records_them(): void
    {
        $item = $this->makeItem('Gasket', 'SPR', 10);
        $job = $this->jobWithParts([]);

        $this->parts->completeWithUsedParts($job, $this->completionData(['completed_at' => '2026-01-10']),
            [['item_id' => $item->id, 'quantity' => 3]], $this->userId);

        $this->assertSame(PmsJob::COMPLETED, $job->fresh()->status);
        $this->assertSame(7, (int) DB::table('vessel_items')->where('item_id', $item->id)->value('stock_qty'));

        $consumption = StockConsumption::sole();
        $this->assertSame('pms_job', $consumption->consumption_type);
        $this->assertSame(3, (int) $consumption->qty);
        $this->assertSame($this->vesselId, (int) $consumption->vessel_id);
        $this->assertStringContainsString("Overhaul pump", $consumption->purpose);

        $used = PmsUsedPart::sole();
        $this->assertSame($job->id, $used->job_id);
        $this->assertSame($consumption->id, (int) $used->stock_consumption_id);
        $this->assertSame(3, (int) $used->quantity);
    }

    public function test_used_quantity_cannot_exceed_stock_and_the_job_stays_open(): void
    {
        $enough = $this->makeItem('Gasket', 'SPR', 10);
        $short = $this->makeItem('Seal', 'SPR', 2);
        $job = $this->jobWithParts([]);

        try {
            $this->parts->completeWithUsedParts($job, $this->completionData(['completed_at' => '2026-01-10']),
                [['item_id' => $enough->id, 'quantity' => 4], ['item_id' => $short->id, 'quantity' => 3]], $this->userId);
            $this->fail('Expected a refusal: only 2 seals in stock.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Only 2 of Seal', $e->errors()['used_parts'][0]);
        }

        $this->assertSame(PmsJob::PLANNED, $job->fresh()->status, 'not completed');
        $this->assertSame(10, (int) DB::table('vessel_items')->where('item_id', $enough->id)->value('stock_qty'), 'nothing was taken');
        $this->assertSame(0, StockConsumption::count());
        $this->assertSame(0, PmsUsedPart::count());
    }

    public function test_an_item_with_no_stock_on_board_cannot_be_used(): void
    {
        $none = $this->makeItem('Gasket', 'SPR');
        $job = $this->jobWithParts([]);

        $this->expectException(ValidationException::class);
        $this->parts->completeWithUsedParts($job, $this->completionData(['completed_at' => '2026-01-10']),
            [['item_id' => $none->id, 'quantity' => 1]], $this->userId);
    }

    public function test_a_refused_completion_does_not_touch_stock(): void
    {
        $item = $this->makeItem('Gasket', 'SPR', 10);
        $job = $this->jobWithParts([]);
        Carbon::setTestNow('2026-03-01'); // far past the 2026-02-10 due date, so a reason is required
        CarbonImmutable::setTestNow('2026-03-01');

        try {
            $this->parts->completeWithUsedParts($job, $this->completionData(['completed_at' => '2026-03-01']),
                [['item_id' => $item->id, 'quantity' => 3]], $this->userId);
            $this->fail('A late completion needs a reason.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('overdue_reason', $e->errors());
        }

        $this->assertSame(10, (int) DB::table('vessel_items')->where('item_id', $item->id)->value('stock_qty'));
        $this->assertSame(0, StockConsumption::count());
    }
}
