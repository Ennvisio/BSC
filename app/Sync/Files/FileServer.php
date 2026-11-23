<?php

namespace App\Sync\Files;

use App\Sync\Engine\ReplicationRules;
use App\Sync\Engine\RowScope;
use App\Sync\Engine\SyncServer;
use App\Sync\Models\SyncNode;
use App\Sync\SyncSql;
use Illuminate\Support\Facades\DB;

/**
 * The cloud's side of the file channel (/api/sync/v1/files).
 *
 * Uploads: the cloud only accepts a file it is waiting for from that very
 * vessel (a record the vessel pushed names it), written where its own copy
 * of the record says, in pieces appended at the expected offset, and put
 * in place only when the whole file matches the SHA-256 the vessel
 * declared (and the record's own checksum, where it has one).
 *
 * Downloads: a vessel may read only the files of its own records and of
 * records the cloud sent it (sync_file_grants), in pieces, with the whole
 * file's SHA-256, so it can check what it put together.
 */
class FileServer
{
    public function __construct(private FileIndex $index, private ReplicationRules $rules, private RowScope $scope)
    {
    }

    /** @return array{protocol: int, files: list<array>} */
    public function wanted(SyncNode $node, int $limit): array
    {
        $files = [];
        foreach ($this->index->due($node->node_id, max(1, min($limit, 500))) as $entry) {
            $files[] = [
                'table' => $entry->table_name, 'row_uuid' => $entry->row_uuid, 'column' => $entry->column_name,
                'sha256' => $entry->sha256, 'size' => $entry->size === null ? null : (int) $entry->size,
                'received' => $this->partSize($this->partFile($node, $entry)),
            ];
        }

        return ['protocol' => SyncServer::PROTOCOL, 'files' => $files];
    }

    /**
     * One piece of an upload.
     *
     * @return array{status: int, body: array}
     */
    public function receive(SyncNode $node, string $table, string $uuid, string $column, array $headers, string $chunk): array
    {
        $entry = $this->wantedEntry($node, $table, $uuid, $column);
        if ($entry === null) {
            return $this->answer(404, 'This server is not waiting for that file from this vessel.');
        }
        $spec = FileColumns::spec($table, $column);
        $row = $this->index->rowByUuid($table, $uuid);
        $path = $row?->{$column} ?? null;
        if ($row === null || $path === null || $path !== $entry->path || ! FileColumns::isSafePath($spec, (string) $path)) {
            return $this->answer(409, 'The record no longer names this file.');
        }

        if (($headers['missing'] ?? false) === true) {
            $this->index->attemptFailed($entry, 'The vessel does not have this file.');

            return $this->answer(200, 'Noted.', ['received' => 0, 'complete' => false]);
        }

        $declaredSha = strtolower((string) ($headers['sha256'] ?? ''));
        $size = $headers['size'] ?? null;
        $offset = $headers['offset'] ?? null;
        if (! preg_match('/^[0-9a-f]{64}$/', $declaredSha) || ! is_int($size) || $size < 0 || ! is_int($offset) || $offset < 0) {
            return $this->answer(400, 'X-File-SHA256, X-File-Size and X-File-Offset are required.');
        }
        if ($size > (int) config('sync.files.max_file_bytes')) {
            return $this->answer(413, 'The file is larger than this server accepts.');
        }
        $expected = $entry->sha256 ?? $this->index->recordedChecksum($spec['disk'], (string) $entry->path);
        if ($expected !== null && ! hash_equals((string) $expected, $declaredSha)) {
            $this->index->attemptFailed($entry, 'The vessel offered a file whose checksum differs from the record\'s.');

            return $this->answer(422, 'The file\'s checksum differs from the one in the record.');
        }
        if (strlen($chunk) > (int) config('sync.files.chunk_bytes') * 2) {
            return $this->answer(413, 'The piece is too large.');
        }
        if (! hash_equals(strtolower((string) ($headers['chunk_sha256'] ?? '')), hash('sha256', $chunk))) {
            return $this->answer(400, 'The piece does not match its checksum.');
        }

        $part = $this->partFile($node, $entry);
        $meta = $part.'.json';
        $known = is_file($meta) ? json_decode((string) file_get_contents($meta), true) : null;
        if (! is_array($known) || ($known['sha256'] ?? null) !== $declaredSha || ($known['size'] ?? null) !== $size) {
            @unlink($part);
            $this->ensureDir(dirname($part));
            file_put_contents($meta, json_encode(['sha256' => $declaredSha, 'size' => $size]));
        }
        $received = $this->partSize($part);
        if ($offset !== $received) {
            return $this->answer(409, "Expected the piece at byte {$received}.", ['received' => $received, 'complete' => false]);
        }
        if ($received + strlen($chunk) > $size) {
            @unlink($part);

            return $this->answer(400, 'More bytes than the declared size.', ['received' => 0, 'complete' => false]);
        }
        file_put_contents($part, $chunk, FILE_APPEND | LOCK_EX);
        $received += strlen($chunk);

        if ($received < $size) {
            return $this->answer(200, 'Piece stored.', ['received' => $received, 'complete' => false]);
        }

        $actual = hash_file('sha256', $part);
        if (! hash_equals($declaredSha, $actual)) {
            @unlink($part);
            @unlink($meta);
            $this->index->attemptFailed($entry, 'The uploaded file did not match its checksum and was discarded.');

            return $this->answer(422, 'The file does not match its checksum. Send it again.', ['received' => 0, 'complete' => false]);
        }

        FileStorage::place($part, FileColumns::absolute($spec, (string) $path));
        @unlink($meta);
        $this->index->put($table, $uuid, $column, ['path' => $path, 'sha256' => $actual, 'size' => $size, 'state' => FileIndex::PRESENT,
            'peer_node' => null, 'last_error' => null, 'verified_at' => now()]);

        return $this->answer(200, 'File received.', ['received' => $received, 'complete' => true]);
    }

    /**
     * One piece of a download.
     *
     * @return array{status: int, body: string|array, headers: array<string, string>}
     */
    public function send(SyncNode $node, string $table, string $uuid, string $column, int $offset, int $length): array
    {
        if (! $this->servable($table, $column) || ! SyncSql::isUuid($uuid)) {
            return ['status' => 404, 'body' => ['message' => 'No such file.'], 'headers' => []];
        }
        // The vessel's own records (to fetch its files back after a restore),
        // and records the cloud sent it.
        $row = $this->index->rowByUuid($table, $uuid);
        $entitled = $row !== null && (
            DB::table('sync_file_grants')->where(['vessel_id' => (int) $node->vessel_id, 'table_name' => $table, 'row_uuid' => strtolower($uuid)])->exists()
            || $this->scope->vesselOf($table, $row->id) === (int) $node->vessel_id);
        $row = $entitled ? $row : null;
        $path = $row?->{$column} ?? null;
        $file = $path === null ? null : $this->index->describe($table, strtolower($uuid), $column, (string) $path);
        if ($file === null) {
            // The same answer whether the file is not here or not this
            // vessel's, so a vessel cannot probe for other vessels' files.
            return ['status' => 404, 'body' => ['message' => 'No such file.'], 'headers' => []];
        }

        $length = max(1, min($length, (int) config('sync.files.chunk_bytes')));
        if ($offset < 0 || $offset > $file['size']) {
            return ['status' => 416, 'body' => ['message' => 'Offset outside the file.'], 'headers' => []];
        }
        $handle = fopen($file['file'], 'rb');
        fseek($handle, $offset);
        $bytes = $offset === $file['size'] ? '' : (string) fread($handle, $length);
        fclose($handle);

        return ['status' => 200, 'body' => $bytes, 'headers' => [
            'Content-Type' => 'application/octet-stream',
            'X-File-SHA256' => $file['sha256'],
            'X-File-Size' => (string) $file['size'],
            'X-File-Offset' => (string) $offset,
            'X-Chunk-SHA256' => hash('sha256', $bytes),
            'X-Sync-Protocol' => (string) SyncServer::PROTOCOL,
        ]];
    }

    private function wantedEntry(SyncNode $node, string $table, string $uuid, string $column): ?object
    {
        if (! $this->servable($table, $column) || ! SyncSql::isUuid($uuid)) {
            return null;
        }
        $entry = $this->index->entry($table, $uuid, $column);

        return $entry !== null && $entry->state === FileIndex::WANTED && $entry->peer_node === strtolower($node->node_id) ? $entry : null;
    }

    private function servable(string $table, string $column): bool
    {
        return FileColumns::spec($table, $column) !== null && $this->rules->isEnabled($table);
    }

    private function partFile(SyncNode $node, object $entry): string
    {
        return FileColumns::tmpDir().DIRECTORY_SEPARATOR.'up-'.strtolower($node->node_id).'-'.$entry->table_name.'-'.$entry->row_uuid.'-'.$entry->column_name.'.part';
    }

    private function partSize(string $file): int
    {
        clearstatcache(true, $file);

        return is_file($file) ? (int) filesize($file) : 0;
    }

    private function ensureDir(string $dir): void
    {
        if (! is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
    }

    private function answer(int $status, string $message, array $extra = []): array
    {
        return ['status' => $status, 'body' => ['protocol' => SyncServer::PROTOCOL, 'message' => $message] + $extra];
    }
}
