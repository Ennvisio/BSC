<?php

namespace Tests\Feature\VesselServer;

use App\Console\VesselSchedule;
use App\Jobs\QueueHeartbeat;
use App\Support\AppMode;
use App\Support\Heartbeat;
use Illuminate\Bus\Queueable;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/** The scheduler and queue worker services of a vessel server. */
class BackgroundServicesTest extends TestCase
{
    private function scheduleFor(string $mode, ?int $vesselId = null): Schedule
    {
        config(['navisuite.mode' => $mode, 'navisuite.vessel_id' => $vesselId]);
        $schedule = new Schedule;
        VesselSchedule::register($schedule, new AppMode);

        return $schedule;
    }

    public function test_the_clouds_schedule_is_unchanged(): void
    {
        $events = $this->scheduleFor('cloud')->events();

        $this->assertCount(1, $events);
        $this->assertStringEndsWith("artisan' pms:refresh-jobs", $events[0]->command);
        $this->assertSame('15 0 * * *', $events[0]->expression);
    }

    public function test_a_vessel_refreshes_only_its_own_pms_jobs_and_sends_heartbeats(): void
    {
        $events = $this->scheduleFor('vessel', 7)->events();

        $this->assertCount(3, $events);
        $this->assertStringEndsWith("artisan' pms:refresh-jobs --vessel=7", $events[0]->command);
        $this->assertSame(['navisuite:scheduler-heartbeat', 'navisuite:queue-heartbeat'], [$events[1]->description, $events[2]->description]);
        $this->assertSame('* * * * *', $events[1]->expression);
        $this->assertInstanceOf(CallbackEvent::class, $events[1]);
    }

    public function test_sync_housekeeping_is_added_only_once_sync_is_set_up(): void
    {
        config(['sync.node_id' => '0b6f8a52-3c1e-4e8f-9a51-7d0e2f6b9c11']);

        foreach (['cloud' => 4, 'vessel' => 6] as $mode => $count) {
            $events = $this->scheduleFor($mode, $mode === 'vessel' ? 7 : null)->events();
            $this->assertCount($count, $events);
            [$prune, $health, $verify] = array_slice($events, -3);
            $this->assertStringEndsWith("artisan' sync:prune", $prune->command);
            $this->assertSame('30 3 * * *', $prune->expression);
            // Checkpoint 7: monitoring and the nightly integrity check.
            $this->assertStringEndsWith("artisan' sync:health --log", $health->command);
            $this->assertSame('*/15 * * * *', $health->expression);
            $this->assertStringEndsWith("artisan' sync:files verify", $verify->command);
            $this->assertSame('0 4 * * *', $verify->expression);
        }
    }

    public function test_the_scheduler_heartbeat_is_recorded_when_it_runs(): void
    {
        $this->assertNull(Heartbeat::age(Heartbeat::SCHEDULER));

        $this->scheduleFor('vessel', 7)->events()[1]->run($this->app);

        $this->assertLessThanOrEqual(1, Heartbeat::age(Heartbeat::SCHEDULER));
    }

    public function test_the_queue_heartbeat_is_recorded_by_a_worker(): void
    {
        $this->createQueueTables();
        config(['queue.default' => 'database']);

        QueueHeartbeat::dispatch();
        $this->assertSame(1, DB::table('jobs')->count());
        $this->assertNull(Heartbeat::age(Heartbeat::QUEUE));

        Artisan::call('queue:work', ['--once' => true, '--stop-when-empty' => true]);

        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertLessThanOrEqual(1, Heartbeat::age(Heartbeat::QUEUE));
    }

    public function test_a_failing_job_is_kept_in_failed_jobs(): void
    {
        $this->createQueueTables();
        config(['queue.default' => 'database']);

        dispatch(new FailingTestJob);
        Artisan::call('queue:work', ['--once' => true, '--stop-when-empty' => true]);

        $this->assertSame(0, DB::table('jobs')->count());
        $failed = DB::table('failed_jobs')->sole();
        $this->assertStringContainsString('Simulated failure', $failed->exception);
    }

    public function test_the_queue_tables_migration_is_repeatable_and_reversible(): void
    {
        $migration = $this->createQueueTables();
        $migration->up();

        foreach (['jobs', 'job_batches', 'failed_jobs'] as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }

        $migration->down();
        $this->assertFalse(Schema::hasTable('jobs'));
    }

    private function createQueueTables(): object
    {
        $migration = require database_path('migrations/2026_11_22_100000_create_queue_tables.php');
        $migration->up();

        return $migration;
    }
}

class FailingTestJob implements ShouldQueue
{
    use Queueable;

    public $tries = 1;

    public function handle(): void
    {
        throw new RuntimeException('Simulated failure');
    }
}
