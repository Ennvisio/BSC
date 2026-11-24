<?php

namespace App\Providers;

use App\Sync\SyncContext;
use App\Sync\SyncCursors;
use App\Sync\SyncInbox;
use App\Sync\SyncLogger;
use App\Sync\SyncNodes;
use App\Sync\SyncRegistry;
use App\Sync\TriggerBuilder;
use App\Sync\TriggerManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/** Cloud-vessel synchronisation: foundation (Checkpoint 2) and engine (Checkpoint 5). See config/sync.php. */
class SyncServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // PD-34: every connection hands out the ownership-guarded query
        // builder, so query-builder writes are checked like model saves.
        \App\Sync\Database\OwnershipGuardedConnections::register();

        foreach ([SyncRegistry::class, TriggerBuilder::class, TriggerManager::class, SyncInbox::class, SyncCursors::class, SyncNodes::class, SyncLogger::class] as $class) {
            $this->app->singleton($class);
        }
    }

    public function boot(): void
    {
        // Checkpoint 7: a crew member moved to another vessel (or ashore) is
        // also told to the vessel they left (App\Sync\VesselDepartures).
        \App\Role::updated(function (\App\Role $role) {
            $old = $role->getOriginal('vessel_id');
            if ($role->wasChanged('vessel_id') && $old !== null && (int) $old !== (int) $role->vessel_id) {
                app(\App\Sync\VesselDepartures::class)->record((int) $old, ['roles' => [$role->id], 'users' => [$role->user_id]]);
            }
        });

        // The SQLite versions of the capture triggers call two helper
        // functions, which must exist on every SQLite connection. MySQL needs
        // nothing: its triggers read session variables.
        Event::listen(ConnectionEstablished::class, function (ConnectionEstablished $event) {
            SyncContext::registerSqliteFunctions($event->connection);
        });

        // PD-34 (decided): on the cloud, a vessel's own record of a vessel
        // that runs its own server is never deleted. Every Eloquent delete
        // (soft or hard) is checked here, so a later screen inherits the
        // rule; the query-builder deletes that can reach such rows are
        // refused by their own checks (see App\Sync\VesselOwnership).
        Event::listen('eloquent.deleting: *', function (string $event, array $payload) {
            $model = $payload[0] ?? null;
            if (! $model instanceof \Illuminate\Database\Eloquent\Model || ! $model->exists) {
                return;
            }
            $refusal = app(\App\Sync\VesselOwnership::class)
                ->cloudDeleteRefusal($model->getTable(), $model->getAttributes(), $model->getKeyName());
            if ($refusal !== null) {
                throw new \App\Sync\Exceptions\VesselRecordProtected($refusal);
            }
        });

        // PD-34, edits (decided 2026-11-24): data is changed only on the
        // side that owns it. Every Eloquent save is checked here, so a later
        // screen inherits the rule (see App\Sync\WrongSideEdits).
        Event::listen(\Illuminate\Database\Events\MigrationsStarted::class, fn () => \App\Sync\WrongSideEdits::migrationsStarted());
        Event::listen(\Illuminate\Database\Events\MigrationsEnded::class, fn () => \App\Sync\WrongSideEdits::migrationsEnded());
        Event::listen('eloquent.saving: *', function (string $event, array $payload) {
            $model = $payload[0] ?? null;
            if ($model instanceof \Illuminate\Database\Eloquent\Model) {
                app(\App\Sync\WrongSideEdits::class)->check($model);
            }
        });

        // /api/sync/v1: a vessel makes a handful of calls per cycle. This
        // only stops a misbehaving node from flooding the cloud.
        RateLimiter::for('sync', fn (Request $request) => Limit::perMinute(300)->by('sync-node:'.$request->header('X-Sync-Node', $request->ip())));
    }
}
