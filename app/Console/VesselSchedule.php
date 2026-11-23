<?php

namespace App\Console;

use App\Jobs\QueueHeartbeat;
use App\Support\AppMode;
use App\Support\Heartbeat;
use Illuminate\Console\Scheduling\Schedule;

/**
 * The scheduled tasks. Called from routes/console.php.
 *
 * The cloud keeps exactly the schedule it had: the nightly PMS refresh for
 * every vessel. A vessel server refreshes only its own vessel's jobs (its
 * database may hold a full fleet copy) and adds the two heartbeats the health
 * check reads. Once sync is set up, both prune old sync bookkeeping nightly.
 */
class VesselSchedule
{
    public static function register(Schedule $schedule, AppMode $mode): void
    {
        // PMS: DueSoon/Overdue depend on today's date, so open jobs are
        // re-evaluated every night.
        $refresh = $mode->isVessel() && $mode->vesselId() !== null
            ? 'pms:refresh-jobs --vessel='.$mode->vesselId()
            : 'pms:refresh-jobs';

        $schedule->command($refresh)->dailyAt('00:15')->withoutOverlapping();

        if ($mode->isVessel()) {
            $schedule->call(fn () => Heartbeat::record(Heartbeat::SCHEDULER))
                ->everyMinute()
                ->name('navisuite:scheduler-heartbeat');

            $schedule->job(new QueueHeartbeat)
                ->everyMinute()
                ->name('navisuite:queue-heartbeat');
        }

        // Sync housekeeping (Checkpoint 5), only on servers where sync has
        // been set up (SYNC_NODE_ID), so the cloud's schedule is unchanged
        // until then.
        if (filled(config('sync.node_id'))) {
            $schedule->command('sync:prune')->dailyAt('03:30')->withoutOverlapping();
            // Checkpoint 7: problems reported once to the sync log, and
            // every stored file read back against its checksum nightly.
            $schedule->command('sync:health --log')->everyFifteenMinutes()->withoutOverlapping();
            $schedule->command('sync:files verify')->dailyAt('04:00')->withoutOverlapping();
        }
    }
}
