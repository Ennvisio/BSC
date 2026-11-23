<?php

namespace App\Sync\Engine;

use RuntimeException;

/** A received change refers to a row this server does not have yet. The change waits (deferred). */
class MissingReference extends RuntimeException
{
    public function __construct(public readonly string $table, public readonly string $column, public readonly string $uuid)
    {
        parent::__construct("{$column} refers to {$table} {$uuid}, which is not here yet.");
    }
}
