<?php

namespace App\Sync;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Session state the capture triggers read:
 *
 * - capture off: writes are not recorded in sync_outbox and do not bump
 *   sync_version. For maintenance such as filling in missing UUIDs or
 *   loading a vessel snapshot.
 * - origin: the node a change came from. Set while applying changes received
 *   from another node, so they are recorded with that origin (and never sent
 *   back to it) and keep the version the sender gave them.
 *
 * - actor: the user (their users.sync_uuid) making the change. Recorded on
 *   the outbox event and on the deletion record (Checkpoint 14), so the
 *   receiving side knows who made a change. Null for the system.
 *
 * On MySQL the triggers read the session variables @sync_capture_off,
 * @sync_origin and @sync_actor. On SQLite (tests) they call the functions registered by
 * registerSqliteFunctions(), which read the same state from here.
 */
final class SyncContext
{
    private static bool $captureOff = false;

    private static ?string $origin = null;

    private static ?string $actor = null;

    public static function captureEnabled(): bool
    {
        return ! self::$captureOff;
    }

    public static function origin(): ?string
    {
        return self::$origin;
    }

    public static function actor(): ?string
    {
        return self::$actor;
    }

    /**
     * Records later changes as made by user $actorUuid (null = the system)
     * until it is set again. Used per web request by SetSyncActor.
     */
    public static function setActor(?string $actorUuid): void
    {
        self::set(self::$captureOff, self::$origin, self::normaliseActor($actorUuid));
    }

    /** Runs $callback recording every change as made by user $actorUuid. */
    public static function asActor(?string $actorUuid, callable $callback): mixed
    {
        return self::with(self::$captureOff, self::$origin, $callback, self::normaliseActor($actorUuid));
    }

    private static function normaliseActor(?string $actorUuid): ?string
    {
        if ($actorUuid === null || $actorUuid === '') {
            return null;
        }
        if (! SyncSql::isUuid($actorUuid)) {
            throw new InvalidArgumentException('The actor must be a user UUID.');
        }

        return strtolower($actorUuid);
    }

    /**
     * Runs $callback with change capture switched off on the default
     * connection, then restores the previous state, even on failure.
     */
    public static function withoutCapture(callable $callback): mixed
    {
        return self::with(true, self::$origin, $callback);
    }

    /** Runs $callback recording every change as coming from node $nodeId. */
    public static function asOrigin(string $nodeId, callable $callback): mixed
    {
        if (! SyncSql::isUuid($nodeId)) {
            throw new InvalidArgumentException('The origin must be a node UUID.');
        }

        return self::with(self::$captureOff, strtolower($nodeId), $callback);
    }

    private static function with(bool $captureOff, ?string $origin, callable $callback, string|false|null $actor = false): mixed
    {
        [$previousCaptureOff, $previousOrigin, $previousActor] = [self::$captureOff, self::$origin, self::$actor];

        self::set($captureOff, $origin, $actor === false ? self::$actor : $actor);

        try {
            return $callback();
        } finally {
            self::set($previousCaptureOff, $previousOrigin, $previousActor);
        }
    }

    private static function set(bool $captureOff, ?string $origin, ?string $actor): void
    {
        self::$captureOff = $captureOff;
        self::$origin = $origin;
        self::$actor = $actor;

        $connection = DB::connection();

        if ($connection->getDriverName() === 'mysql') {
            $connection->statement('SET @sync_capture_off = ?, @sync_origin = ?, @sync_actor = ?', [$captureOff ? 1 : null, $origin, $actor]);
        }
    }

    /** Makes the trigger helper functions available on a SQLite connection. */
    public static function registerSqliteFunctions(Connection $connection): void
    {
        if ($connection->getDriverName() !== 'sqlite') {
            return;
        }

        $pdo = $connection->getPdo();
        $functions = [
            'sync_capture_enabled' => fn () => self::captureEnabled() ? 1 : 0,
            'sync_origin' => fn () => self::origin(),
            'sync_actor' => fn () => self::actor(),
        ];

        foreach ($functions as $name => $function) {
            method_exists($pdo, 'createFunction')
                ? $pdo->createFunction($name, $function, 0)
                : $pdo->sqliteCreateFunction($name, $function, 0);
        }
    }
}
