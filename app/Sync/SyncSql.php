<?php

namespace App\Sync;

use InvalidArgumentException;

/**
 * Driver-specific SQL fragments shared by the migrations and the trigger
 * builder. Production runs MySQL on both the cloud and the vessels. SQLite
 * is supported so the test suite can exercise the real triggers.
 */
final class SyncSql
{
    public const DRIVERS = ['mysql', 'sqlite'];

    /** An SQL expression that yields a new random UUID string per row. */
    public static function uuidExpression(string $driver): string
    {
        return match ($driver) {
            // Time-and-node based (version 1). Unique across servers.
            'mysql' => 'UUID()',
            // Random (version 4), built from randomblob() so it is evaluated per row.
            'sqlite' => "lower(hex(randomblob(4)) || '-' || hex(randomblob(2)) || '-4' || substr(hex(randomblob(2)), 2) || '-' || substr('89ab', 1 + (abs(random()) % 4), 1) || substr(hex(randomblob(2)), 2) || '-' || hex(randomblob(6)))",
            default => throw self::unsupported($driver),
        };
    }

    /** The current time with sub-second precision. */
    public static function nowExpression(string $driver): string
    {
        return match ($driver) {
            'mysql' => 'CURRENT_TIMESTAMP(6)',
            'sqlite' => "strftime('%Y-%m-%d %H:%M:%f', 'now')",
            default => throw self::unsupported($driver),
        };
    }

    public static function assertSupported(string $driver): void
    {
        if (! in_array($driver, self::DRIVERS, true)) {
            throw self::unsupported($driver);
        }
    }

    public static function isUuid(?string $value): bool
    {
        return is_string($value)
            && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1;
    }

    private static function unsupported(string $driver): InvalidArgumentException
    {
        return new InvalidArgumentException("Sync change capture supports MySQL and SQLite, not \"{$driver}\".");
    }
}
