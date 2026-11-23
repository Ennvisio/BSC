<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * "Still alive" timestamps for the vessel server's background services.
 *
 * The scheduler records one every minute, and so does a tiny job that goes
 * through the queue, which proves a queue worker is running. The LAN
 * discovery responder records one every 30 seconds while it listens, and the
 * sync worker every few seconds. The health
 * check (App\Support\HealthCheck) reports how old each one is. The values
 * live in the cache, which the web server and the services share on disk.
 */
class Heartbeat
{
    public const SCHEDULER = 'scheduler';

    public const QUEUE = 'queue';

    public const DISCOVERY = 'discovery';

    public const SYNC = 'sync';

    private const PREFIX = 'navisuite:heartbeat:';

    public static function record(string $name): void
    {
        Cache::forever(self::PREFIX.$name, time());
    }

    /** Seconds since the last heartbeat, or null if there has never been one. */
    public static function age(string $name): ?int
    {
        $last = Cache::get(self::PREFIX.$name);

        return is_int($last) ? max(0, time() - $last) : null;
    }
}
