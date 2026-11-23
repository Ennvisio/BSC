<?php

namespace Tests\Feature\Sync;

use App\Sync\Models\SyncLog;
use App\Sync\Models\SyncNode;
use App\Sync\SyncCursors;
use App\Sync\SyncLogger;
use App\Sync\SyncNodes;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Concerns\BuildsRequisitionSchema;
use Tests\Concerns\BuildsSyncSchema;
use Tests\TestCase;

/** Node (device) registration, cursors, sync logs and the sync commands. */
class SyncNodesCursorsLogsTest extends TestCase
{
    use BuildsRequisitionSchema;
    use BuildsSyncSchema;

    private const PEER = '33333333-3333-4333-8333-333333333333';

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRequisitionSchema();
        $this->runSyncMigrations();
    }

    // ------------------------------------------------------------------ nodes

    public function test_a_vessel_node_gets_an_id_and_a_token_that_is_stored_only_as_a_hash(): void
    {
        ['node' => $node, 'token' => $token] = app(SyncNodes::class)->register('vessel', $this->vesselId, 'Master PC');

        $this->assertValidUuid($node->node_id);
        $this->assertSame(64, strlen($token));
        $stored = DB::table('sync_nodes')->where('node_id', $node->node_id)->first();
        $this->assertSame(hash('sha256', $token), $stored->token_hash);
        $this->assertStringNotContainsString($token, json_encode((array) $stored));
        $this->assertArrayNotHasKey('token_hash', $node->toArray());
    }

    public function test_a_node_authenticates_with_its_token_only_while_active(): void
    {
        $nodes = app(SyncNodes::class);
        ['node' => $node, 'token' => $token] = $nodes->register('vessel', $this->vesselId, 'Master PC');

        $this->assertNotNull($nodes->authenticate($node->node_id, $token));
        $this->assertNotNull($node->fresh()->last_seen_at);
        $this->assertNull($nodes->authenticate($node->node_id, 'wrong'));
        $this->assertNull($nodes->authenticate(self::PEER, $token));

        $this->assertTrue($nodes->revoke($node->node_id));
        $this->assertNull($nodes->authenticate($node->node_id, $token));
        $this->assertFalse($nodes->revoke($node->node_id));
    }

    public function test_a_vessel_has_one_active_node_unless_it_is_replaced(): void
    {
        $nodes = app(SyncNodes::class);
        ['node' => $first] = $nodes->register('vessel', $this->vesselId, 'Old PC');

        try {
            $nodes->register('vessel', $this->vesselId, 'New PC');
            $this->fail('A second active node should be refused.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('--replace', $e->getMessage());
        }

        ['node' => $second] = $nodes->register('vessel', $this->vesselId, 'New PC', replace: true);

        $this->assertSame(SyncNode::STATUS_REVOKED, $first->fresh()->status);
        $this->assertNull($first->fresh()->token_hash);
        $this->assertTrue($second->isActive());
    }

    public function test_node_registration_checks_its_input(): void
    {
        $nodes = app(SyncNodes::class);

        foreach ([['vessel', null], ['vessel', 999], ['cloud', $this->vesselId], ['client', null]] as [$kind, $vessel]) {
            try {
                $nodes->register($kind, $vessel, 'x');
                $this->fail("{$kind}/{$vessel} should be refused.");
            } catch (RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(0, DB::table('sync_nodes')->count());
    }

    public function test_this_servers_node_id_comes_from_config(): void
    {
        config(['sync.node_id' => ' '.strtoupper(self::PEER).' ']);
        $this->assertSame(self::PEER, app(SyncNodes::class)->thisNodeId());

        config(['sync.node_id' => 'not-a-uuid']);
        $this->assertNull(app(SyncNodes::class)->thisNodeId());
    }

    // ---------------------------------------------------------------- cursors

    public function test_a_cursor_only_moves_forward(): void
    {
        $cursors = app(SyncCursors::class);

        $this->assertSame(0, $cursors->position(self::PEER, 'pull'));
        $this->assertTrue($cursors->advance(self::PEER, 'pull', 10));
        $this->assertTrue($cursors->advance(self::PEER, 'pull', 25));
        $this->assertFalse($cursors->advance(self::PEER, 'pull', 25));
        $this->assertFalse($cursors->advance(self::PEER, 'pull', 12), 'a late acknowledgement must not move it back');

        $this->assertSame(25, $cursors->position(self::PEER, 'pull'));
        $this->assertSame(0, $cursors->position(self::PEER, 'push'), 'streams are independent');
        $this->assertSame(1, DB::table('sync_cursors')->count());
    }

    // ------------------------------------------------------------------- logs

    public function test_sync_actions_are_logged(): void
    {
        app(SyncLogger::class)->record('push', 'ok', ['node_id' => self::PEER, 'events' => 12, 'bytes' => 2048, 'duration_ms' => 350, 'context' => ['batch' => 1]]);

        $log = SyncLog::sole();
        $this->assertSame(['push', 'ok', 12, 2048, 350, ['batch' => 1]], [$log->action, $log->status, $log->events, $log->bytes, $log->duration_ms, $log->context]);
        $this->assertNotNull($log->created_at);
    }

    // --------------------------------------------------------------- commands

    public function test_registering_a_node_prints_its_credentials_once(): void
    {
        $this->artisan("sync:register-node vessel --vessel={$this->vesselId} --name=Bridge")
            ->expectsOutputToContain('SYNC_NODE_ID=')
            ->expectsOutputToContain('SYNC_NODE_TOKEN=')
            ->assertSuccessful();

        $node = SyncNode::sole();
        $this->assertSame(['vessel', $this->vesselId, 'Bridge'], [$node->kind, $node->vessel_id, $node->name]);
        $this->assertSame('register-node', SyncLog::sole()->action);

        $this->artisan("sync:revoke-node {$node->node_id}")->assertSuccessful();
        $this->assertFalse($node->fresh()->isActive());
    }

    public function test_nodes_are_only_managed_on_the_cloud(): void
    {
        config(['navisuite.mode' => 'vessel', 'navisuite.vessel_id' => $this->vesselId]);

        $this->artisan("sync:register-node vessel --vessel={$this->vesselId}")->assertFailed();
        $this->artisan('sync:revoke-node '.self::PEER)->assertFailed();
        $this->assertSame(0, DB::table('sync_nodes')->count());
    }

    public function test_the_trigger_command_installs_reports_and_removes_capture(): void
    {
        $this->artisan('sync:triggers status')->assertFailed();
        $this->artisan('sync:triggers install')->expectsOutputToContain('Change capture is installed')->assertSuccessful();
        $this->artisan('sync:triggers status')->expectsOutputToContain('installed on every synchronised table')->assertSuccessful();
        $this->artisan('sync:triggers drop')->assertSuccessful();
        $this->artisan('sync:triggers bogus')->assertFailed();
    }

    public function test_backfill_fills_rows_written_without_triggers_and_records_nothing(): void
    {
        DB::table('budget_groups')->insert(['name' => 'Written before capture', 'status' => true, 'created_by' => 1]);
        $this->assertNull(DB::table('budget_groups')->where('name', 'Written before capture')->value('sync_uuid'));
        $this->installSyncTriggers();
        $this->clearOutbox();

        $this->artisan('sync:backfill-uuids')->expectsOutputToContain('Rows given a sync UUID: 1.')->assertSuccessful();

        $this->assertValidUuid(DB::table('budget_groups')->where('name', 'Written before capture')->value('sync_uuid'));
        $this->assertCount(0, $this->outbox());
        $this->artisan('sync:backfill-uuids')->expectsOutputToContain('Rows given a sync UUID: 0.')->assertSuccessful();
    }

    public function test_installing_fails_cleanly_without_the_sync_columns(): void
    {
        $this->syncMigration(self::SYNC_MIGRATIONS[1])->down();

        $this->artisan('sync:triggers install')->expectsOutputToContain('Run "php artisan migrate" first.')->assertFailed();
        $this->assertSame([], app(\App\Sync\TriggerManager::class)->installedTriggerNames());
    }

    public function test_status_reports_readiness(): void
    {
        $this->installSyncTriggers();

        // This partial schema has a stand-in pms_used_parts without pms_jobs,
        // which its vessel lookup reads: it must be reported, not captured.
        $this->artisan('sync:status')->expectsOutputToContain('pms_used_parts')->assertFailed();

        \Illuminate\Support\Facades\Schema::drop('pms_used_parts');
        $this->artisan('sync:status')->expectsOutputToContain('Tables with change capture')->assertSuccessful();

        config(['navisuite.mode' => 'vessel', 'navisuite.vessel_id' => $this->vesselId, 'sync.node_id' => null]);
        $this->artisan('sync:status')->expectsOutputToContain('SYNC_NODE_ID is missing')->assertFailed();
    }
}
