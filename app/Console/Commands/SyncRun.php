<?php

namespace App\Console\Commands;

use App\Support\AppMode;
use App\Sync\Engine\SyncWorker;
use Illuminate\Console\Command;

/** "Sync now": one cycle in the foreground, or ask the running service to sync. */
class SyncRun extends Command
{
    protected $signature = 'sync:run {--background : Ask the NaviSuite-Sync service to sync at its next check instead}';

    protected $description = 'Vessel only: sync with the cloud now';

    public function handle(AppMode $mode, SyncWorker $worker): int
    {
        if (! $mode->isVessel()) {
            $this->error('sync:run runs on vessel servers only.');

            return self::FAILURE;
        }

        if ($this->option('background')) {
            $worker->requestNow();
            $this->info('The sync service will start a cycle within a few seconds.');

            return self::SUCCESS;
        }

        $result = $worker->runCycle();
        if (! $result['ok']) {
            $this->error($result['error']);
            $this->line("The service will try again in {$result['retry_in']} s.");

            return self::FAILURE;
        }
        if (isset($result['skipped'])) {
            $this->warn($result['skipped']);

            return self::SUCCESS;
        }

        $s = $result['summary'];
        $this->table(['', 'Batches or pages', 'Changes', 'Problems'], [
            ['Sent', $s['pushed']['batches'], $s['pushed']['events'], "{$s['pushed']['rejected']} refused, {$s['pushed']['conflicts']} conflicts"],
            ['Received', $s['pulled']['pages'], $s['pulled']['events'], "{$s['pulled']['rejected']} refused, {$s['pulled']['deferred']} waiting, {$s['pulled']['conflicts']} conflicts"],
        ]);
        $this->line("Took {$s['duration_ms']} ms. Clock difference to the cloud: ".($s['clock_skew'] ?? '?').' s.');

        return self::SUCCESS;
    }
}
