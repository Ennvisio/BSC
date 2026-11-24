<?php

namespace App\Sync\Files;

use App\Sync\SyncRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * This server's view of the files its records refer to (sync_files): one
 * entry per table, row and column.
 *
 * present  the file is here; sha256 and size are what it was when last read
 * wanted   a record arrived from peer_node and the file must come from there
 * failed   it could not be fetched or did not check out max_attempts times,
 *          or its path is unsafe; an operator looks (sync:files)
 * missing  the record is here and the file should be too, but is not
 */
class FileIndex
{
    public const PRESENT = 'present';

    public const WANTED = 'wanted';

    public const FAILED = 'failed';

    public const MISSING = 'missing';

    public function __construct(private SyncRegistry $registry)
    {
    }

    public function entry(string $table, string $uuid, string $column): ?object
    {
        return DB::table('sync_files')->where(['table_name' => $table, 'row_uuid' => strtolower($uuid), 'column_name' => $column])->first();
    }

    /** @param array<string, mixed> $fields */
    public function put(string $table, string $uuid, string $column, array $fields): void
    {
        $key = ['table_name' => $table, 'row_uuid' => strtolower($uuid), 'column_name' => $column];
        $now = now();
        $existing = DB::table('sync_files')->where($key)->first();

        if ($existing === null) {
            DB::table('sync_files')->insert($key + $fields + ['attempts' => 0, 'created_at' => $now, 'updated_at' => $now]);
        } else {
            DB::table('sync_files')->where('id', $existing->id)->update($fields + ['updated_at' => $now]);
        }
    }

    public function forget(string $table, string $uuid, ?string $column = null): void
    {
        DB::table('sync_files')
            ->where('table_name', $table)->where('row_uuid', strtolower($uuid))
            ->when($column !== null, fn ($q) => $q->where('column_name', $column))
            ->delete();
    }

    /**
     * What this server should do about the file a record now names: it is
     * here (and checks out), or it is wanted from $peer.
     *
     * @return string the state recorded
     */
    public function reconcile(string $table, string $uuid, string $column, ?string $path, ?string $expectedSha, ?int $expectedSize, ?string $peer): string
    {
        $spec = FileColumns::spec($table, $column);
        if ($spec === null) {
            return 'none';
        }
        if ($path === null || $path === '') {
            $this->forget($table, $uuid, $column);

            return 'none';
        }
        if (! FileColumns::isSafePath($spec, $path)) {
            $this->put($table, $uuid, $column, ['path' => mb_substr($path, 0, 500), 'sha256' => $expectedSha, 'size' => $expectedSize,
                'state' => self::FAILED, 'peer_node' => $peer, 'last_error' => 'The path is not one this server stores files under, so it is never fetched.']);

            return self::FAILED;
        }

        $file = FileColumns::absolute($spec, $path);
        if (is_file($file)) {
            $sha = hash_file('sha256', $file);
            if ($expectedSha === null || hash_equals(strtolower($expectedSha), $sha)) {
                $this->put($table, $uuid, $column, ['path' => $path, 'sha256' => $sha, 'size' => filesize($file),
                    'state' => self::PRESENT, 'peer_node' => null, 'last_error' => null, 'verified_at' => now()]);

                return self::PRESENT;
            }
        }

        if ($peer === null) {
            $this->put($table, $uuid, $column, ['path' => $path, 'sha256' => $expectedSha, 'size' => $expectedSize, 'state' => self::MISSING,
                'peer_node' => null, 'last_error' => is_file($file) ? 'The file here does not match its checksum.' : 'The file is not on this server.']);

            return self::MISSING;
        }

        $current = $this->entry($table, $uuid, $column);
        $same = $current !== null && $current->state === self::WANTED && $current->path === $path;
        $this->put($table, $uuid, $column, ['path' => $path, 'sha256' => $expectedSha, 'size' => $expectedSize, 'state' => self::WANTED,
            'peer_node' => strtolower($peer), 'last_error' => $same ? $current->last_error : null]
            + ($same ? [] : ['attempts' => 0, 'next_attempt_at' => null]));

        return self::WANTED;
    }

    /**
     * The checksum a record on this server gives for the file at $path, or
     * null when none does. The same stored file can be named by records
     * of two tables (a certificate's current copy is also in its copy
     * history); a record that keeps the file's checksum then vouches for
     * it whichever record it is fetched for, so a damaged file never
     * comes in through the record that keeps no checksum.
     */
    public function recordedChecksum(string $disk, string $path): ?string
    {
        foreach (FileColumns::tables() as $table) {
            foreach (FileColumns::forTable($table) as $column => $spec) {
                if ($spec['sha256'] === null || $spec['disk'] !== $disk || ! str_starts_with($path, $spec['prefix']) || ! Schema::hasTable($table)) {
                    continue;
                }
                $sha = DB::table($table)->where($column, $path)->whereNotNull($spec['sha256'])->value($spec['sha256']);
                if ($sha !== null && $sha !== '') {
                    return strtolower((string) $sha);
                }
            }
        }

        return null;
    }

    /** The row's file columns with what the row itself says about each file. */
    public function columnsOf(string $table, object $row): array
    {
        $out = [];
        foreach (FileColumns::forTable($table) as $column => $spec) {
            if (! property_exists($row, $column)) {
                continue;
            }
            $out[$column] = [
                'path' => $row->{$column} === null ? null : (string) $row->{$column},
                'sha256' => $spec['sha256'] !== null && isset($row->{$spec['sha256']}) ? strtolower((string) $row->{$spec['sha256']}) : null,
                'size' => $spec['size'] !== null && isset($row->{$spec['size']}) ? (int) $row->{$spec['size']} : null,
            ];
        }

        return $out;
    }

    public function uuidOf(string $table, object $row): string
    {
        return strtolower((string) $row->{$this->registry->uuidColumn($table)});
    }

    public function rowByUuid(string $table, string $uuid): ?object
    {
        return DB::table($table)->where($this->registry->uuidColumn($table), strtolower($uuid))->first();
    }

    /** @return list<object> files wanted from $peer that are due */
    public function due(string $peer, int $limit = 200): array
    {
        return DB::table('sync_files')
            ->where('state', self::WANTED)->where('peer_node', strtolower($peer))
            ->where(fn ($q) => $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
            ->orderBy('id')->limit($limit)->get()->all();
    }

    /** One more failed attempt; after max_attempts the file is marked failed. */
    public function attemptFailed(object $entry, string $error): void
    {
        $attempts = (int) $entry->attempts + 1;
        $final = $attempts >= (int) config('sync.files.max_attempts', 20);
        DB::table('sync_files')->where('id', $entry->id)->update([
            'attempts' => $attempts,
            'state' => $final ? self::FAILED : self::WANTED,
            'last_error' => mb_substr($error.($final ? " Gave up after {$attempts} attempts." : ''), 0, 1000),
            'next_attempt_at' => now()->addSeconds(min(3600, 30 * (2 ** min($attempts, 7)))),
            'updated_at' => now(),
        ]);
    }

    /**
     * Marked failed at once, without further attempts: for a file that can
     * never travel (PD-22: larger than a server accepts). It stays listed
     * for an operator, like a file that failed max_attempts times.
     */
    public function failNow(object $entry, string $error): void
    {
        DB::table('sync_files')->where('id', $entry->id)->update([
            'attempts' => (int) $entry->attempts + 1,
            'state' => self::FAILED,
            'last_error' => mb_substr($error.' Not tried again.', 0, 1000),
            'next_attempt_at' => null,
            'updated_at' => now(),
        ]);
    }

    /** The largest file the servers exchange, for messages: "20 MB". */
    public static function sizeLimitText(): string
    {
        return round((int) config('sync.files.max_file_bytes') / 1048576, 1).' MB';
    }

    /**
     * The file a row names, as it is on this server now: its checksum and
     * size, or null when it is not here. The checksum is read again only
     * when the file changed since it was last read.
     *
     * @return array{file: string, sha256: string, size: int}|null
     */
    public function describe(string $table, string $uuid, string $column, string $path): ?array
    {
        $spec = FileColumns::spec($table, $column);
        if ($spec === null || ! FileColumns::isSafePath($spec, $path)) {
            return null;
        }
        $file = FileColumns::absolute($spec, $path);
        if (! is_file($file)) {
            return null;
        }
        clearstatcache(true, $file);
        $size = (int) filesize($file);
        $entry = $this->entry($table, $uuid, $column);
        if ($entry !== null && $entry->state === self::PRESENT && $entry->path === $path && (int) $entry->size === $size
            && $entry->sha256 !== null && $entry->verified_at !== null && strtotime((string) $entry->verified_at) >= filemtime($file)) {
            return ['file' => $file, 'sha256' => $entry->sha256, 'size' => $size];
        }

        $sha = hash_file('sha256', $file);
        $this->put($table, $uuid, $column, ['path' => $path, 'sha256' => $sha, 'size' => $size, 'state' => self::PRESENT,
            'peer_node' => null, 'last_error' => null, 'verified_at' => now()]);

        return ['file' => $file, 'sha256' => $sha, 'size' => $size];
    }

    /** @return array<string, int> state => count */
    public function counts(): array
    {
        return DB::table('sync_files')->selectRaw('state, COUNT(*) AS n')->groupBy('state')->pluck('n', 'state')
            ->map(fn ($n) => (int) $n)->all();
    }
}
