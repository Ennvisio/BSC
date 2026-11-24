<?php

namespace App\Sync\Database;

use Illuminate\Database\Connection;

/**
 * Makes the MySQL, MariaDB and SQLite connections hand out the
 * ownership-guarded query builder (PD-34). Registered before any
 * connection is opened (SyncServiceProvider::register()).
 */
final class OwnershipGuardedConnections
{
    public static function register(): void
    {
        $drivers = [
            'mysql' => GuardedMySqlConnection::class,
            'mariadb' => GuardedMariaDbConnection::class,
            'sqlite' => GuardedSQLiteConnection::class,
        ];
        foreach ($drivers as $driver => $class) {
            Connection::resolverFor($driver, fn ($pdo, $database, $prefix, $config) => new $class($pdo, $database, $prefix, $config));
        }
    }
}
