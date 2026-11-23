<?php

namespace Tests\Feature\Sync\Engine;

use App\Support\HealthCheck;
use App\Support\Heartbeat;
use App\Sync\Engine\SyncAgent;
use App\Sync\Engine\SyncWorker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** The background worker: when it syncs, how it backs off, and what it reports. */
class SyncWorkerTest extends SyncEngineTestCase
{
    public function test_the_wait_after_failures_doubles_up_to_half_an_hour(): void
    {
        $worker = app(SyncWorker::class);

        $this->assertSame([30, 60, 120, 240, 480, 960, 1800, 1800, 1800], array_map(fn ($n) => $worker->delay($n), range(1, 9)));
        $this->assertSame(1800, $worker->delay(1000), 'no overflow after a very long outage');
    }

    public function test_jitter_spreads_retries_by_at_most_a_fifth_and_never_past_the_cap(): void
    {
        config(['sync.worker.jitter' => 0.2]);
        $worker = app(SyncWorker::class);

        $this->assertSame(24, $worker->delay(1, 0.0));
        $this->assertSame(30, $worker->delay(1, 0.5));
        $this->assertSame(36, $worker->delay(1, 1.0));
        $this->assertSame(1440, $worker->delay(9, 0.0));
        $this->assertSame(1800, $worker->delay(9, 1.0));
        foreach (range(1, 200) as $i) {
            $d = $worker->delay(3);
            $this->assertTrue($d >= 96 && $d <= 144, "{$d} outside 120 +/- 20%");
        }
    }

    public function test_failures_are_counted_and_the_schedule_survives_a_restart(): void
    {
        $this->travelTo('2026-11-20 10:00:00');
        $this->network = 'down';

        $first = $this->syncCycle();
        $this->assertSame([false, 1, 30], [$first['ok'], $first['failures'], $first['retry_in']]);
        $this->assertStringContainsString('Satellite link down', $first['error']);

        // A new process (service restart, Windows reboot) reads the same schedule.
        $this->app->forgetInstance(SyncWorker::class);
        $this->onShip(function () {
            $worker = app(SyncWorker::class);
            $this->assertSame(1, $worker->status()['failures']);
            $this->assertFalse($worker->isDue(), 'not before the back-off has passed');
            $this->assertTrue($worker->isDue(now()->addSeconds(30)->getTimestamp()));
        });

        $this->travel(30)->seconds();
        $this->assertSame(60, $this->syncCycle()['retry_in']);

        $this->network = 'up';
        $this->travel(60)->seconds();
        $this->assertCycleOk();

        $this->onShip(function () {
            $status = app(SyncWorker::class)->status();
            $this->assertSame(0, $status['failures']);
            $this->assertNull($status['last_error']);
            $this->assertSame(now()->getTimestamp(), $status['last_success_at']);
            $this->assertSame(now()->addSeconds(300)->getTimestamp(), $status['next_attempt_at'], 'back to the normal interval');
            $this->assertArrayHasKey('pushed', $status['last_summary']);
        });
    }

    public function test_offline_and_real_errors_are_logged_differently(): void
    {
        $this->network = 'down';
        $this->syncCycle();

        $this->network = 'up';
        $this->onCloud(fn () => DB::table('sync_nodes')->where('node_id', $this->vesselNode)->update(['vessel_id' => 8]));
        $this->syncCycle();

        $this->onCloud(fn () => DB::table('sync_nodes')->where('node_id', $this->vesselNode)->update(['vessel_id' => $this->vesselId]));
        $this->assertCycleOk();

        $this->onShip(function () {
            $cycles = DB::table('sync_logs')->where('action', 'cycle')->orderBy('id')->get(['status', 'message']);
            $this->assertSame(['warning', 'error', 'ok'], $cycles->pluck('status')->all());
            $this->assertStringStartsWith('Offline.', $cycles[0]->message);
            $this->assertStringContainsString('vessel 8', $cycles[1]->message);
        });
    }

    public function test_sync_now_runs_before_the_interval_is_up(): void
    {
        $this->assertCycleOk();
        $this->onShip(function () {
            $worker = app(SyncWorker::class);
            $this->assertFalse($worker->isDue());

            $this->artisan('sync:run', ['--background' => true])->assertSuccessful()->run();

            $this->assertTrue(app(SyncWorker::class)->isDue());
        });

        $this->assertCycleOk();
        $this->assertFalse($this->onShip(fn () => app(SyncWorker::class)->status()['run_now']), 'the request is used up');
    }

    public function test_two_cycles_never_overlap(): void
    {
        $this->addManufacturer('ship', 'Waiting');
        $lock = $this->onShip(fn () => Cache::lock(SyncAgent::LOCK, 60));
        $this->assertTrue($lock->get());

        $result = $this->syncCycle();

        $this->assertTrue($result['ok']);
        $this->assertSame('Another sync cycle is running.', $result['skipped']);
        $this->assertSame([], $this->requests);
        $lock->release();
        $this->assertCycleOk();
        $this->assertSame(1, $this->cloudCount('manufacturers'));
    }

    public function test_a_server_that_is_not_set_up_does_not_call_the_cloud(): void
    {
        $result = $this->onShip(function () {
            config(['sync.node_token' => '', 'sync.cloud_url' => 'ftp://cloud.test']);

            return app(SyncWorker::class)->runCycle();
        });

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('The sync token is not set', $result['error']);
        $this->assertStringContainsString('SYNC_CLOUD_URL', $result['error']);
        $this->assertSame([], $this->requests);
    }

    public function test_the_service_command_syncs_and_reports_that_it_is_alive(): void
    {
        $this->addManufacturer('ship', 'Sent by the service');

        $this->onShip(fn () => $this->artisan('sync:work', ['--once' => true])->expectsOutputToContain('ok')->assertSuccessful()->run());

        $this->assertSame(1, $this->cloudCount('manufacturers'));
        $this->assertNotNull(Heartbeat::age(Heartbeat::SYNC));

        // Not due yet: the next check only records the heartbeat.
        $this->requests = [];
        $this->onShip(fn () => $this->artisan('sync:work', ['--once' => true])->assertSuccessful()->run());
        $this->assertSame([], $this->requests);
    }

    public function test_the_service_idles_quietly_until_a_cloud_address_is_set(): void
    {
        $this->addManufacturer('ship', 'Kept on board');

        $this->onShip(function () {
            config(['sync.cloud_url' => null]);
            $this->artisan('sync:work', ['--once' => true])->expectsOutputToContain('sync is off')->assertSuccessful()->run();
            $this->assertSame(0, DB::table('sync_logs')->count(), 'no failure is logged');
            $this->assertSame(0, app(SyncWorker::class)->status()['failures']);
        });

        $this->assertSame([], $this->requests);
        $this->assertNotNull(Heartbeat::age(Heartbeat::SYNC));
    }

    public function test_the_commands_refuse_to_run_on_the_cloud(): void
    {
        $this->onCloud(function () {
            $this->artisan('sync:work', ['--once' => true])->assertFailed()->run();
            $this->artisan('sync:run')->assertFailed()->run();
        });
    }

    public function test_sync_now_in_the_foreground_reports_what_moved(): void
    {
        $this->addManufacturer('ship', 'One');
        $this->addCurrency('EUR', 'Euro');

        $this->onShip(fn () => $this->artisan('sync:run')->expectsOutputToContain('Clock difference')->assertSuccessful()->run());

        $this->network = 'down';
        $this->onShip(fn () => $this->artisan('sync:run')->expectsOutputToContain('try again in 30 s')->assertFailed()->run());
    }

    public function test_health_shows_sync_only_once_it_is_set_up(): void
    {
        $this->network = 'down';
        $this->syncCycle();
        Heartbeat::record(Heartbeat::SYNC);

        $check = $this->onShip(fn () => app(HealthCheck::class)->run()['checks']['sync'] ?? null);
        $this->assertNotNull($check);
        $this->assertTrue($check['ok'], 'offline at sea is not a failure of the service');
        $this->assertFalse($check['critical']);
        $this->assertSame(1, $check['failures_in_a_row']);
        $this->assertNull($check['last_success_age_seconds']);
        $this->assertStringContainsString('Last cycle failed', $check['message']);

        $this->onShip(function () {
            config(['sync.node_token' => '']);
            $this->assertFalse(app(HealthCheck::class)->run()['checks']['sync']['ok']);
            config(['sync.cloud_url' => null]);
            $this->assertArrayNotHasKey('sync', app(HealthCheck::class)->run()['checks']);
        });
    }
}
