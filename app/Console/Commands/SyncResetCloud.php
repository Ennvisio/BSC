<?php

namespace App\Console\Commands;

use App\Support\AppMode;
use App\Sync\Engine\SyncAgent;
use App\Sync\Engine\SyncWorker;
use App\Sync\SyncLogger;
use App\Sync\SyncState;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Recovery when the cloud this vessel synced with is gone: replaced by a new
 * server (a new cloud node id), or restored from a backup (the vessel is
 * then "ahead" of the cloud and every pull is refused with 409).
 *
 * Forgets what this vessel knows about the cloud, so the next cycle sends
 * every change still in the outbox and fetches every change the cloud still
 * has, from the start. That is safe: rows the other side already has are
 * recognised as unchanged. Business data is not touched.
 */
class SyncResetCloud extends Command
{
    protected $signature = 'sync:reset-cloud {--force : Do not ask}';

    protected $description = 'Vessel only: start over with a replaced or restored cloud server (see docs/checkpoint-5-sync-engine.md, "Cloud replaced")';

    public function handle(AppMode $mode, SyncState $state, SyncLogger $logger, SyncWorker $worker): int
    {
        if (! $mode->isVessel()) {
            $this->error('sync:reset-cloud runs on vessel servers only.');

            return self::FAILURE;
        }

        $cloud = $state->get('cloud.node_id');
        $cursors = $cloud ? DB::table('sync_cursors')->where('node_id', $cloud)->pluck('position', 'stream')->all() : [];
        $this->line('Cloud known as: '.($cloud ?? 'none'));
        $this->line('Sent up to: '.($cursors[SyncAgent::STREAM_PUSH] ?? 0).', received up to: '.($cursors[SyncAgent::STREAM_PULL] ?? 0));

        if (! $this->option('force') && ! $this->confirm('Forget this and send and fetch everything again at the next sync?', false)) {
            return self::FAILURE;
        }

        $lock = Cache::lock(SyncAgent::LOCK, 300);
        if (! $lock->get()) {
            $this->error('A sync cycle is running. Try again in a minute.');

            return self::FAILURE;
        }

        try {
            DB::transaction(function () use ($state, $cloud) {
                if ($cloud) {
                    DB::table('sync_cursors')->where('node_id', $cloud)->delete();
                }
                $state->forget('cloud.node_id');
                $state->forget('cloud.last_handshake');
            });
        } finally {
            $lock->release();
        }

        $logger->record('reset-cloud', 'warning', ['node_id' => $cloud, 'message' => 'Cloud forgotten; everything is sent and fetched again at the next sync.']);
        $worker->requestNow();

        $this->info('Done. The next sync sends every change still in the outbox and fetches everything the cloud still has.');
        $this->line('Changes older than the outbox retention (SYNC retention, 14 days) are no longer in the outbox. To send a whole table, run');
        $this->line('  php artisan sync:resend <table> --force   (here, for tables the vessel sends up)');
        $this->line('  php artisan sync:resend <table> --vessel=<id> --force   (on the cloud, for tables it sends down)');

        return self::SUCCESS;
    }
}
