<?php

namespace App\Http\Middleware;

use App\Sync\SyncContext;
use App\Sync\SyncSql;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Checkpoint 14: records the signed-in user as the actor of every change a
 * web request makes, so the outbox event and any deletion record say who
 * made it. A user without a sync UUID yet (before the backfill) is recorded
 * as the system. The actor is cleared again when the request ends.
 */
class SetSyncActor
{
    public function handle(Request $request, Closure $next): Response
    {
        $uuid = $request->user()?->sync_uuid;

        SyncContext::setActor(is_string($uuid) && SyncSql::isUuid($uuid) ? $uuid : null);

        try {
            return $next($request);
        } finally {
            SyncContext::setActor(null);
        }
    }
}
