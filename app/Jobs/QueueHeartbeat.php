<?php

namespace App\Jobs;

use App\Support\Heartbeat;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Dispatched by the scheduler every minute on a vessel server. When a queue
 * worker runs it, the queue heartbeat is refreshed, so a stopped worker shows
 * up in the health check.
 */
class QueueHeartbeat implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public $tries = 1;

    public function handle(): void
    {
        Heartbeat::record(Heartbeat::QUEUE);
    }
}
