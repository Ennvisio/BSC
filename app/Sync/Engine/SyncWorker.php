<?php

namespace App\Sync\Engine;

use App\Sync\SyncLogger;
use App\Sync\SyncState;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Decides when the vessel runs a sync cycle, and remembers it in the
 * database so a restart (Windows update, power cut) resumes the same
 * schedule instead of starting over.
 *
 *   all well          next cycle after `interval` (5 minutes)
 *   failure n         after base * 2^(n-1), at most `backoff_max`, +/- jitter:
 *                     30 s, 1, 2, 4, 8, 16, then every 30 minutes
 *   "sync now"        at the next check (within a few seconds)
 */
class SyncWorker
{
    public const STATE = 'worker';

    public function __construct(
        private SyncAgent $agent,
        private SyncState $state,
        private SyncLogger $logger,
    ) {
    }

    public function status(): array
    {
        return (array) $this->state->get(self::STATE, []) + [
            'failures' => 0, 'next_attempt_at' => null, 'last_attempt_at' => null,
            'last_success_at' => null, 'last_error' => null, 'run_now' => false,
        ];
    }

    public function isDue(?int $now = null): bool
    {
        $status = $this->status();
        $now ??= now()->getTimestamp();

        return $status['run_now'] || $status['next_attempt_at'] === null || $now >= $status['next_attempt_at'];
    }

    /** Asks the running worker to sync at its next check. */
    public function requestNow(): void
    {
        $this->save(['run_now' => true] + $this->status());
    }

    /** Runs one cycle and schedules the next. Never throws. */
    public function runCycle(): array
    {
        $status = $this->status();
        $now = now()->getTimestamp();
        $status['last_attempt_at'] = $now;
        $status['run_now'] = false;

        try {
            $summary = $this->agent->cycle();
        } catch (Throwable $e) {
            $status['failures']++;
            $delay = $this->delay($status['failures']);
            $status['next_attempt_at'] = $now + $delay;
            $status['last_error'] = mb_substr(class_basename($e).': '.$e->getMessage(), 0, 1000);
            $status['last_error_at'] = $now;
            $this->save($status);

            $offline = $e instanceof SyncTransportException && $e->isNetwork();
            $this->logger->record('cycle', $offline ? 'warning' : 'error', [
                'message' => ($offline ? 'Offline. ' : '').$status['last_error']." Next try in {$delay} s (failure {$status['failures']}).",
            ]);
            if (! $offline) {
                Log::channel('sync')->error('Sync cycle failed', ['error' => $status['last_error'], 'failures' => $status['failures']]);
            }

            return ['ok' => false, 'error' => $status['last_error'], 'retry_in' => $delay, 'failures' => $status['failures']];
        }

        if (isset($summary['skipped'])) {
            $status['next_attempt_at'] = $now + 30;
            $this->save($status);

            return ['ok' => true, 'skipped' => $summary['skipped']];
        }

        $status['failures'] = 0;
        $status['last_success_at'] = $now;
        $status['last_error'] = null;
        // More waiting than one cycle may carry: go again soon.
        $busy = ($summary['pushed']['batches'] ?? 0) >= (int) config('sync.worker.max_pages', 50)
            || ($summary['pulled']['pages'] ?? 0) >= (int) config('sync.worker.max_pages', 50);
        $status['next_attempt_at'] = $now + ($busy ? 5 : (int) config('sync.worker.interval', 300));
        $status['last_summary'] = $summary;
        $this->save($status);

        return ['ok' => true, 'summary' => $summary];
    }

    /** Seconds to wait after the given number of failures in a row. */
    public function delay(int $failures, ?float $random = null): int
    {
        $base = max(1, (int) config('sync.worker.backoff_base', 30));
        $max = max($base, (int) config('sync.worker.backoff_max', 1800));
        $jitter = (float) config('sync.worker.jitter', 0.2);

        $delay = min($max, $base * (2 ** max(0, min($failures - 1, 30))));
        $random ??= mt_rand() / mt_getrandmax();
        $delay *= 1 + $jitter * (2 * $random - 1);

        return (int) max(1, round(min($delay, $max)));
    }

    private function save(array $status): void
    {
        $this->state->put(self::STATE, $status);
    }
}
