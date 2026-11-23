<?php

namespace Tests\Feature\Sync\Engine;

use App\Sync\Engine\OutboxSequencer;
use App\Sync\Engine\SyncWorker;
use App\Sync\Models\SyncBatch;
use App\Sync\SyncCursors;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

/**
 * Duplicates, interrupted connections, crashes part-way through, partial
 * failures and retries: nothing is lost and nothing is applied twice.
 */
class SyncReliabilityTest extends SyncEngineTestCase
{
    // ------------------------------------------------------------ duplicates

    public function test_a_batch_whose_answer_was_lost_is_sent_again_and_applied_only_once(): void
    {
        $uuid = $this->addManufacturer('ship', 'Wartsila');
        $this->network = fn ($path, $n) => $path === '/api/sync/v1/push' && $n === 1 ? 'drop-response' : 'up';

        $first = $this->syncCycle();
        $this->assertFalse($first['ok'], 'the vessel never heard back');
        $this->assertSame(1, $this->cloudCount('manufacturers'), 'but the cloud did apply it');

        $this->assertCycleOk();

        $pushes = array_values(array_filter($this->requests, fn ($r) => $r['path'] === '/api/sync/v1/push'));
        $this->assertCount(2, $pushes);
        $this->onShip(function () {
            $batch = SyncBatch::query()->where('direction', 'out')->sole();
            $this->assertSame(2, $batch->attempts, 'one stored batch, sent twice');
            $this->assertSame('acked', $batch->status);
            $this->assertTrue(json_decode($batch->response, true)['repeated']);
        });
        $this->onCloud(function () use ($uuid) {
            $this->assertSame(1, DB::table('sync_inbox')->count());
            $this->assertSame(1, SyncBatch::query()->where('direction', 'in')->count());
            $this->assertSame(1, DB::table('sync_outbox')->where('row_uuid', $uuid)->count(), 'applied once, so recorded once');
        });
    }

    public function test_an_event_delivered_again_in_another_batch_is_recognised(): void
    {
        $event = $this->event('manufacturers', $this->newUuid(), ['name' => 'Alfa Laval', 'created_at' => null, 'updated_at' => null]);

        $first = $this->api('POST', 'push', $this->batch([$event]))->assertOk()->json();
        $second = $this->api('POST', 'push', $this->batch([$event]))->assertOk()->json();

        $this->assertSame(1, $first['counts']['applied']);
        $this->assertSame(1, $second['counts']['duplicate']);
        $this->assertSame(1, $this->cloudCount('manufacturers'));
    }

    public function test_the_same_batch_id_gets_the_same_answer_even_with_different_content(): void
    {
        $id = $this->newUuid();
        $first = $this->api('POST', 'push', $this->batch([$this->event('manufacturers', $this->newUuid(), ['name' => 'One'])], $id))->json();
        $again = $this->api('POST', 'push', $this->batch([$this->event('manufacturers', $this->newUuid(), ['name' => 'Two'])], $id))->json();

        $this->assertSame($first['counts'], $again['counts']);
        $this->assertTrue($again['repeated']);
        $this->assertSame(['One'], $this->onCloud(fn () => DB::table('manufacturers')->pluck('name')->all()));
    }

    public function test_a_page_whose_answer_was_lost_is_fetched_again_and_applied_once(): void
    {
        $eur = $this->addCurrency('EUR', 'Euro');
        $this->network = fn ($path, $n) => $path === '/api/sync/v1/pull' && $n === 1 ? 'drop-response' : 'up';

        $this->assertFalse($this->syncCycle()['ok']);
        $this->assertNull($this->row('ship', 'currencies', $eur));
        $this->assertSame(0, $this->onShip(fn () => app(SyncCursors::class)->position($this->cloudNode, 'pull')));

        $this->assertCycleOk();

        $this->assertSame('Euro', $this->row('ship', 'currencies', $eur)->name);
        $this->onShip(fn () => $this->assertSame(1, DB::table('sync_inbox')->count()));
    }

    // ------------------------------------------------------------ interruptions

    public function test_the_link_failing_between_push_and_pull_loses_nothing(): void
    {
        $this->addManufacturer('ship', 'Sent up');
        $eur = $this->addCurrency('EUR', 'Euro');
        $this->network = fn ($path) => $path === '/api/sync/v1/pull' ? 'down' : 'up';

        $this->assertFalse($this->syncCycle()['ok']);
        $this->assertSame(1, $this->cloudCount('manufacturers'), 'the push completed');
        $this->assertNull($this->row('ship', 'currencies', $eur));

        $this->network = 'up';
        $this->assertCycleOk();
        $this->assertNotNull($this->row('ship', 'currencies', $eur));
        $this->assertSame(1, $this->cloudCount('manufacturers'));
    }

    public function test_a_batch_created_before_a_reboot_is_sent_unchanged_after_it(): void
    {
        $this->addManufacturer('ship', 'Before the reboot');
        $this->network = fn ($path) => $path === '/api/sync/v1/push' ? 'down' : 'up';
        $this->assertFalse($this->syncCycle()['ok']);
        $batchId = $this->onShip(fn () => SyncBatch::query()->where('direction', 'out')->value('batch_id'));

        // Windows restarts: a fresh process, and more work is done meanwhile.
        $this->app->forgetInstance(SyncWorker::class);
        $this->addManufacturer('ship', 'After the reboot');
        $this->network = 'up';
        $this->travel(10)->minutes();
        $this->assertCycleOk();

        $this->onShip(function () use ($batchId) {
            $batches = SyncBatch::query()->where('direction', 'out')->orderBy('id')->get();
            $this->assertSame($batchId, $batches[0]->batch_id, 'the stored batch went first, unchanged');
            $this->assertSame(['acked', 'acked'], $batches->pluck('status')->all());
        });
        $this->assertSame(2, $this->cloudCount('manufacturers'));
    }

    // ------------------------------------------------------------ crashes mid-way

    public function test_a_cloud_crash_part_way_through_a_batch_leaves_nothing_and_the_retry_applies_it_all(): void
    {
        $this->onShip(fn () => DB::table('manufacturers')->insert(array_map(fn ($i) => ['name' => "Maker {$i}", 'created_at' => now(), 'updated_at' => now()], range(1, 5))));
        $inserts = 0;
        $this->crashWhen = function (QueryExecuted $q) use (&$inserts) {
            return $q->connectionName === 'cloud' && str_starts_with($q->sql, 'insert into "manufacturers"') && ++$inserts === 3;
        };

        $result = $this->syncCycle();
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('500', $result['error']);
        $this->onCloud(function () {
            $this->assertSame(0, DB::table('manufacturers')->count(), 'two rows were written before the crash; both rolled back');
            $this->assertSame(0, DB::table('sync_inbox')->count());
            $this->assertSame(0, SyncBatch::query()->count());
        });

        $this->crashWhen = null;
        $this->travel(1)->minutes();
        $this->assertCycleOk();
        $this->assertSame(5, $this->cloudCount('manufacturers'));
    }

    public function test_a_vessel_crash_while_applying_a_page_leaves_nothing_and_the_retry_applies_it_all(): void
    {
        foreach (['EUR', 'USD', 'GBP'] as $code) {
            $this->addCurrency($code, $code);
        }
        $inserts = 0;
        $this->crashWhen = function (QueryExecuted $q) use (&$inserts) {
            return $q->connectionName === 'ship' && str_starts_with($q->sql, 'insert into "currencies"') && ++$inserts === 2;
        };

        $this->assertFalse($this->syncCycle()['ok']);
        $this->assertSame(0, $this->shipCount('currencies'));
        $this->assertSame(0, $this->onShip(fn () => app(SyncCursors::class)->position($this->cloudNode, 'pull')), 'the position did not move');

        $this->crashWhen = null;
        $this->travel(1)->minutes();
        $this->assertCycleOk();
        $this->assertSame(3, $this->shipCount('currencies'));
    }

    // ------------------------------------------------------------ partial failure

    public function test_one_bad_change_does_not_stop_the_rest_of_the_batch(): void
    {
        $otherVessel = $this->uuidOf('cloud', 'vessels', 8);
        $survey = $this->uuidOf('cloud', 'surveys', 1);
        $good = $this->event('manufacturers', $this->newUuid(), ['name' => 'Good one']);
        $foreign = $this->event('vessel_surveys', $this->newUuid(), ['vessel_id' => $otherVessel, 'survey_id' => $survey, 'society_name' => 'X', 'survey_date' => '2026-01-01', 'survey_exp_date' => '2027-01-01', 'created_by' => $this->uuidOf('cloud', 'users', 1)]);
        $cloudOwned = $this->event('currencies', $this->newUuid(), ['code' => 'XXX', 'name' => 'Fake']);
        $malformed = ['event_id' => $this->newUuid(), 'table' => 'manufacturers', 'op' => 'insert', 'values' => ['name' => 'No uuid']];
        $alsoGood = $this->event('manufacturers', $this->newUuid(), ['name' => 'Good two']);

        $answer = $this->api('POST', 'push', $this->batch([$good, $foreign, $cloudOwned, $malformed, $alsoGood]))->assertOk()->json();

        $this->assertSame(2, $answer['counts']['applied']);
        $this->assertSame(3, $answer['counts']['rejected']);
        $reasons = array_column($answer['rejected'], 'reason');
        $this->assertStringContainsString('belongs to vessel 8', $reasons[0]);
        $this->assertStringContainsString('not accepted', $reasons[1]);
        $this->assertStringContainsString('row_uuid', $reasons[2]);
        $this->assertSame(['Good one', 'Good two'], $this->onCloud(fn () => DB::table('manufacturers')->orderBy('name')->pluck('name')->all()));
        $this->assertSame(0, $this->cloudCount('vessel_surveys'), 'the refused insert was rolled back');
        $this->assertSame(0, $this->cloudCount('currencies'));
        $this->onCloud(fn () => $this->assertSame(2, DB::table('sync_inbox')->where('status', 'failed')->count(), 'kept for an operator, with the reason'));
    }

    // ------------------------------------------------------------ waiting and retrying

    public function test_a_change_that_arrives_before_the_record_it_refers_to_waits_for_it(): void
    {
        $parent = $this->newUuid();
        $child = $this->event('item_groups', $this->newUuid(), ['name' => 'Pistons', 'path' => '1/2', 'parent_id' => $parent]);

        $first = $this->api('POST', 'push', $this->batch([$child]))->json();
        $this->assertSame(1, $first['counts']['deferred']);
        $this->assertSame(0, $this->cloudCount('item_groups'));

        $second = $this->api('POST', 'push', $this->batch([$this->event('item_groups', $parent, ['name' => 'Engine', 'path' => '1'])]))->json();
        $this->assertSame(1, $second['counts']['applied']);
        $this->assertSame(['applied' => 1, 'deferred' => 0, 'failed' => 0], $second['retried']);

        $ids = $this->onCloud(fn () => DB::table('item_groups')->pluck('parent_id', 'name')->all());
        $this->assertSame($this->onCloud(fn () => DB::table('item_groups')->where('sync_uuid', $parent)->value('id')), $ids['Pistons']);
    }

    public function test_a_change_that_keeps_waiting_is_eventually_marked_failed(): void
    {
        config(['sync.inbox.max_attempts' => 3]);
        $orphan = $this->event('item_groups', $this->newUuid(), ['name' => 'Orphan', 'path' => 'x', 'parent_id' => $this->newUuid()]);
        // Tried on arrival and again after its batch (attempts 1 and 2)...
        $first = $this->api('POST', 'push', $this->batch([$orphan]))->json();
        $this->assertSame(1, $first['retried']['deferred']);
        // ...and after the next batch (attempt 3), when it gives up.
        $last = $this->api('POST', 'push', $this->batch([]))->json();

        $this->assertSame(['applied' => 0, 'deferred' => 0, 'failed' => 1], $last['retried']);
        $this->onCloud(fn () => $this->assertStringContainsString('Gave up after 3 attempts', DB::table('sync_inbox')->value('last_error')));
    }

    public function test_a_newer_change_replaces_an_older_one_still_waiting(): void
    {
        $row = $this->newUuid();
        $missing = $this->newUuid();
        $this->api('POST', 'push', $this->batch([$this->event('item_groups', $row, ['name' => 'Old', 'path' => 'x', 'parent_id' => $missing])]));
        $this->api('POST', 'push', $this->batch([$this->event('item_groups', $row, ['name' => 'New', 'path' => 'x', 'parent_id' => null], 'update', 2)]));

        $this->onCloud(function () {
            $this->assertSame(['skipped', 'applied'], DB::table('sync_inbox')->orderBy('id')->pluck('status')->all());
            $this->assertSame('New', DB::table('item_groups')->value('name'));
        });
    }

    // ------------------------------------------------------------ ordering

    public function test_an_event_that_commits_late_is_still_delivered(): void
    {
        $this->onShip(function () {
            $sequencer = app(OutboxSequencer::class);
            $raw = fn (int $id) => DB::table('sync_outbox')->insert([
                'id' => $id, 'event_id' => $this->newUuid(), 'table_name' => 'manufacturers', 'row_id' => (string) $id,
                'row_uuid' => $this->newUuid(), 'operation' => 'insert', 'row_version' => 1, 'created_at' => now(),
            ]);

            $raw(5);
            $raw(7);
            $sequencer->sequence();
            $readerAt = $sequencer->highWater();       // a reader has everything up to here

            $raw(6);                                     // id 6's transaction commits only now
            $sequencer->sequence();

            $this->assertSame([5 => 1, 7 => 2, 6 => 3], DB::table('sync_outbox')->orderBy('seq')->pluck('seq', 'id')->map(fn ($v) => (int) $v)->all());
            $this->assertSame([6], DB::table('sync_outbox')->where('seq', '>', $readerAt)->pluck('id')->map(fn ($v) => (int) $v)->all(),
                'a cursor over ids would have skipped id 6; the sequence number puts it after what was already read');
        });
    }
}
