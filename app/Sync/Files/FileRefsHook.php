<?php

namespace App\Sync\Files;

use App\Sync\Engine\ApplyHook;
use App\Sync\Engine\InvalidChange;
use App\Sync\Engine\ReceivingSide;
use App\Sync\Engine\ReplicationRules;

/**
 * Runs for every table with file columns (config/sync.php 'files.columns').
 *
 * Before a change from a vessel is written on the cloud, a file path that
 * is not one this server stores files under is refused, so a vessel can
 * never make the cloud read or serve a file outside its storage. After any
 * change is written, the file it names is recorded as present, or wanted
 * from the server that sent the change (FileIndex); FileTransfer fetches
 * it later.
 */
class FileRefsHook implements ApplyHook
{
    public function __construct(private FileIndex $index)
    {
    }

    public function prepare(string $table, array $values, ?object $local, ReceivingSide $side): array
    {
        if ($side->name() !== ReplicationRules::CLOUD) {
            return $values;
        }
        foreach (FileColumns::forTable($table) as $column => $spec) {
            if (! array_key_exists($column, $values) || $values[$column] === null) {
                continue;
            }
            $path = (string) $values[$column];
            if ($local !== null && (string) ($local->{$column} ?? '') === $path) {
                continue; // unchanged: whatever was here stays
            }
            if (! FileColumns::isSafePath($spec, $path)) {
                throw new InvalidChange("{$table}.{$column} names a file outside {$spec['prefix']} or of a type that is never stored, so it is refused.");
            }
        }

        return $values;
    }

    public function applied(string $table, array $event, string $source, ?object $before, ?object $after, ReceivingSide $side): void
    {
        if ($after === null) {
            // The record is gone; the file stays on disk (other records or
            // the backups may still need it) but is no longer tracked.
            $this->index->forget($table, $event['row_uuid']);

            return;
        }

        $uuid = $this->index->uuidOf($table, $after);
        $old = $before === null ? [] : $this->index->columnsOf($table, $before);
        foreach ($this->index->columnsOf($table, $after) as $column => $file) {
            $unchanged = ($old[$column]['path'] ?? null) === $file['path'] && ($old[$column]['sha256'] ?? null) === $file['sha256'];
            if ($unchanged && $this->index->entry($table, $uuid, $column) !== null) {
                continue;
            }
            $this->index->reconcile($table, $uuid, $column, $file['path'], $file['sha256'], $file['size'], $source);
        }
    }
}
