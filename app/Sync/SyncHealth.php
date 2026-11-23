<?php

namespace App\Sync;

use App\Support\AppMode;
use App\Support\Secrets\SecretsReport;
use App\Sync\Engine\SyncAgent;
use App\Sync\Engine\SyncWorker;
use App\Sync\Files\FileIndex;
use App\Sync\Models\SyncConflict;
use App\Sync\Models\SyncInboxEvent;
use App\Sync\Models\SyncNode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Sync failure monitoring (Checkpoint 7): everything about sync that needs
 * a person, in one list. Used by "php artisan sync:health", /health and
 * check-health.ps1.
 *
 * Being offline is normal at sea: failures caused by the link alone only
 * become a warning once the vessel has not synced for
 * sync.alerts.offline_warning_hours. Any other failure is reported from
 * sync.alerts.failures_in_a_row on.
 */
class SyncHealth
{
    public const ERROR = 'error';

    public const WARNING = 'warning';

    public const INFO = 'info';

    public function __construct(private AppMode $mode, private SyncState $state)
    {
    }

    /** @return list<array{level: string, check: string, message: string}> */
    public function alerts(): array
    {
        $alerts = [];
        $add = function (string $level, string $check, string $message) use (&$alerts) {
            $alerts[] = ['level' => $level, 'check' => $check, 'message' => $message];
        };

        foreach (app(SecretsReport::class)->problems() as $kind => $messages) {
            foreach ($messages as $message) {
                $add($kind === 'errors' ? self::ERROR : self::WARNING, 'credentials', $message);
            }
        }

        if (! Schema::hasTable('sync_state')) {
            return $alerts;
        }

        if ($this->mode->isVessel()) {
            $this->vessel($add);
        } elseif (filled(config('sync.node_id'))) {
            $this->cloud($add);
        }

        if (Schema::hasTable('sync_inbox')) {
            $failed = SyncInboxEvent::query()->where('status', SyncInboxEvent::STATUS_FAILED)->count();
            if ($failed > 0) {
                $add(self::WARNING, 'inbox', "{$failed} received change(s) could not be applied. See \"php artisan sync:status\"; fix the cause and run \"sync:retry --failed\".");
            }
            $stuckHours = (int) config('sync.alerts.deferred_warning_hours', 24);
            $stuck = SyncInboxEvent::query()->where('status', SyncInboxEvent::STATUS_DEFERRED)
                ->where('received_at', '<', now()->subHours($stuckHours))->count();
            if ($stuck > 0) {
                $add(self::WARNING, 'inbox', "{$stuck} received change(s) have waited more than {$stuckHours} h for a record they refer to.");
            }
        }

        if (Schema::hasTable('sync_conflicts')) {
            $review = SyncConflict::query()->where('status', SyncConflict::STATUS_NEEDS_REVIEW)->count();
            if ($review > 0) {
                $add(self::INFO, 'conflicts', "{$review} conflict(s) wait for review (php artisan sync:conflicts).");
            }
        }

        if (Schema::hasTable('sync_files')) {
            $counts = app(FileIndex::class)->counts();
            if (($counts[FileIndex::FAILED] ?? 0) > 0) {
                $add(self::WARNING, 'files', ($counts[FileIndex::FAILED]).' file(s) could not be transferred (php artisan sync:files).');
            }
            if (($counts[FileIndex::MISSING] ?? 0) > 0) {
                $add(self::WARNING, 'files', ($counts[FileIndex::MISSING]).' file(s) named by records are missing or damaged here (php artisan sync:files).');
            }
            $verify = (array) $this->state->get('files.verify', []);
            if (($verify['bad'] ?? 0) > 0) {
                $add(self::ERROR, 'integrity', "The last integrity check found {$verify['bad']} damaged or missing file(s).");
            }
        }

        return $alerts;
    }

    /** Highest level among the alerts, or "ok". */
    public static function level(array $alerts): string
    {
        $levels = array_column($alerts, 'level');

        return in_array(self::ERROR, $levels, true) ? self::ERROR : (in_array(self::WARNING, $levels, true) ? self::WARNING : 'ok');
    }

    /**
     * Writes alerts that are new since the last call to the sync log
     * channel and sync_logs, so a log watcher sees each problem once.
     *
     * @return list<array> the new alerts
     */
    public function logNew(array $alerts): array
    {
        $previous = (array) $this->state->get('health.alerts', []);
        $keys = array_map(fn ($a) => $a['level'].'|'.$a['check'].'|'.$a['message'], $alerts);
        $new = [];
        foreach ($alerts as $i => $alert) {
            if ($alert['level'] !== self::INFO && ! in_array($keys[$i], $previous, true)) {
                $new[] = $alert;
                Log::channel('sync')->log($alert['level'] === self::ERROR ? 'error' : 'warning', 'Sync alert: '.$alert['message'], ['check' => $alert['check']]);
                app(SyncLogger::class)->record('health', $alert['level'], ['message' => mb_substr($alert['message'], 0, 1000)]);
            }
        }
        $this->state->put('health.alerts', $keys);
        $this->state->put('health.checked_at', now()->getTimestamp());

        return $new;
    }

    private function vessel(callable $add): void
    {
        $problems = app(SyncAgent::class)->configurationProblems();
        if ($problems !== []) {
            if (filled(config('sync.cloud_url')) || filled(config('sync.node_id'))) {
                $add(self::ERROR, 'setup', 'Sync is not set up: '.implode(' ', $problems));
            }

            return;
        }

        $worker = app(SyncWorker::class)->status();
        $now = time();
        $lastSuccess = $worker['last_success_at'] ?? null;
        $offlineHours = (int) config('sync.alerts.offline_warning_hours', 72);
        $failures = (int) ($worker['failures'] ?? 0);
        $error = (string) ($worker['last_error'] ?? '');
        $network = $error !== '' && str_contains($error, 'Cloud not reachable');

        if ($lastSuccess === null) {
            $add(self::WARNING, 'cycle', 'This vessel has never synced with the cloud.'.($error !== '' ? " Last error: {$error}" : ''));
        } elseif ($now - (int) $lastSuccess > $offlineHours * 3600) {
            $add(self::WARNING, 'cycle', sprintf('No successful sync for %d hours.', intdiv($now - (int) $lastSuccess, 3600)).($error !== '' ? " Last error: {$error}" : ''));
        }
        if (! $network && $failures >= (int) config('sync.alerts.failures_in_a_row', 3)) {
            $add(self::ERROR, 'cycle', "The last {$failures} sync cycles failed: {$error}");
        }

        $acked = DB::table('sync_cursors')->where('stream', SyncAgent::STREAM_PUSH)->max('position');
        $oldest = DB::table('sync_outbox')->whereNull('origin_node')
            ->when($acked !== null, fn ($q) => $q->where(fn ($w) => $w->whereNull('seq')->orWhere('seq', '>', $acked)))
            ->min('created_at');
        if ($oldest !== null && strtotime((string) $oldest) < $now - $offlineHours * 3600) {
            $add(self::WARNING, 'outbox', 'Changes made on board since '.substr((string) $oldest, 0, 16).' have not reached the cloud yet.');
        }

        $files = (array) $this->state->get('files', []);
        if (! empty($files['last_error']) && ($files['last_run_at'] ?? 0) > $now - 86400) {
            $add(self::INFO, 'files', 'Last file transfer: '.$files['last_error']);
        }
    }

    private function cloud(callable $add): void
    {
        $hours = (int) config('sync.alerts.offline_warning_hours', 72);
        foreach (SyncNode::query()->where('kind', SyncNode::KIND_VESSEL)->whereNull('revoked_at')->get() as $node) {
            if ($node->last_seen_at === null) {
                continue; // registered, not installed yet
            }
            if ($node->last_seen_at->lt(now()->subHours($hours))) {
                $add(self::WARNING, 'vessels', ($node->name ?: $node->node_id)." (vessel {$node->vessel_id}) has not synced since ".$node->last_seen_at->format('Y-m-d H:i').'.');
            }
        }
    }
}
