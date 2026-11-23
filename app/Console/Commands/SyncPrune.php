<?php

namespace App\Console\Commands;

use App\Sync\Engine\SyncMaintenance;
use Illuminate\Console\Command;

class SyncPrune extends Command
{
    protected $signature = 'sync:prune {--dry-run : Only count}';

    protected $description = 'Delete sync bookkeeping that every reader has passed and that is past its retention period';

    public function handle(SyncMaintenance $maintenance): int
    {
        $counts = $maintenance->prune((bool) $this->option('dry-run'));
        $this->table(['Table', $this->option('dry-run') ? 'Would delete' : 'Deleted'], collect($counts)->map(fn ($n, $t) => ["sync_{$t}", $n])->values()->all());

        return self::SUCCESS;
    }
}
