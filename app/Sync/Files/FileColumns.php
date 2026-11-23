<?php

namespace App\Sync\Files;

use App\Support\Files\SafeUpload;

/**
 * The columns that hold a file's path (config/sync.php 'files.columns'),
 * and the rules a path must follow before this server reads or writes the
 * file it names.
 */
class FileColumns
{
    /** @return array<string, array{disk: string, prefix: string, sha256: ?string, size: ?string}> column => spec */
    public static function forTable(string $table): array
    {
        $columns = [];
        foreach ((array) config("sync.files.columns.{$table}", []) as $column => $spec) {
            $columns[(string) $column] = [
                'disk' => (string) ($spec['disk'] ?? 'local'),
                'prefix' => (string) ($spec['prefix'] ?? ''),
                'sha256' => $spec['sha256'] ?? null,
                'size' => $spec['size'] ?? null,
            ];
        }

        return $columns;
    }

    /** @return list<string> */
    public static function tables(): array
    {
        return array_keys((array) config('sync.files.columns', []));
    }

    public static function spec(string $table, string $column): ?array
    {
        return self::forTable($table)[$column] ?? null;
    }

    /**
     * A path this server may read or write: under the column's prefix,
     * plain names only (letters, digits, dot, dash, underscore), no "." or
     * ".." segment, and an extension a stored file can have.
     */
    public static function isSafePath(array $spec, ?string $path): bool
    {
        if ($path === null || $path === '' || strlen($path) > 255 || $spec['prefix'] === '' || ! str_starts_with($path, $spec['prefix'])) {
            return false;
        }
        if (! preg_match('#^[A-Za-z0-9._-]+(/[A-Za-z0-9._-]+)*$#', $path)) {
            return false;
        }
        foreach (explode('/', $path) as $segment) {
            if ($segment === '.' || $segment === '..') {
                return false;
            }
        }
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return $extension !== '' && in_array($extension, SafeUpload::storedExtensions(), true);
    }

    /** The file on this server's disk. Only call with a path isSafePath() accepted. */
    public static function absolute(array $spec, string $path): string
    {
        $root = rtrim((string) config("filesystems.disks.{$spec['disk']}.root"), '/\\');
        if ($root === '') {
            throw new \RuntimeException("Disk {$spec['disk']} has no root folder.");
        }

        return $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
    }

    /** Where partial transfers are kept until they are complete and checked. */
    public static function tmpDir(): string
    {
        $root = rtrim((string) config('filesystems.disks.local.root'), '/\\');

        return $root.DIRECTORY_SEPARATOR.trim((string) config('sync.files.tmp_dir', 'sync-files-tmp'), '/\\');
    }
}
