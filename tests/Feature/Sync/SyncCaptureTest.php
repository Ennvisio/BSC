<?php

namespace Tests\Feature\Sync;

use App\Sync\SyncContext;
use App\Sync\TriggerManager;
use App\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Tests\Concerns\BuildsRequisitionSchema;
use Tests\Concerns\BuildsSyncSchema;
use Tests\TestCase;

/**
 * The transactional outbox: the capture triggers record every insert, update
 * and delete in sync_outbox, in the same transaction as the change.
 */
class SyncCaptureTest extends TestCase
{
    use BuildsRequisitionSchema;
    use BuildsSyncSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRequisitionSchema();
        $this->runSyncMigrations();
        $this->installSyncTriggers();
        $this->clearOutbox();
    }

    // ------------------------------------------------------------ event creation

    public function test_an_eloquent_insert_gets_a_uuid_and_an_insert_event(): void
    {
        $user = User::create(['name' => 'New', 'email' => 'new@test.local', 'password' => 'x']);
        $row = DB::table('users')->find($user->id);

        $this->assertValidUuid($row->sync_uuid);
        $this->assertSame(1, (int) $row->sync_version);

        $events = $this->outbox();
        $this->assertCount(1, $events);
        $this->assertSame('users', $events[0]->table_name);
        $this->assertSame('insert', $events[0]->operation);
        $this->assertSame((string) $user->id, (string) $events[0]->row_id);
        $this->assertSame($row->sync_uuid, $events[0]->row_uuid);
        $this->assertSame(1, (int) $events[0]->row_version);
        $this->assertNull($events[0]->origin_node);
        $this->assertValidUuid($events[0]->event_id);
        $this->assertNotNull($events[0]->created_at);
    }

    public function test_a_query_builder_insert_is_captured_too(): void
    {
        // At least 16 write sites bypass Eloquent (audit R2); triggers see them all.
        DB::table('budget_groups')->insert(['name' => 'Bunkers', 'status' => true, 'created_by' => 1]);

        $row = DB::table('budget_groups')->where('name', 'Bunkers')->first();
        $this->assertValidUuid($row->sync_uuid);
        $this->assertSame([['budget_groups', 'insert', $row->sync_uuid]], $this->outbox()->map(fn ($e) => [$e->table_name, $e->operation, $e->row_uuid])->all());
    }

    public function test_events_carry_the_vessel_directly_or_through_their_parent(): void
    {
        $order = $this->makeOrder();
        $itemId = DB::table('order_items')->insertGetId(['order_id' => $order->id, 'item_id' => 1, 'item_qty' => 2]);

        $events = $this->outbox()->keyBy('table_name');
        $this->assertSame($this->vesselId, (int) $events['orders']->vessel_id);
        $this->assertSame($this->vesselId, (int) $events['order_approvals']->vessel_id);
        $this->assertSame($this->vesselId, (int) $events['order_items']->vessel_id);
        $this->assertSame((string) $itemId, (string) $events['order_items']->row_id);
    }

    public function test_fleet_wide_data_has_no_vessel_and_crew_attachments_have_the_uploaders_vessel(): void
    {
        $crew = $this->makeUser('chief-officer', $this->vesselId);
        $this->clearOutbox();

        DB::table('categories')->insert(['name' => 'Paints', 'symbol' => 'PNT', 'created_by' => 1]);
        DB::table('attachments')->insert([
            'uploaded_by' => $crew->id, 'title' => 'Spec', 'original_filename' => 'a.pdf', 'path' => 'x/a.pdf',
            'mime_type' => 'application/pdf', 'kind' => 'pdf', 'file_size' => 10,
        ]);

        $events = $this->outbox()->keyBy('table_name');
        $this->assertNull($events['categories']->vessel_id);
        $this->assertSame($this->vesselId, (int) $events['attachments']->vessel_id);
    }

    public function test_an_update_raises_the_version_and_records_an_update_event(): void
    {
        $order = $this->makeOrder();
        $uuid = DB::table('orders')->where('id', $order->id)->value('sync_uuid');
        $this->clearOutbox();

        $order->status = 'approved';
        $order->save();
        DB::table('orders')->where('id', $order->id)->update(['port_name' => 'Chattogram']);

        $row = DB::table('orders')->find($order->id);
        $this->assertSame(3, (int) $row->sync_version);
        $this->assertSame($uuid, $row->sync_uuid);

        $events = $this->outbox();
        $this->assertSame(['update', 'update'], $events->pluck('operation')->all());
        $this->assertSame([2, 3], $events->pluck('row_version')->map(fn ($v) => (int) $v)->all());
        $this->assertSame([$uuid, $uuid], $events->pluck('row_uuid')->all());
    }

    public function test_a_rows_uuid_can_never_be_changed(): void
    {
        $order = $this->makeOrder();
        $uuid = DB::table('orders')->where('id', $order->id)->value('sync_uuid');

        DB::table('orders')->where('id', $order->id)->update(['sync_uuid' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb']);
        DB::table('orders')->where('id', $order->id)->update(['sync_uuid' => null]);

        $this->assertSame($uuid, DB::table('orders')->where('id', $order->id)->value('sync_uuid'));
    }

    public function test_a_delete_records_the_deleted_rows_uuid_and_vessel(): void
    {
        $order = $this->makeOrder();
        $itemId = DB::table('order_items')->insertGetId(['order_id' => $order->id, 'item_id' => 1, 'item_qty' => 2]);
        $uuid = DB::table('order_items')->where('id', $itemId)->value('sync_uuid');
        $this->clearOutbox();

        DB::table('order_items')->where('id', $itemId)->delete();

        $event = $this->outbox()->sole();
        $this->assertSame(['order_items', 'delete', $uuid, $this->vesselId], [$event->table_name, $event->operation, $event->row_uuid, (int) $event->vessel_id]);
    }

    public function test_new_rows_always_get_distinct_uuids(): void
    {
        DB::table('budget_groups')->insert(array_map(
            fn ($i) => ['name' => "Bulk {$i}", 'status' => true, 'created_by' => 1],
            range(1, 300)
        ));

        $uuids = DB::table('budget_groups')->where('name', 'like', 'Bulk %')->pluck('sync_uuid');
        $this->assertCount(300, $uuids);
        $this->assertCount(300, $uuids->unique());
        $uuids->each(fn ($uuid) => $this->assertValidUuid($uuid));

        $eventIds = $this->outbox()->pluck('event_id');
        $this->assertCount(300, $eventIds);
        $this->assertCount(300, $eventIds->unique());
    }

    // --------------------------------------------------------- transactions

    public function test_a_rolled_back_change_leaves_neither_the_row_nor_its_event(): void
    {
        try {
            DB::transaction(function () {
                DB::table('budget_groups')->insert(['name' => 'Rolled back', 'status' => true, 'created_by' => 1]);
                $this->makeOrder();
                throw new RuntimeException('simulated failure');
            });
        } catch (RuntimeException) {
        }

        $this->assertFalse(DB::table('budget_groups')->where('name', 'Rolled back')->exists());
        $this->assertCount(0, $this->outbox());
    }

    public function test_a_committed_change_keeps_its_event(): void
    {
        DB::transaction(fn () => DB::table('budget_groups')->insert(['name' => 'Committed', 'status' => true, 'created_by' => 1]));

        $this->assertCount(1, $this->outbox());
    }

    public function test_a_rolled_back_savepoint_removes_only_its_own_events(): void
    {
        DB::transaction(function () {
            DB::table('budget_groups')->insert(['name' => 'Outer', 'status' => true, 'created_by' => 1]);

            try {
                DB::transaction(function () {
                    DB::table('budget_groups')->insert(['name' => 'Inner', 'status' => true, 'created_by' => 1]);
                    throw new RuntimeException('inner failure');
                });
            } catch (RuntimeException) {
            }
        });

        $this->assertSame(['Outer'], DB::table('budget_groups')->whereIn('name', ['Outer', 'Inner'])->pluck('name')->all());
        $this->assertCount(1, $this->outbox());
    }

    public function test_an_update_rolled_back_keeps_the_old_version(): void
    {
        $order = $this->makeOrder();
        $this->clearOutbox();

        try {
            DB::transaction(function () use ($order) {
                DB::table('orders')->where('id', $order->id)->update(['status' => 'approved']);
                throw new RuntimeException('x');
            });
        } catch (RuntimeException) {
        }

        $this->assertSame(1, (int) DB::table('orders')->where('id', $order->id)->value('sync_version'));
        $this->assertCount(0, $this->outbox());
    }

    // ------------------------------------------------------- capture context

    public function test_changes_made_with_capture_off_are_not_recorded(): void
    {
        $order = $this->makeOrder();
        $this->clearOutbox();

        SyncContext::withoutCapture(function () use ($order) {
            DB::table('orders')->where('id', $order->id)->update(['status' => 'maintenance']);
            DB::table('budget_groups')->insert(['name' => 'Quiet', 'status' => true, 'created_by' => 1]);
        });

        $this->assertCount(0, $this->outbox());
        $this->assertSame(1, (int) DB::table('orders')->where('id', $order->id)->value('sync_version'));
        $this->assertValidUuid(DB::table('budget_groups')->where('name', 'Quiet')->value('sync_uuid'));

        // Capture is back on afterwards.
        DB::table('orders')->where('id', $order->id)->update(['status' => 'loud']);
        $this->assertCount(1, $this->outbox());
    }

    public function test_capture_is_restored_even_when_the_callback_fails(): void
    {
        try {
            SyncContext::withoutCapture(fn () => throw new RuntimeException('x'));
        } catch (RuntimeException) {
        }

        $this->assertTrue(SyncContext::captureEnabled());
        DB::table('budget_groups')->insert(['name' => 'After', 'status' => true, 'created_by' => 1]);
        $this->assertCount(1, $this->outbox());
    }

    public function test_changes_applied_from_another_node_keep_their_version_and_origin(): void
    {
        $order = $this->makeOrder();
        $this->clearOutbox();
        $cloud = '11111111-1111-4111-8111-111111111111';

        SyncContext::asOrigin($cloud, fn () => DB::table('orders')->where('id', $order->id)->update(['status' => 'approved', 'sync_version' => 7]));

        $event = $this->outbox()->sole();
        $this->assertSame($cloud, $event->origin_node);
        $this->assertSame(7, (int) $event->row_version);
        $this->assertSame(7, (int) DB::table('orders')->where('id', $order->id)->value('sync_version'));
        $this->assertNull(SyncContext::origin());
    }

    public function test_an_origin_must_be_a_node_uuid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SyncContext::asOrigin('cloud', fn () => null);
    }

    public function test_the_outbox_refuses_a_repeated_event_id(): void
    {
        DB::table('budget_groups')->insert(['name' => 'Once', 'status' => true, 'created_by' => 1]);
        $event = (array) $this->outbox()->sole();
        unset($event['id']);

        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('sync_outbox')->insert($event);
    }

    // -------------------------------------------------------- trigger management

    public function test_installing_twice_does_not_duplicate_events(): void
    {
        $manager = app(TriggerManager::class);
        $countBefore = count($manager->installedTriggerNames());

        $this->installSyncTriggers();

        $this->assertSame($countBefore, count($manager->installedTriggerNames()));
        $this->assertTrue($manager->isFullyInstalled());

        DB::table('budget_groups')->insert(['name' => 'Single', 'status' => true, 'created_by' => 1]);
        $this->assertCount(1, $this->outbox());
    }

    public function test_dropping_the_triggers_stops_capture_without_affecting_writes(): void
    {
        app(TriggerManager::class)->drop();

        DB::table('budget_groups')->insert(['name' => 'Uncaptured', 'status' => true, 'created_by' => 1]);

        $this->assertTrue(DB::table('budget_groups')->where('name', 'Uncaptured')->exists());
        $this->assertCount(0, $this->outbox());
        $this->assertSame([], app(TriggerManager::class)->installedTriggerNames());
    }

    public function test_an_existing_approval_still_works_with_capture_on(): void
    {
        $order = $this->makeOrder(['vessel_id' => $this->vesselId, 'approval' => ['cheif_ofcr_app' => 1]]);
        $master = $this->makeUser('master', $this->vesselId);
        $this->clearOutbox();

        $this->actingAs($master)->postJson('/order/approve', ['id' => $order->id])->assertOk();

        $this->assertNotNull($order->fresh()->orderApproval->master_app);
        $approvalEvent = $this->outbox()->firstWhere('table_name', 'order_approvals');
        $this->assertNotNull($approvalEvent, 'the approval must be recorded for sync');
        $this->assertSame('update', $approvalEvent->operation);
        $this->assertSame($this->vesselId, (int) $approvalEvent->vessel_id);
    }
}
