<?php

namespace App\Sync\Files;

use App\Sync\Engine\SyncClient;
use App\Sync\Engine\SyncTransportException;
use Throwable;

/**
 * The vessel's file transfers, run after the records of each sync cycle
 * (SyncAgent) or on demand (php artisan sync:files run).
 *
 * Up: the cloud lists the files it is waiting for from this vessel; each is
 * sent in pieces from where the cloud says it got to, so a link that drops
 * halfway through a large PDF loses one piece, not the file.
 *
 * Down: every file this vessel is waiting for from the cloud is fetched in
 * pieces into a partial file, checked against the SHA-256 the cloud gives
 * (and the record's own, where it has one), and only then put in place.
 *
 * A failure with one file never stops the others or the records; it is
 * counted and tried again later (FileIndex::attemptFailed). Each cycle moves
 * at most sync.files.max_bytes_per_cycle, so files never crowd out the
 * records on a slow link.
 */
class FileTransfer
{
    private int $budget = 0;

    public function __construct(private FileIndex $index)
    {
    }

    /** @return array{uploaded: int, downloaded: int, bytes: int, failed: int, waiting: int, errors: list<string>} */
    public function run(SyncClient $client, string $cloudNode, bool $askCloud = true): array
    {
        $this->budget = max(1, (int) config('sync.files.max_bytes_per_cycle', 50 * 1024 * 1024));
        $summary = ['uploaded' => 0, 'downloaded' => 0, 'bytes' => 0, 'failed' => 0, 'waiting' => 0, 'errors' => []];

        if ($askCloud) {
            $this->uploads($client, $summary);
        }
        $this->downloads($client, $cloudNode, $summary);

        $summary['waiting'] = count($this->index->due($cloudNode, 10000));
        $summary['errors'] = array_slice($summary['errors'], 0, 20);

        return $summary;
    }

    private function uploads(SyncClient $client, array &$summary): void
    {
        $answer = $client->raw('GET', '/files/wanted', ['limit' => 200]);
        $data = json_decode($answer['body'], true);
        if ($answer['status'] !== 200 || ! is_array($data['files'] ?? null)) {
            throw new FileTransferException('The cloud did not list the files it is waiting for (HTTP '.$answer['status'].').');
        }

        foreach ($data['files'] as $wanted) {
            if ($this->budget <= 0) {
                break;
            }
            try {
                if ($this->upload($client, $wanted, $summary)) {
                    $summary['uploaded']++;
                }
            } catch (SyncTransportException $e) {
                throw $e; // the link is down: stop, the next cycle carries on
            } catch (Throwable $e) {
                $summary['failed']++;
                $summary['errors'][] = "Up {$this->describe($wanted)}: {$e->getMessage()}";
            }
        }
    }

    private function upload(SyncClient $client, array $wanted, array &$summary): bool
    {
        $table = (string) ($wanted['table'] ?? '');
        $uuid = strtolower((string) ($wanted['row_uuid'] ?? ''));
        $column = (string) ($wanted['column'] ?? '');
        $spec = FileColumns::spec($table, $column);
        $row = $spec === null ? null : $this->index->rowByUuid($table, $uuid);
        $path = "/files/{$table}/{$uuid}/{$column}";

        $file = $row === null || ($row->{$column} ?? null) === null ? null : $this->index->describe($table, $uuid, $column, (string) $row->{$column});
        if ($file === null || (isset($wanted['sha256']) && $wanted['sha256'] !== null && ! hash_equals((string) $wanted['sha256'], $file['sha256']))) {
            // Not here, or not the file the record names any more.
            $client->raw('PUT', $path, [], '', ['X-File-Missing' => '1', 'X-Sync-Body-SHA256' => hash('sha256', '')]);
            throw new FileTransferException($file === null ? 'Not on this server.' : 'The file here does not match the record\'s checksum.');
        }

        $offset = max(0, min((int) ($wanted['received'] ?? 0), $file['size']));
        $chunkSize = max(1024, (int) config('sync.files.chunk_bytes', 512 * 1024));
        $handle = fopen($file['file'], 'rb');
        $requests = 0;
        $maxRequests = intdiv($file['size'], $chunkSize) + 10;

        try {
            do {
                if (++$requests > $maxRequests) {
                    throw new FileTransferException('The cloud kept asking for other pieces.');
                }
                fseek($handle, $offset);
                $chunk = $offset >= $file['size'] ? '' : (string) fread($handle, $chunkSize);
                $answer = $client->raw('PUT', $path, [], $chunk, [
                    'X-File-SHA256' => $file['sha256'], 'X-File-Size' => (string) $file['size'], 'X-File-Offset' => (string) $offset,
                    'X-Sync-Body-SHA256' => hash('sha256', $chunk),
                ]);
                $data = json_decode($answer['body'], true) ?: [];
                if ($answer['status'] === 409 && isset($data['received'])) {
                    $offset = (int) $data['received'];   // the cloud has more, or less: carry on from there

                    continue;
                }
                if ($answer['status'] !== 200) {
                    throw new FileTransferException('Cloud answered '.$answer['status'].': '.($data['message'] ?? ''));
                }
                $this->budget -= strlen($chunk);
                $summary['bytes'] += strlen($chunk);
                $offset = (int) ($data['received'] ?? $offset + strlen($chunk));
                if (($data['complete'] ?? false) === true) {
                    return true;
                }
            } while ($this->budget > 0 && $offset <= $file['size']);
        } finally {
            fclose($handle);
        }

        return false; // the rest next cycle
    }

    private function downloads(SyncClient $client, string $cloudNode, array &$summary): void
    {
        foreach ($this->index->due($cloudNode) as $entry) {
            if ($this->budget <= 0) {
                break;
            }
            try {
                if ($this->download($client, $entry, $summary)) {
                    $summary['downloaded']++;
                }
            } catch (SyncTransportException $e) {
                throw $e;
            } catch (Throwable $e) {
                $summary['failed']++;
                $summary['errors'][] = "Down {$entry->table_name}/{$entry->row_uuid}/{$entry->column_name}: {$e->getMessage()}";
                $e instanceof FileTooLarge
                    ? $this->index->failNow($entry, $e->getMessage())
                    : $this->index->attemptFailed($entry, $e->getMessage());
            }
        }
    }

    private function download(SyncClient $client, object $entry, array &$summary): bool
    {
        $spec = FileColumns::spec($entry->table_name, $entry->column_name);
        if ($spec === null || ! FileColumns::isSafePath($spec, $entry->path)) {
            throw new FileTransferException('The path is not one this server stores files under.');
        }
        $part = FileColumns::tmpDir().DIRECTORY_SEPARATOR.'down-'.$entry->table_name.'-'.$entry->row_uuid.'-'.$entry->column_name.'.part';
        if (! is_dir(dirname($part))) {
            @mkdir(dirname($part), 0750, true);
        }
        $chunkSize = max(1024, (int) config('sync.files.chunk_bytes', 512 * 1024));
        $path = "/files/{$entry->table_name}/{$entry->row_uuid}/{$entry->column_name}";
        $expected = $entry->sha256 ?? $this->index->recordedChecksum($spec['disk'], $entry->path);

        while ($this->budget > 0) {
            clearstatcache(true, $part);
            $offset = is_file($part) ? (int) filesize($part) : 0;
            $answer = $client->raw('GET', $path, ['offset' => $offset, 'length' => $chunkSize]);
            if ($answer['status'] === 416) {
                @unlink($part);   // the file changed since the last piece

                continue;
            }
            if ($answer['status'] !== 200) {
                $data = json_decode($answer['body'], true) ?: [];
                throw new FileTransferException('Cloud answered '.$answer['status'].': '.($data['message'] ?? ''));
            }

            $headers = $answer['headers'];
            $sha = strtolower((string) ($headers['x-file-sha256'] ?? ''));
            $size = (int) ($headers['x-file-size'] ?? -1);
            if ($size > (int) config('sync.files.max_file_bytes')) {
                @unlink($part);
                throw new FileTooLarge(sprintf('The file is %.1f MB, larger than the %s a server accepts (PD-22).', $size / 1048576, FileIndex::sizeLimitText()));
            }
            if (! preg_match('/^[0-9a-f]{64}$/', $sha) || $size < 0) {
                throw new FileTransferException('The cloud sent no usable checksum or size.');
            }
            if ($expected !== null && ! hash_equals((string) $expected, $sha)) {
                @unlink($part);
                throw new FileTransferException('The cloud\'s file does not match the checksum in the record.');
            }
            if ((int) ($headers['x-file-offset'] ?? -1) !== $offset || ! hash_equals(strtolower((string) ($headers['x-chunk-sha256'] ?? '')), hash('sha256', $answer['body']))) {
                throw new FileTransferException('A piece arrived damaged.');
            }

            file_put_contents($part, $answer['body'], FILE_APPEND | LOCK_EX);
            $this->budget -= strlen($answer['body']);
            $summary['bytes'] += strlen($answer['body']);
            clearstatcache(true, $part);
            $have = (int) filesize($part);

            if ($have > $size) {
                @unlink($part);
                throw new FileTransferException('More bytes arrived than the file has.');
            }
            if ($have < $size && $answer['body'] !== '') {
                continue;
            }

            $actual = hash_file('sha256', $part);
            if (! hash_equals($sha, $actual)) {
                @unlink($part);
                throw new FileTransferException('The file did not match its checksum and was discarded.');
            }
            FileStorage::place($part, FileColumns::absolute($spec, $entry->path));
            $this->index->put($entry->table_name, $entry->row_uuid, $entry->column_name, ['sha256' => $actual, 'size' => $size,
                'state' => FileIndex::PRESENT, 'peer_node' => null, 'last_error' => null, 'verified_at' => now()]);

            return true;
        }

        return false;
    }

    private function describe(array $wanted): string
    {
        return ($wanted['table'] ?? '?').'/'.($wanted['row_uuid'] ?? '?').'/'.($wanted['column'] ?? '?');
    }
}
