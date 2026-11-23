<?php

namespace Tests\Feature\Sync\Engine;

use App\Sync\Engine\SyncWorker;
use App\Sync\SyncHealth;
use App\Sync\SyncState;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Sync\Modules\BusinessSyncTestCase;

/** Sync failure monitoring (Checkpoint 7): what needs a person, once. */
class SyncHealthTest extends BusinessSyncTestCase
{
    private function alerts(string $server): array
    {
        return $this->on($server, fn () => app(SyncHealth::class)->alerts());
    }

    public function test_a_healthy_vessel_reports_nothing(): void
    {
        $this->assertCycleOk();

        $this->assertSame([], $this->alerts('ship'));
        $this->onShip(fn () => $this->artisan('sync:health')->expectsOutputToContain('nothing needs attention')->assertExitCode(0)->run());
    }

    public function test_being_offline_at_sea_is_only_a_warning_and_only_after_a_while(): void
    {
        $this->assertCycleOk();
        $this->network = 'down';
        foreach (range(1, 5) as $i) {
            $this->assertFalse($this->syncCycle()['ok']);
        }
        $this->assertSame([], $this->alerts('ship'), 'five failed cycles because the link is down: normal');

        $this->onShip(function () {
            $state = app(SyncState::class);
            $worker = $state->get(SyncWorker::STATE);
            $worker['last_success_at'] = time() - 80 * 3600;
            $state->put(SyncWorker::STATE, $worker);
        });
        $alerts = $this->alerts('ship');
        $this->assertSame(['warning', 'cycle'], [$alerts[0]['level'], $alerts[0]['check']]);
        $this->assertStringContainsString('No successful sync for 80 hours', $alerts[0]['message']);
    }

    public function test_a_failure_that_is_not_the_link_is_an_error_from_the_third_cycle(): void
    {
        $this->assertCycleOk();
        $this->onCloud(fn () => DB::table('sync_nodes')->where('node_id', $this->vesselNode)->update(['token_hash' => hash('sha256', 'revoked')]));

        $this->syncCycle();
        $this->syncCycle();
        $this->assertSame([], array_filter($this->alerts('ship'), fn ($a) => $a['level'] === 'error'));
        $this->syncCycle();

        $errors = array_values(array_filter($this->alerts('ship'), fn ($a) => $a['level'] === 'error'));
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('The last 3 sync cycles failed', $errors[0]['message']);
        $this->assertStringContainsString('401', $errors[0]['message']);
        $this->onShip(fn () => $this->artisan('sync:health')->assertExitCode(2)->run());
    }

    public function test_changes_that_could_not_be_applied_and_conflicts_are_listed(): void
    {
        $this->onShip(fn () => DB::table('users')->insert(['name' => 'X', 'email' => 'x@mvtest.test', 'password' => 'x', 'status' => true, 'created_at' => now(), 'updated_at' => now()]));
        $this->syncCycle();

        $cloud = $this->alerts('cloud');
        $this->assertContains('inbox', array_column($cloud, 'check'));
        $this->onCloud(fn () => $this->artisan('sync:health', ['--json' => true])->expectsOutputToContain('"status": "warning"')->assertExitCode(1)->run());
    }

    public function test_each_problem_is_logged_once(): void
    {
        $this->onShip(fn () => DB::table('users')->insert(['name' => 'X', 'email' => 'x@mvtest.test', 'password' => 'x', 'status' => true, 'created_at' => now(), 'updated_at' => now()]));
        $this->syncCycle();

        $this->onCloud(function () {
            $this->artisan('sync:health', ['--log' => true])->run();
            $this->artisan('sync:health', ['--log' => true])->run();
            $this->assertSame(1, DB::table('sync_logs')->where('action', 'health')->count());
        });
    }

    public function test_the_health_endpoint_shows_sync_alerts(): void
    {
        $this->onShip(function () {
            config(['navisuite.health.enabled' => true]);
            $body = $this->get('/health', ['REMOTE_ADDR' => '127.0.0.1'])->json();
            $this->assertArrayHasKey('sync_alerts', $body['checks']);
            $this->assertFalse($body['checks']['sync_alerts']['critical']);
        });
    }
}
