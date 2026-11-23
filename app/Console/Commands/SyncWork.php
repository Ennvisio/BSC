<?php

namespace App\Console\Commands;

use App\Support\AppMode;
use App\Support\Heartbeat;
use App\Sync\Engine\SyncWorker;
use Illuminate\Console\Command;

/**
 * The vessel's background sync service (NaviSuite-Sync on Windows). Runs a
 * cycle when one is due, then sleeps a few seconds and checks again. The
 * schedule lives in the database, so stopping and starting the service, or
 * restarting Windows, simply carries on.
 */
class SyncWork extends Command
{
    protected $signature = 'sync:work
        {--once : Run one cycle if due, then stop}
        {--max-cycles=0 : Stop after this many cycles (0: never)}
        {--sleep=5 : Seconds between checks}';

    protected $description = 'Vessel only: keep the vessel in sync with the cloud (the NaviSuite-Sync service)';

    public function handle(AppMode $mode, SyncWorker $worker): int
    {
        if (! $mode->isVessel()) {
            $this->error('sync:work runs on vessel servers only.');

            return self::FAILURE;
        }

        $cycles = 0;
        $max = (int) $this->option('max-cycles');
        $sleep = max(1, (int) $this->option('sleep'));

        $idleNoted = false;

        while (true) {
            Heartbeat::record(Heartbeat::SYNC);

            if (blank(config('sync.cloud_url'))) {
                // Not set up yet: stay alive (the service must not keep
                // restarting) but do nothing and log nothing.
                if (! $idleNoted) {
                    $this->line(now()->format('Y-m-d H:i:s').' idle: SYNC_CLOUD_URL is empty, so sync is off.');
                    $idleNoted = true;
                }
            } elseif ($worker->isDue()) {
                $result = $worker->runCycle();
                $cycles++;
                $this->line(now()->format('Y-m-d H:i:s').' '.($result['ok']
                    ? 'ok'.(isset($result['skipped']) ? ': '.$result['skipped'] : '')
                    : "failed ({$result['failures']} in a row), retry in {$result['retry_in']} s: {$result['error']}"));
            }

            if ($this->option('once') || ($max > 0 && $cycles >= $max)) {
                return self::SUCCESS;
            }

            sleep($sleep);
        }
    }
}
