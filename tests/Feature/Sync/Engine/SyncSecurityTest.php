<?php

namespace Tests\Feature\Sync\Engine;

use App\Sync\Models\SyncNode;
use App\Sync\SyncNodes;
use App\Sync\SyncState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Who may talk to the sync API, and what a vessel may change through it.
 * A vessel node is trusted with its own vessel's records and the shared
 * lists it co-authors; nothing else.
 */
class SyncSecurityTest extends SyncEngineTestCase
{
    public function test_a_known_node_with_its_token_is_let_in(): void
    {
        $this->api('POST', 'handshake', ['protocol' => 1, 'vessel_id' => $this->vesselId])
            ->assertOk()
            ->assertJsonPath('vessel_id', $this->vesselId)
            ->assertJsonPath('cloud_node_id', $this->cloudNode);
    }

    public function test_missing_or_wrong_credentials_are_refused(): void
    {
        $this->api('GET', 'pull?after=0', headers: ['Authorization' => null])->assertStatus(401);
        $this->api('GET', 'pull?after=0', headers: ['Authorization' => 'Bearer '.Str::random(64)])->assertStatus(401);
        $this->api('GET', 'pull?after=0', headers: ['X-Sync-Node' => null])->assertStatus(401);
        $this->api('GET', 'pull?after=0', headers: ['X-Sync-Node' => (string) Str::uuid()])->assertStatus(401);
        $this->api('GET', 'pull?after=0', headers: ['X-Sync-Node' => 'not-a-uuid'])->assertStatus(401);
    }

    public function test_a_revoked_node_is_refused_even_with_its_old_token(): void
    {
        $this->onCloud(fn () => app(SyncNodes::class)->revoke($this->vesselNode));

        $this->api('GET', 'pull?after=0')->assertStatus(401);
        $this->assertFalse($this->syncCycle()['ok']);
    }

    public function test_a_replaced_master_pc_can_no_longer_sync_but_the_new_one_can(): void
    {
        $new = $this->onCloud(fn () => app(SyncNodes::class)->register('vessel', $this->vesselId, 'New master PC', true));

        $this->api('GET', 'pull?after=0')->assertStatus(401);
        $this->api('GET', 'pull?after=0', headers: ['X-Sync-Node' => $new['node']->node_id, 'Authorization' => 'Bearer '.$new['token']])->assertOk();
    }

    public function test_only_vessel_nodes_may_sync(): void
    {
        $token = Str::random(64);
        $this->onCloud(fn () => SyncNode::create([
            'node_id' => $id = (string) Str::uuid(), 'kind' => SyncNode::KIND_CLOUD, 'name' => 'Second cloud',
            'token_hash' => hash('sha256', $token), 'status' => SyncNode::STATUS_ACTIVE,
        ]));

        $id = $this->onCloud(fn () => SyncNode::query()->where('name', 'Second cloud')->value('node_id'));
        $this->api('GET', 'pull?after=0', headers: ['X-Sync-Node' => $id, 'Authorization' => 'Bearer '.$token])->assertStatus(403);
    }

    public function test_repeated_wrong_tokens_are_slowed_down(): void
    {
        foreach (range(1, 10) as $i) {
            $this->api('GET', 'pull?after=0', headers: ['Authorization' => 'Bearer wrong'])->assertStatus(401);
        }

        $this->api('GET', 'pull?after=0', headers: ['Authorization' => 'Bearer wrong'])->assertStatus(429);
        $this->api('GET', 'pull?after=0')->assertStatus(429, 'the right token waits too, so guessing gains nothing');
    }

    public function test_the_api_does_not_exist_on_a_vessel_server(): void
    {
        $response = $this->onShip(fn () => $this->call('GET', '/api/sync/v1/pull?after=0', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->vesselToken, 'HTTP_X_SYNC_NODE' => $this->vesselNode, 'HTTP_X_SYNC_PROTOCOL' => '1',
        ]));

        $response->assertNotFound();
    }

    public function test_the_api_is_closed_until_the_cloud_has_its_own_node_id(): void
    {
        $response = $this->onCloud(function () {
            config(['sync.node_id' => null]);

            return $this->call('GET', '/api/sync/v1/pull?after=0', [], [], [], [
                'HTTP_AUTHORIZATION' => 'Bearer '.$this->vesselToken, 'HTTP_X_SYNC_NODE' => $this->vesselNode, 'HTTP_X_SYNC_PROTOCOL' => '1',
            ]);
        });

        $response->assertStatus(503);
    }

    public function test_an_unknown_protocol_version_is_refused(): void
    {
        $this->api('GET', 'pull?after=0', headers: ['X-Sync-Protocol' => '2'])->assertStatus(400);
        $this->api('GET', 'pull?after=0', headers: ['X-Sync-Protocol' => null])->assertStatus(400);
    }

    public function test_a_damaged_upload_is_refused_whole(): void
    {
        $batch = $this->batch([$this->event('manufacturers', $this->newUuid(), ['name' => 'Truncated'])]);

        $this->api('POST', 'push', $batch, ['X-Sync-Body-SHA256' => hash('sha256', 'something else')])->assertStatus(400);
        $this->api('POST', 'push', $batch, ['X-Sync-Body-SHA256' => null])->assertStatus(400);

        $json = json_encode($batch);
        $this->api('POST', 'push', null, ['Content-Encoding' => 'gzip', 'X-Sync-Body-SHA256' => hash('sha256', $json), 'Content-Type' => 'application/json'], 'cut short')
            ->assertStatus(400);
        $gz = gzencode($json);
        $this->api('POST', 'push', null, ['Content-Encoding' => 'gzip', 'X-Sync-Body-SHA256' => hash('sha256', $json), 'Content-Type' => 'application/json'], substr($gz, 0, (int) (strlen($gz) * 0.7)), alreadyEncoded: true)
            ->assertStatus(400)->assertJsonPath('message', 'The request body is not valid gzip.');

        config(['sync.http.max_request_bytes' => 100]);
        $this->api('POST', 'push', $batch)->assertStatus(413);

        $this->assertSame(0, $this->cloudCount('manufacturers'));
        $this->assertSame(0, $this->onCloud(fn () => DB::table('sync_batches')->count()));
    }

    public function test_a_malformed_batch_or_query_is_refused(): void
    {
        $this->api('POST', 'push', ['batch_id' => 'x', 'events' => []])->assertStatus(422);
        $this->api('POST', 'push', $this->batch([], null, 5, 2))->assertStatus(422);
        config(['sync.batch.max_events' => 2]);
        $this->api('POST', 'push', $this->batch(array_map(fn () => $this->event('manufacturers', $this->newUuid(), ['name' => Str::random()]), range(1, 3))))->assertStatus(422);
        $this->api('GET', 'pull?after=-1')->assertStatus(422);
        $this->api('GET', 'pull?after=abc')->assertStatus(422);
        $this->api('POST', 'ack', ['stream' => 'push', 'cursor' => 1])->assertStatus(422);
    }

    public function test_a_vessel_cannot_change_cloud_owned_reference_data(): void
    {
        $eur = $this->addCurrency('EUR', 'Euro');
        $answer = $this->api('POST', 'push', $this->batch([
            $this->event('currencies', $eur, ['code' => 'EUR', 'name' => 'Hijacked'], 'update', 9),
        ]))->assertOk()->json();

        $this->assertSame(1, $answer['counts']['rejected']);
        $this->assertSame('Euro', $this->row('cloud', 'currencies', $eur)->name);
    }

    public function test_a_vessel_cannot_touch_another_vessels_records(): void
    {
        $theirs = $this->onCloud(function () {
            $id = DB::table('vessel_surveys')->insertGetId(['vessel_id' => 8, 'survey_id' => 1, 'society_name' => 'Theirs', 'survey_date' => '2026-01-01', 'survey_exp_date' => '2027-01-01', 'created_by' => 1]);

            return DB::table('vessel_surveys')->where('id', $id)->value('sync_uuid');
        });

        $answer = $this->api('POST', 'push', $this->batch([
            $this->event('vessel_surveys', $theirs, $this->survey($this->vesselId, 'Taken over'), 'update', 5),
            $this->event('vessel_surveys', $theirs, null, 'delete', 6),
        ]))->assertOk()->json();

        $this->assertSame(2, $answer['counts']['rejected']);
        $this->assertSame('Theirs', $this->row('cloud', 'vessel_surveys', $theirs)->society_name);
        $this->assertSame(8, (int) $this->row('cloud', 'vessel_surveys', $theirs)->vessel_id);
    }

    public function test_a_vessel_cannot_move_its_own_record_to_another_vessel(): void
    {
        $mine = $this->newUuid();
        $this->api('POST', 'push', $this->batch([$this->event('vessel_surveys', $mine, $this->survey($this->vesselId, 'Ours'))]))
            ->assertOk()->assertJsonPath('counts.applied', 1);

        $answer = $this->api('POST', 'push', $this->batch([
            $this->event('vessel_surveys', $mine, $this->survey(8, 'Moved'), 'update', 2),
        ], null, 2, 2))->assertOk()->json();

        $this->assertSame(1, $answer['counts']['rejected']);
        $this->assertSame($this->vesselId, (int) $this->row('cloud', 'vessel_surveys', $mine)->vessel_id);
    }

    public function test_a_batch_id_belongs_to_the_node_that_sent_it_first(): void
    {
        $id = $this->newUuid();
        $this->api('POST', 'push', $this->batch([$this->event('manufacturers', $this->newUuid(), ['name' => 'Mine'])], $id))->assertOk();

        $other = $this->onCloud(fn () => app(SyncNodes::class)->register('vessel', 8, 'MV OTHER master PC'));
        $this->api('POST', 'push', $this->batch([], $id), ['X-Sync-Node' => $other['node']->node_id, 'Authorization' => 'Bearer '.$other['token']])
            ->assertStatus(409);
    }

    public function test_positions_beyond_the_end_of_the_stream_are_refused(): void
    {
        $this->api('GET', 'pull?after=999')->assertStatus(409)->assertJsonFragment(['message' => "The vessel is at position 999, but the cloud's stream ends at ".$this->onCloud(fn () => (int) DB::table('sync_outbox')->max('seq')).'. Was the cloud database restored from a backup?']);
        $this->api('POST', 'ack', ['stream' => 'pull', 'cursor' => 999])->assertStatus(409);
    }

    public function test_the_vessel_stops_if_the_cloud_says_it_is_a_different_vessel(): void
    {
        $this->onCloud(fn () => SyncNode::query()->where('node_id', $this->vesselNode)->update(['vessel_id' => 8]));
        $this->addManufacturer('ship', 'Must not leave');

        $result = $this->syncCycle();

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('vessel 8', $result['error']);
        $this->assertSame(['/api/sync/v1/handshake'], array_column($this->requests, 'path'));
        $this->assertSame(0, $this->cloudCount('manufacturers'));
    }

    public function test_the_vessel_stops_if_the_cloud_changes_identity(): void
    {
        $this->assertCycleOk();
        $this->requests = [];
        $this->onShip(fn () => app(SyncState::class)->put('cloud.node_id', (string) Str::uuid()));
        $this->addManufacturer('ship', 'Must not leave');

        $result = $this->syncCycle();

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('identity changed', $result['error']);
        $this->assertSame(['/api/sync/v1/handshake'], array_column($this->requests, 'path'));
        $this->assertSame(0, $this->cloudCount('manufacturers'));
    }

    public function test_tokens_are_not_stored_or_logged_in_clear(): void
    {
        $this->addManufacturer('ship', 'Logged');
        $this->assertCycleOk();

        foreach (['cloud', 'ship'] as $server) {
            $this->on($server, function () {
                foreach (['sync_nodes', 'sync_logs', 'sync_batches', 'sync_state'] as $table) {
                    $dump = json_encode(DB::table($table)->get());
                    $this->assertStringNotContainsString($this->vesselToken, $dump, "token found in {$table}");
                }
            });
        }
    }

    private function survey(int $vessel, string $society): array
    {
        return [
            'vessel_id' => $this->uuidOf('cloud', 'vessels', $vessel), 'survey_id' => $this->uuidOf('cloud', 'surveys', 1),
            'society_name' => $society, 'survey_date' => '2026-01-01', 'survey_exp_date' => '2027-01-01',
            'created_by' => $this->uuidOf('cloud', 'users', 2),
        ];
    }
}
