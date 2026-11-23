<?php

namespace App\Sync\Engine;

/**
 * Module logic that runs while a received change is applied, inside the
 * same savepoint: anything it throws undoes the change. MissingReference
 * makes the change wait and be tried again later, InvalidChange refuses it.
 *
 * Listed per table in config/sync.php ('hooks').
 */
interface ApplyHook
{
    /**
     * Adjusts the received values (already decoded: local ids) before they
     * are checked against the natural key and written.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function prepare(string $table, array $values, ?object $local, ReceivingSide $side): array;

    /**
     * After the row was written or deleted. $before is the row as it was
     * here (null for a new row); $after as it is now (null after a delete).
     */
    public function applied(string $table, array $event, string $source, ?object $before, ?object $after, ReceivingSide $side): void;
}
