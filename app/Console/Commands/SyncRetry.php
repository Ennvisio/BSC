<?php

namespace App\Console\Commands;

use App\Sync\Engine\SyncMaintenance;
use Illuminate\Console\Command;

class SyncRetry extends Command
{
    protected $signature = 'sync:retry {--failed : Also retry changes that failed, e.g. after enabling a table or fixing a cause}';

    protected $description = 'Apply again received changes that are waiting or (with --failed) that failed';

    public function handle(SyncMaintenance $maintenance): int
    {
        $results = $maintenance->retry((bool) $this->option('failed'));
        if ($results === []) {
            $this->info('Nothing is waiting.');

            return self::SUCCESS;
        }
        $this->table(['From node', 'Applied', 'Still waiting', 'Failed'], collect($results)->map(fn ($r, $n) => [$n, $r['applied'], $r['deferred'], $r['failed']])->values()->all());

        return self::SUCCESS;
    }
}
