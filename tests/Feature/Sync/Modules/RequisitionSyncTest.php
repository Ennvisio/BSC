<?php

namespace Tests\Feature\Sync\Modules;

use App\ProcurementStage;
use App\Sync\Models\SyncConflict;
use Illuminate\Support\Facades\DB;

/**
 * Requisitions pass between ship and office stage by stage. Each side writes
 * its own columns; the status and rejection details ("the baton") are
 * shared. These tests drive the real approval endpoints on either server.
 */
class RequisitionSyncTest extends BusinessSyncTestCase
{
    public function test_a_requisition_raised_on_board_reaches_the_office_with_lines_signatures_and_part_a(): void
    {
        $orderId = $this->draftOnBoard(['ME-100' => 4, 'ME-200' => 2]);
        $uuid = $this->submitOnBoard($orderId);
        $this->onShip(fn () => $this->approve('master@mvtest.test', $orderId)->assertOk());

        $this->assertCycleOk();

        $this->onCloud(function () use ($uuid) {
            $order = DB::table('orders')->where('sync_uuid', $uuid)->first();
            $this->assertNotNull($order, 'the requisition is in the office');
            $this->assertSame('MV TEST', DB::table('vessels')->where('id', $order->vessel_id)->value('name'));
            $this->assertStringContainsString('/SPR/01/', $order->req_no);
            $this->assertSame('approved by master', $order->status);
            $this->assertSame($this->userId('co@mvtest.test'), (int) $order->created_by, 'the raiser is the same person in the office');

            $lines = DB::table('order_items')->where('order_id', $order->id)->pluck('item_qty', 'item_id')->all();
            $this->assertEquals([$this->itemId('ME-100') => 4, $this->itemId('ME-200') => 2], $lines, 'lines point at the office\'s own item ids');

            $approval = DB::table('order_approvals')->where('order_id', $order->id)->first();
            $this->assertSame($this->userId('co@mvtest.test'), (int) $approval->cheif_ofcr_app);
            $this->assertSame($this->userId('master@mvtest.test'), (int) $approval->master_app);
            $this->assertSame('A', DB::table('order_form_parts')->where('order_id', $order->id)->value('part'));
        });
        $this->assertNotSame(
            $this->onShip(fn () => $this->idOf('orders', $uuid)),
            $this->onCloud(fn () => $this->idOf('orders', $uuid)),
            'the two servers gave it different ids',
        );
        $this->assertSame([], $this->inboxProblems('cloud'));
    }

    /** Submitted on board, approved by the master, and in the office: returns [ship id, uuid]. */
    private function withTheOffice(array $lines = ['ME-100' => 4]): array
    {
        $orderId = $this->draftOnBoard($lines);
        $uuid = $this->submitOnBoard($orderId);
        $this->onShip(fn () => $this->approve('master@mvtest.test', $orderId)->assertOk());
        $this->assertCycleOk();

        return [$orderId, $uuid];
    }

    /** The office's earlier stages, as their screens leave them, up to Delivery. */
    private function officeReachesDelivery(string $uuid): void
    {
        $this->onCloud(function () use ($uuid) {
            $id = $this->idOf('orders', $uuid);
            DB::table('orders')->where('id', $id)->update(['status' => 'approved by srd-general-manager', 'procurement_stage' => ProcurementStage::DELIVERY, 'updated_at' => now()]);
            DB::table('order_approvals')->where('order_id', $id)->update([
                'gm_app' => $this->userId('gm.srd@office.test'), 'dgm_app_ssm' => $this->userId('dgm.ssm@office.test'),
                'assigned_to_ssm' => $this->userId('am.ssm@office.test'), 'updated_at' => now(),
            ]);
        });
    }

    public function test_office_approval_part_b_remarks_and_quantity_corrections_come_back_to_the_vessel(): void
    {
        [$orderId, $uuid] = $this->withTheOffice(['ME-100' => 4]);

        $this->onCloud(function () use ($uuid) {
            $id = $this->idOf('orders', $uuid);
            $line = (int) DB::table('order_items')->where('order_id', $id)->value('id');
            $this->as('gm.srd@office.test')->postJson("/order/{$id}/form-part/B", ['q' => []])->assertOk();
            $this->as('gm.srd@office.test')->postJson("/order/{$id}/official-remarks", ['official_remarks' => 'Urgent, ship at anchor.'])->assertOk();
            $this->as('gm.srd@office.test')->postJson('/order/approve', ['id' => $id, 'req_qty' => [$line => 3]])->assertOk();
        });

        $this->assertCycleOk();

        $this->onShip(function () use ($orderId) {
            $order = DB::table('orders')->where('id', $orderId)->first();
            $this->assertSame('approved by srd-general-manager', $order->status);
            $this->assertSame('Urgent, ship at anchor.', $order->official_remarks);
            $this->assertSame($this->userId('gm.srd@office.test'), (int) $order->official_remarks_by);
            $this->assertSame($this->userId('gm.srd@office.test'), (int) DB::table('order_approvals')->where('order_id', $orderId)->value('gm_app'));
            $this->assertSame(['A', 'B'], DB::table('order_form_parts')->where('order_id', $orderId)->orderBy('part')->pluck('part')->all());

            $line = DB::table('order_items')->where('order_id', $orderId)->first();
            $this->assertSame(3, (int) $line->item_qty, 'SRD\'s correction');
            $change = DB::table('order_item_quantity_changes')->where('order_item_id', $line->id)->sole();
            $this->assertSame([4, 3, 'gm-srd'], [(int) $change->old_qty, (int) $change->new_qty, $change->changed_by_role], 'the audit line came with it');
            $this->assertSame($this->userId('gm.srd@office.test'), (int) $change->changed_by);

            $this->assertFalse(\App\Order::find($orderId)->hasPendingActionFor('master', $this->userId('master@mvtest.test')), 'no longer with the ship');
        });
        $this->assertSame([], $this->inboxProblems('ship'));
    }

    public function test_delivery_in_the_office_and_receipt_on_board_close_the_loop_and_move_stock_once(): void
    {
        [$orderId, $uuid] = $this->withTheOffice(['ME-100' => 4]);
        $this->officeReachesDelivery($uuid);
        $this->onCloud(function () use ($uuid) {
            $id = $this->idOf('orders', $uuid);
            $line = (int) DB::table('order_items')->where('order_id', $id)->value('id');
            $this->as('am.ssm@office.test')->postJson('/order/approve', ['id' => $id, 'deliver_qty' => [$line => 3]])->assertOk();
        });
        $this->assertCycleOk();

        $this->onShip(function () use ($orderId) {
            $order = DB::table('orders')->where('id', $orderId)->first();
            $this->assertSame(['delivered', ProcurementStage::RECEIPT_VERIFICATION], [$order->status, $order->procurement_stage]);
            $this->assertNotNull($order->deliver_date);
            $this->assertSame(3, (int) DB::table('order_items')->where('order_id', $orderId)->value('del_item_qty'));
            $this->assertSame([ProcurementStage::DELIVERY], DB::table('order_procurement_steps')->where('order_id', $orderId)->pluck('step')->all());

            // The raiser confirms receipt through the real screen action.
            $line = (int) DB::table('order_items')->where('order_id', $orderId)->value('id');
            $this->as('co@mvtest.test')->postJson('/order/approve', ['id' => $orderId, 'rcv_qty' => [$line => 3]])->assertOk();
            $this->assertSame(13, (int) DB::table('vessel_items')->where('vessel_id', 7)->where('item_id', $this->itemId('ME-100'))->value('stock_qty'));
        });

        $this->assertCycleOk();
        $this->assertCycleOk(); // nothing is applied twice

        $this->onCloud(function () use ($uuid) {
            $id = $this->idOf('orders', $uuid);
            $order = DB::table('orders')->where('id', $id)->first();
            $this->assertSame(['received', ProcurementStage::INVOICE_VERIFICATION], [$order->status, $order->procurement_stage]);
            $this->assertNotNull($order->rcv_date);
            $this->assertSame(3, (int) DB::table('order_items')->where('order_id', $id)->value('rcv_item_qty'));
            $this->assertSame([ProcurementStage::DELIVERY, ProcurementStage::RECEIPT_VERIFICATION], DB::table('order_procurement_steps')->where('order_id', $id)->orderBy('completed_at')->orderBy('id')->pluck('step')->all());
            $this->assertSame($this->userId('co@mvtest.test'), (int) DB::table('order_procurement_steps')->where('order_id', $id)->where('step', 'receipt_verification')->value('completed_by'));

            $movement = DB::table('stock_movements')->where('order_id', $id)->sole();
            $this->assertSame(['receipt', 3, 13], [$movement->type, (int) $movement->qty, (int) $movement->balance_after]);
            $this->assertSame(13, (int) DB::table('vessel_items')->where('vessel_id', 7)->where('item_id', $this->itemId('ME-100'))->value('stock_qty'), 'the office\'s balance follows the ledger');
            $this->assertSame(0, SyncConflict::query()->count());
        });
    }

    public function test_a_superintendent_rejecting_while_the_master_approves_keeps_the_ships_version_for_review(): void
    {
        $orderId = $this->draftOnBoard(['ME-100' => 4]);
        $uuid = $this->submitOnBoard($orderId);
        $this->assertCycleOk();

        // Both act on the same stage before either sees the other.
        $this->onShip(fn () => $this->approve('master@mvtest.test', $orderId)->assertOk());
        $this->onCloud(fn () => $this->as('tech.supt@office.test')->postJson('/order/reject', ['id' => $this->idOf('orders', $uuid), 'reason' => 'Not budgeted'])->assertOk());

        $this->assertCycleOk();
        $this->assertCycleOk();

        foreach (['ship', 'cloud'] as $server) {
            $order = $this->row($server, 'orders', $uuid);
            $this->assertSame('approved by master', $order->status, "{$server}: the holder (the ship) wins for now");
            $this->assertNull($order->rejected_by, "{$server}: the rejection details went with the status");
            $this->assertNull($order->rejection_reason);
        }
        $conflict = $this->onCloud(fn () => SyncConflict::query()->where('table_name', 'orders')->sole());
        $this->assertSame(['needs_review', 'took_remote'], [$conflict->status, $conflict->resolution], 'the office took the ship\'s version, provisionally');
        $this->assertStringContainsString('status', $conflict->note);
        $this->assertSame('Not budgeted', $conflict->local_values['rejection_reason'], 'the office\'s rejection is kept in the record for the reviewer');
        $this->assertSame(1, $this->onShip(fn () => SyncConflict::query()->where('status', 'needs_review')->count()), 'the crew see it too');
    }

    public function test_ship_and_office_signing_their_own_columns_at_the_same_time_is_not_a_conflict(): void
    {
        $orderId = $this->draftOnBoard(['ME-100' => 4]);
        $uuid = $this->submitOnBoard($orderId);
        $this->assertCycleOk();

        // The technical superintendent's acknowledgement is an office column;
        // the master's approval a ship column and the status.
        $this->onShip(fn () => $this->approve('master@mvtest.test', $orderId)->assertOk());
        $this->onCloud(fn () => $this->as('tech.supt@office.test')->postJson('/order/approve', ['id' => $this->idOf('orders', $uuid)])->assertOk());

        $this->assertCycleOk();
        $this->assertCycleOk();

        foreach (['ship', 'cloud'] as $server) {
            $this->on($server, function () use ($uuid, $server) {
                $id = $this->idOf('orders', $uuid);
                $this->assertSame('approved by master', DB::table('orders')->where('id', $id)->value('status'), $server);
                $approval = DB::table('order_approvals')->where('order_id', $id)->first();
                $this->assertSame($this->userId('master@mvtest.test'), (int) $approval->master_app, $server);
                $this->assertSame($this->userId('tech.supt@office.test'), (int) $approval->tech_superintendent_app, $server);
            });
        }
        $this->assertSame(0, $this->onCloud(fn () => SyncConflict::query()->where('kind', 'update_update')->count()));
    }

    public function test_a_requisition_number_already_used_in_the_office_is_held_for_review_not_renumbered(): void
    {
        $orderId = $this->draftOnBoard(['ME-100' => 4]);
        $uuid = $this->submitOnBoard($orderId);
        $reqNo = $this->onShip(fn () => DB::table('orders')->where('id', $orderId)->value('req_no'));
        // A different requisition in the office already carries that number.
        $this->onCloud(fn () => DB::table('orders')->insert([
            'vessel_id' => 7, 'category_id' => $this->categoryId, 'req_no' => $reqNo, 'req_date' => now()->toDateString(), 'port_name' => '-',
            'status' => 'approved by chief-officer', 'ord_status' => true, 'created_by' => 1, 'created_by_role' => 'chief-officer', 'created_at' => now(), 'updated_at' => now(),
        ]));

        $result = $this->syncCycle();

        $this->assertTrue($result['ok']);
        $this->assertGreaterThan(0, $result['summary']['pushed']['rejected']);
        $this->assertNull($this->row('cloud', 'orders', $uuid), 'not inserted under a number already taken');
        $this->assertSame(1, $this->onCloud(fn () => DB::table('orders')->where('req_no', $reqNo)->count()));
        $this->assertSame($reqNo, $this->row('ship', 'orders', $uuid)->req_no, 'never renumbered automatically');
        $this->assertSame('needs_review', $this->onCloud(fn () => SyncConflict::query()->where('kind', 'duplicate_key')->where('table_name', 'orders')->sole()->status));
    }

    public function test_a_batch_sent_twice_after_a_lost_answer_creates_one_requisition(): void
    {
        $orderId = $this->draftOnBoard(['ME-100' => 4, 'ME-200' => 1]);
        $uuid = $this->submitOnBoard($orderId);
        $this->network = fn ($path, $n) => $path === '/api/sync/v1/push' && $n === 1 ? 'drop-response' : 'up';

        $this->assertFalse($this->syncCycle()['ok'], 'the answer was lost');
        $this->assertCycleOk();

        $this->onCloud(function () use ($uuid) {
            $this->assertSame(1, DB::table('orders')->where('sync_uuid', $uuid)->count());
            $id = $this->idOf('orders', $uuid);
            $this->assertSame(2, DB::table('order_items')->where('order_id', $id)->count());
            $this->assertSame(1, DB::table('order_approvals')->where('order_id', $id)->count());
            $this->assertSame(1, DB::table('order_form_parts')->where('order_id', $id)->count());
        });
    }

    public function test_the_vessel_cannot_change_what_the_office_owns(): void
    {
        [$orderId, $uuid] = $this->withTheOffice(['ME-100' => 4]);
        $this->onCloud(function () use ($uuid) {
            $id = $this->idOf('orders', $uuid);
            $this->as('gm.srd@office.test')->postJson("/order/{$id}/form-part/B", ['q' => []])->assertOk();
            $this->as('gm.srd@office.test')->postJson("/order/{$id}/official-remarks", ['official_remarks' => 'Office note'])->assertOk();
        });
        $this->assertCycleOk();

        // Edited on board directly in the database, bypassing the screens.
        $this->onShip(function () use ($orderId) {
            DB::table('orders')->where('id', $orderId)->update(['official_remarks' => 'Changed on board', 'port_name' => 'Singapore', 'updated_at' => now()->addMinute()]);
            DB::table('order_form_parts')->where('order_id', $orderId)->where('part', 'B')->update(['answers' => '{"forged":true}', 'updated_at' => now()->addMinute()]);
        });
        $result = $this->syncCycle();

        $this->assertSame(1, $result['summary']['pushed']['rejected'], 'Part B is the office\'s row');
        $this->onCloud(function () use ($uuid) {
            $id = $this->idOf('orders', $uuid);
            $order = DB::table('orders')->where('id', $id)->first();
            $this->assertSame('Office note', $order->official_remarks, 'an office column is never taken from the vessel');
            $this->assertSame('Singapore', $order->port_name, 'the vessel\'s own column is');
            $this->assertStringNotContainsString('forged', (string) DB::table('order_form_parts')->where('order_id', $id)->where('part', 'B')->value('answers'));
        });
        $this->assertStringContainsString('kept by the cloud', (string) $this->onCloud(fn () => DB::table('sync_inbox')->where('status', 'failed')->value('last_error')));
    }

    public function test_a_procurement_step_is_a_permanent_record(): void
    {
        [$orderId, $uuid] = $this->withTheOffice(['ME-100' => 4]);
        $this->officeReachesDelivery($uuid);
        $this->onCloud(fn () => $this->as('am.ssm@office.test')->postJson('/order/approve', ['id' => $this->idOf('orders', $uuid)])->assertOk());
        $this->assertCycleOk();
        $this->onShip(fn () => $this->approve('co@mvtest.test', $orderId)->assertOk());
        $this->assertCycleOk();

        $this->onShip(fn () => DB::table('order_procurement_steps')->where('order_id', $orderId)->where('step', 'receipt_verification')->update(['remarks' => 'rewritten later', 'updated_at' => now()->addHour()]));
        $result = $this->syncCycle();

        $this->assertSame(1, $result['summary']['pushed']['rejected']);
        $this->assertStringContainsString('permanent record', (string) $this->onCloud(fn () => DB::table('sync_inbox')->where('status', 'failed')->value('last_error')));
        $this->assertNull($this->onCloud(fn () => DB::table('order_procurement_steps')->where('step', 'receipt_verification')->value('remarks')));
    }

    public function test_a_service_requisition_goes_up_with_its_equipment_and_an_office_rejection_comes_back(): void
    {
        $id = $this->onShip(function () {
            $co = $this->userId('co@mvtest.test');
            $equipment = DB::table('vessel_equipment')->insertGetId(['vessel_id' => 7, 'name' => 'Fresh water generator', 'maker' => 'Alfa Laval', 'status' => true, 'created_by' => $co, 'created_at' => now(), 'updated_at' => now()]);
            $id = DB::table('service_requisitions')->insertGetId([
                'vessel_id' => 7, 'req_no' => 'DK/SHR/01/2026', 'req_date' => now()->toDateString(), 'service_type' => 'shore_repair',
                'description' => 'Overhaul', 'status' => 'approved by chief-officer', 'is_submitted' => true, 'created_by' => $co,
                'created_by_role' => 'chief-officer', 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('service_requisition_items')->insert(['service_requisition_id' => $id, 'vessel_equipment_id' => $equipment, 'title' => 'Fresh water generator', 'quantity' => 1, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('service_requisition_approvals')->insert(['service_requisition_id' => $id, 'cheif_ofcr_app' => $co, 'created_at' => now(), 'updated_at' => now()]);

            return $id;
        });
        $uuid = $this->onShip(fn () => $this->uuidFor('service_requisitions', $id));

        $this->assertCycleOk();

        $this->onCloud(function () use ($uuid) {
            $cloudId = $this->idOf('service_requisitions', $uuid);
            $this->assertNotNull($cloudId);
            $item = DB::table('service_requisition_items')->where('service_requisition_id', $cloudId)->sole();
            $this->assertSame('Fresh water generator', DB::table('vessel_equipment')->where('id', $item->vessel_equipment_id)->value('name'));
            // What ServiceRequisitionApprovalController::reject() writes.
            DB::table('service_requisitions')->where('id', $cloudId)->update([
                'status' => 'rejected', 'rejected_by' => $this->userId('gm.srd@office.test'), 'rejected_by_role' => 'gm-srd',
                'rejected_at_stage' => 'GM (SRD)', 'rejected_at' => now(), 'rejection_reason' => 'Do it at drydock', 'updated_at' => now(),
            ]);
        });

        $this->assertCycleOk();

        $row = $this->row('ship', 'service_requisitions', $uuid);
        $this->assertSame(['rejected', 'Do it at drydock', 'gm-srd'], [$row->status, $row->rejection_reason, $row->rejected_by_role]);
        $this->assertSame($this->onShip(fn () => $this->userId('gm.srd@office.test')), (int) $row->rejected_by);
    }

    public function test_a_legacy_reference_that_points_nowhere_is_not_blanked_in_the_office(): void
    {
        [$orderId, $uuid] = $this->withTheOffice(['ME-100' => 4]);
        // Old data: the raiser stored by name, on both servers alike.
        foreach (['ship', 'cloud'] as $server) {
            $this->on($server, fn () => \App\Sync\SyncContext::withoutCapture(fn () => DB::table('orders')->where('sync_uuid', $uuid)->update(['created_by' => 'C/O Karim'])));
        }
        $this->onShip(fn () => DB::table('orders')->where('id', $orderId)->update(['port_name' => 'Mongla', 'updated_at' => now()->addMinute()]));

        $this->assertCycleOk();

        $order = $this->row('cloud', 'orders', $uuid);
        $this->assertSame(['Mongla', 'C/O Karim'], [$order->port_name, $order->created_by]);
        $this->assertTrue($this->onShip(fn () => DB::table('sync_logs')->where('context', 'like', '%orders.created_by=C%Karim%')->exists()), 'reported in the log');
    }
}
