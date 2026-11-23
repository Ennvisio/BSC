<?php

namespace Tests\Feature\Sync\Engine;

use App\Sync\Engine\OutboxSequencer;
use App\Sync\Engine\ReplicationRules;
use App\Sync\Engine\SyncServer;
use App\Sync\Models\SyncNode;
use App\Sync\SyncCursors;
use App\Sync\SyncNodes;
use Illuminate\Support\Facades\DB;

/** Housekeeping commands: prune, retry, resend, register, and the rule check. */
class SyncMaintenanceTest extends SyncEngineTestCase
{
    public function test_prune_keeps_everything_not_yet_delivered(): void
    {
        $this->addManufacturer('ship', 'Delivered');
        $this->assertCycleOk();
        $this->network = 'down';
        $waiting = $this->addManufacturer('ship', 'Still at sea');
        $this->syncCycle();

        $this->travel(20)->days();
        $before = $this->onShip(fn () => DB::table('sync_outbox')->count());

        $dry = $this->onShip(fn () => app(\App\Sync\Engine\SyncMaintenance::class)->prune(true));
        $this->assertSame(1, $dry['outbox']);
        $this->assertSame($before, $this->onShip(fn () => DB::table('sync_outbox')->count()), 'a dry run deletes nothing');

        $this->onShip(fn () => $this->artisan('sync:prune')->assertSuccessful()->run());

        $this->onShip(function () use ($waiting) {
            $this->assertSame([$waiting], DB::table('sync_outbox')->pluck('row_uuid')->all(), 'only the delivered change was removed');
            $this->assertSame(0, DB::table('sync_logs')->where('created_at', '<', now()->subDays(90))->count());
        });

        $this->network = 'up';
        $this->assertCycleOk();
        $this->assertSame('Still at sea', $this->row('cloud', 'manufacturers', $waiting)->name);
    }

    public function test_numbering_carries_on_after_everything_was_pruned(): void
    {
        $this->addCurrency('EUR', 'Euro');
        $this->addManufacturer('ship', 'Ship');
        $this->assertCycleOk();
        $this->travel(20)->days();
        foreach (['cloud', 'ship'] as $server) {
            $this->on($server, fn () => $this->artisan('sync:prune')->assertSuccessful()->run());
            // Changes received from the other side are numbered on the next cycle, so only local ones are gone yet.
            $this->assertSame(0, $this->on($server, fn () => DB::table('sync_outbox')->whereNull('origin_node')->count()), "{$server}: delivered local changes pruned");
        }

        $usd = $this->addCurrency('USD', 'US Dollar');
        $more = $this->addManufacturer('ship', 'After the prune');
        $this->assertCycleOk();

        $this->assertNotNull($this->row('ship', 'currencies', $usd), 'the vessel is not mistaken for being ahead of the cloud');
        $this->assertNotNull($this->row('cloud', 'manufacturers', $more));
        $this->assertGreaterThan(1, $this->onCloud(fn () => (int) DB::table('sync_outbox')->min('seq')));
    }

    public function test_the_cloud_keeps_changes_a_vessel_has_not_pulled_yet(): void
    {
        $this->assertCycleOk();
        $this->onCloud(fn () => app(SyncNodes::class)->register('vessel', 8, 'MV OTHER master PC'));
        $this->addCurrency('EUR', 'Euro');
        $this->assertCycleOk(); // vessel 7 has it, vessel 8 has not pulled

        $this->travel(20)->days();
        $this->onCloud(fn () => $this->artisan('sync:prune')->assertSuccessful()->run());

        $this->assertSame(1, $this->onCloud(fn () => DB::table('sync_outbox')->where('table_name', 'currencies')->count()));
    }

    public function test_prune_keeps_failed_changes_and_conflicts_but_removes_old_applied_ones(): void
    {
        config(['sync.inbox.max_attempts' => 1]);
        $this->api('POST', 'push', $this->batch([
            $this->event('manufacturers', $this->newUuid(), ['name' => 'Applied']),
            $this->event('item_groups', $this->newUuid(), ['name' => 'Orphan', 'path' => 'x', 'parent_id' => $this->newUuid()]),
        ]))->assertOk();
        $this->onCloud(fn () => DB::table('sync_conflicts')->insert(['event_id' => $this->newUuid(), 'source_node' => $this->vesselNode, 'detected_by' => 'cloud', 'table_name' => 'manufacturers', 'row_uuid' => $this->newUuid(), 'kind' => 'update_update', 'resolution' => 'kept_local', 'status' => 'resolved', 'created_at' => now()]));

        $this->travel(200)->days();
        $this->onCloud(fn () => $this->artisan('sync:prune')->assertSuccessful()->run());

        $this->onCloud(function () {
            $this->assertSame(['failed'], DB::table('sync_inbox')->pluck('status')->all());
            $this->assertSame(1, DB::table('sync_conflicts')->count());
            $this->assertSame(0, DB::table('sync_batches')->count());
        });
    }

    public function test_a_change_that_gave_up_can_be_retried_once_the_cause_is_fixed(): void
    {
        config(['sync.inbox.max_attempts' => 1]);
        $parent = $this->newUuid();
        $child = $this->newUuid();
        $this->api('POST', 'push', $this->batch([$this->event('item_groups', $child, ['name' => 'Pistons', 'path' => '1/2', 'parent_id' => $parent])]))->assertOk();
        $this->api('POST', 'push', $this->batch([$this->event('item_groups', $parent, ['name' => 'Engine', 'path' => '1'])]))->assertOk();
        $this->assertNull($this->row('cloud', 'item_groups', $child), 'failed changes are not retried automatically');

        $this->onCloud(fn () => $this->artisan('sync:retry')->assertSuccessful()->run());
        $this->assertNull($this->row('cloud', 'item_groups', $child), 'nor without --failed');

        $this->onCloud(fn () => $this->artisan('sync:retry', ['--failed' => true])->assertSuccessful()->run());
        $this->assertSame($this->row('cloud', 'item_groups', $parent)->id, $this->row('cloud', 'item_groups', $child)->parent_id);
        $this->assertSame(['applied'], $this->onCloud(fn () => DB::table('sync_inbox')->where('row_uuid', $child)->pluck('status')->all()));
    }

    public function test_a_table_enabled_late_is_sent_in_full_with_resend(): void
    {
        config(['sync.enabled_tables' => 'currencies']);
        $early = $this->addManufacturer('ship', 'Made before the table was synced');
        $this->assertCycleOk();

        config(['sync.enabled_tables' => 'currencies,manufacturers']);
        $this->assertCycleOk();
        $this->assertNull($this->row('cloud', 'manufacturers', $early), 'the cursor already moved past it');

        $this->onShip(fn () => $this->artisan('sync:resend', ['table' => 'manufacturers', '--force' => true])->expectsOutputToContain('1')->assertSuccessful()->run());
        $this->assertCycleOk();
        $this->assertSame('Made before the table was synced', $this->row('cloud', 'manufacturers', $early)->name);

        // Sending again is harmless: the cloud sees nothing new.
        $this->onShip(fn () => $this->artisan('sync:resend', ['table' => 'manufacturers', '--force' => true])->assertSuccessful()->run());
        $result = $this->assertCycleOk();
        $this->assertSame(1, $result['summary']['pushed']['events']);
        $this->assertSame(0, $this->onCloud(fn () => DB::table('sync_conflicts')->count()));
        $this->assertConverged('manufacturers', ['name', 'updated_at', 'sync_version']);
    }

    public function test_resend_from_the_cloud_can_be_limited_to_one_vessel(): void
    {
        $this->onCloud(function () {
            foreach ([$this->vesselId, 8] as $vessel) {
                DB::table('vessel_surveys')->insert(['vessel_id' => $vessel, 'survey_id' => 1, 'society_name' => "Class of {$vessel}", 'survey_date' => '2026-01-01', 'survey_exp_date' => '2027-01-01', 'created_by' => 1]);
            }
        });
        $this->assertCycleOk();
        $this->onShip(fn () => DB::table('vessel_surveys')->delete()); // e.g. lost on board

        $this->onCloud(function () {
            $this->artisan('sync:resend', ['table' => 'vessel_surveys', '--vessel' => $this->vesselId, '--force' => true])->assertSuccessful()->run();
            $this->assertSame(1, DB::table('sync_outbox')->where('table_name', 'vessel_surveys')->whereNull('seq')->count());
            $this->artisan('sync:resend', ['table' => 'manufacturers', '--vessel' => 7, '--force' => true])->assertFailed()->run();
            $this->artisan('sync:resend', ['table' => 'sync_nodes', '--force' => true])->assertFailed()->run();
        });
    }

    public function test_a_new_vessel_node_starts_reading_at_the_current_end_of_the_stream(): void
    {
        $this->addCurrency('EUR', 'Euro');
        $this->addCurrency('USD', 'US Dollar');

        $this->onCloud(function () {
            $this->artisan('sync:register-node', ['kind' => 'vessel', '--vessel' => 8, '--name' => 'MV OTHER'])->assertSuccessful()->run();
            $node = SyncNode::query()->where('vessel_id', 8)->sole();
            $high = app(OutboxSequencer::class)->highWater();

            $this->assertGreaterThan(0, $high);
            $this->assertSame($high, app(SyncCursors::class)->position($node->node_id, SyncServer::STREAM_PULL), 'its copy of the database already holds everything before this point');
        });
    }

    public function test_status_shows_the_backlog_on_board_and_the_lag_per_vessel_on_the_cloud(): void
    {
        $this->addManufacturer('ship', 'Sent');
        $this->assertCycleOk();
        $this->network = 'down';
        $this->addManufacturer('ship', 'Waiting one');
        $this->addManufacturer('ship', 'Waiting two');
        $this->syncCycle();
        $this->addCurrency('EUR', 'Euro');

        $this->onShip(fn () => $this->artisan('sync:status')
            ->expectsTable(['Item', 'Value'], [
                ['Cloud address', self::CLOUD_URL],
                ['Cloud node', $this->cloudNode],
                ['Last successful sync', date('Y-m-d H:i:s', app(\App\Sync\Engine\SyncWorker::class)->status()['last_success_at'])],
                ['Failures in a row', 1],
                ['Last error', app(\App\Sync\Engine\SyncWorker::class)->status()['last_error']],
                ['Next attempt', date('Y-m-d H:i:s', app(\App\Sync\Engine\SyncWorker::class)->status()['next_attempt_at'])],
                ['Local changes waiting to be sent', 2],
                ['Batch sent, not yet confirmed', '-'],
                ['Received up to (cloud position)', app(SyncCursors::class)->position($this->cloudNode, 'pull')],
                ['Received changes that failed', 0],
            ])
            ->assertSuccessful()->run());

        $this->onCloud(function () {
            $this->artisan('sync:status')->expectsOutputToContain('Behind')->expectsOutputToContain('MV TEST master PC')->assertSuccessful()->run();
            config(['sync.enabled_tables' => 'currencies,requisitions']);
            $this->artisan('sync:status')->expectsOutputToContain('Replication: SYNC_TABLES lists "requisitions"')->assertFailed()->run();
        });

        $this->onShip(function () {
            config(['sync.node_token' => '']);
            $this->artisan('sync:status')->expectsOutputToContain('Sync: The sync token is not set')->assertFailed()->run();
        });
    }

    public function test_a_replaced_cloud_is_refused_until_the_vessel_is_reset(): void
    {
        $this->addManufacturer('ship', 'Before');
        $this->assertCycleOk();

        // The office moves to a new cloud server with a new identity.
        $this->cloudNode = $this->onCloud(fn () => app(SyncNodes::class)->register('cloud', null, 'New cloud', true)['node']->node_id);
        $after = $this->addManufacturer('ship', 'After the move');
        $this->assertStringContainsString('identity changed', $this->syncCycle()['error']);

        $this->onShip(fn () => $this->artisan('sync:reset-cloud', ['--force' => true])->assertSuccessful()->run());
        $this->assertCycleOk();

        $this->assertSame('After the move', $this->row('cloud', 'manufacturers', $after)->name);
        $this->assertSame($this->cloudNode, $this->onShip(fn () => app(\App\Sync\SyncState::class)->get('cloud.node_id')));
        $this->assertSame(0, $this->onCloud(fn () => DB::table('sync_conflicts')->count()), 'the rows sent again are recognised');
        $this->assertConverged('manufacturers', ['name', 'sync_version']);
    }

    public function test_a_cloud_restored_from_a_backup_is_caught_up_after_a_reset(): void
    {
        $this->addCurrency('EUR', 'Euro');
        $this->assertCycleOk();
        // The restored cloud's stream ends before the position the vessel reached.
        $this->onShip(fn () => app(SyncCursors::class)->advance($this->cloudNode, 'pull', 999));
        $this->addManufacturer('ship', 'Made at sea');

        $failed = $this->syncCycle();
        $this->assertStringContainsString('restored from a backup', $failed['error']);

        $this->onShip(fn () => $this->artisan('sync:reset-cloud', ['--force' => true])->assertSuccessful()->run());
        $usd = $this->addCurrency('USD', 'US Dollar');
        $this->assertCycleOk();

        $this->assertNotNull($this->row('ship', 'currencies', $usd));
        $this->assertSame(1, $this->cloudCount('manufacturers'));
        $this->onCloud(fn () => $this->artisan('sync:reset-cloud', ['--force' => true])->assertFailed()->run());
    }

    public function test_the_shipped_replication_rules_are_consistent(): void
    {
        config(['sync' => require config_path('sync.php')]);
        $rules = app(ReplicationRules::class);
        $this->assertSame([], $rules->definitionErrors());

        config(['sync.enabled_tables' => implode(',', array_keys($rules->defined()))]);
        $this->assertSame([], $rules->definitionErrors(), 'every rule may be switched on');

        config(['sync.enabled_tables' => 'currencies,ranks,categories,@master,@inventory,@pms,@requisitions']);
        $this->assertSame([], $rules->definitionErrors(), 'the documented module setting');
        $this->assertSame([], $rules->warnings(), 'every table a module refers to travels with it');
        $this->assertContains('orders', $rules->upTables());
        $this->assertContains('users', $rules->downTables());

        config(['sync.enabled_tables' => '@pms']);
        $this->assertContains('"pms_jobs.approved_by" refers to "users", which is not enabled.', $rules->warnings());

        config(['sync.enabled_tables' => 'currencies,requisitions,@nothing', 'sync.replication.manufacturers.conflict' => 'newest']);
        $errors = $rules->definitionErrors();
        $this->assertContains('SYNC_TABLES lists "requisitions", which has no replication rule.', $errors);
        $this->assertContains('"manufacturers" needs a conflict rule of cloud, vessel or latest.', $errors);
        $this->assertContains('SYNC_TABLES names module "@nothing", which config/sync.php \'modules\' does not define.', $errors);
    }
}
