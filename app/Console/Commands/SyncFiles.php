<?php

namespace App\Console\Commands;

use App\Support\AppMode;
use App\Sync\Engine\ReplicationRules;
use App\Sync\Engine\SyncAgent;
use App\Sync\Engine\SyncClient;
use App\Sync\Files\FileColumns;
use App\Sync\Files\FileIndex;
use App\Sync\Files\FileTransfer;
use App\Sync\SyncRegistry;
use App\Sync\SyncState;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Files of synchronised records (Checkpoint 7): what is here, wanted or
 * failed; integrity checks; and the bookkeeping after a bootstrap or a
 * restore. See docs/checkpoint-7-files-security-backup.md.
 */
class SyncFiles extends Command
{
    protected $signature = 'sync:files
        {action=status : status, verify, scan, checksum, retry or run}
        {--table= : Only this table}
        {--refetch : verify: fetch a damaged or missing file again from the cloud (vessel)}';

    protected $description = 'Show, verify and repair the files of synchronised records (attachments, photos, signatures, certificate copies)';

    public function handle(FileIndex $index, SyncRegistry $registry, AppMode $mode, SyncState $state): int
    {
        return match ($this->argument('action')) {
            'status' => $this->status($index, $state),
            'verify' => $this->verify($index, $registry, $mode, $state),
            'scan' => $this->scan($index, $registry, $mode, $state),
            'checksum' => $this->checksum(),
            'retry' => $this->retry(),
            'run' => $this->runNow($mode, $state),
            default => $this->refuse('Unknown action. Use status, verify, scan, checksum, retry or run.'),
        };
    }

    private function status(FileIndex $index, SyncState $state): int
    {
        $counts = $index->counts() + array_fill_keys([FileIndex::PRESENT, FileIndex::WANTED, FileIndex::FAILED, FileIndex::MISSING], 0);
        $this->table(['Here', 'Waiting', 'Failed', 'Missing'], [[$counts['present'], $counts['wanted'], $counts['failed'], $counts['missing']]]);

        $files = (array) $state->get('files', []);
        if (isset($files['last_run_at'])) {
            $this->line('Last transfer: '.date('Y-m-d H:i:s', (int) $files['last_run_at']).(isset($files['last_error']) && $files['last_error'] ? ' ('.$files['last_error'].')' : ''));
        }
        $verify = (array) $state->get('files.verify', []);
        if (isset($verify['at'])) {
            $this->line('Last integrity check: '.date('Y-m-d H:i:s', (int) $verify['at']).": {$verify['checked']} checked, {$verify['bad']} damaged or missing.");
        }

        $problems = DB::table('sync_files')->whereIn('state', [FileIndex::FAILED, FileIndex::MISSING])->orderBy('id')->limit(30)->get();
        if ($problems->isNotEmpty()) {
            $this->newLine();
            $this->table(['Record', 'Path', 'State', 'Why'], $problems->map(fn ($p) => [
                "{$p->table_name} {$p->row_uuid}", $p->path, $p->state, mb_strimwidth((string) $p->last_error, 0, 70, '...'),
            ])->all());
        }

        return ($counts['failed'] + $counts['missing']) === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Reads every file a record names and compares it with its checksum:
     * the record's own (attachments.sha256) or the one recorded when the
     * file was stored or received. Read-only unless --refetch.
     */
    private function verify(FileIndex $index, SyncRegistry $registry, AppMode $mode, SyncState $state): int
    {
        $checked = 0;
        $bad = [];
        $cloud = $mode->isVessel() ? $state->get('cloud.node_id') : null;

        foreach ($this->tables() as $table) {
            $uuidColumn = $registry->uuidColumn($table);
            foreach (FileColumns::forTable($table) as $column => $spec) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }
                DB::table($table)->whereNotNull($column)->orderBy('id')->chunk(500, function ($rows) use ($index, $table, $column, $spec, $uuidColumn, $cloud, &$checked, &$bad) {
                    foreach ($rows as $row) {
                        $path = (string) $row->{$column};
                        $uuid = strtolower((string) $row->{$uuidColumn});
                        if (! FileColumns::isSafePath($spec, $path)) {
                            continue;
                        }
                        $checked++;
                        $file = FileColumns::absolute($spec, $path);
                        $entry = $index->entry($table, $uuid, $column);
                        $expected = $spec['sha256'] !== null && ! empty($row->{$spec['sha256']}) ? strtolower($row->{$spec['sha256']})
                            : ($entry !== null && $entry->state === FileIndex::PRESENT && $entry->path === $path ? $entry->sha256 : null);

                        if ($entry !== null && $entry->state === FileIndex::WANTED) {
                            continue; // on its way
                        }
                        if (! is_file($file)) {
                            $bad[] = [$table, $uuid, $path, 'missing'];
                        } elseif ($expected !== null && ! hash_equals($expected, hash_file('sha256', $file))) {
                            $bad[] = [$table, $uuid, $path, 'does not match its checksum'];
                        } else {
                            if ($expected === null) {
                                $index->describe($table, $uuid, $column, $path);   // first sight: remember it
                            } else {
                                DB::table('sync_files')->where(['table_name' => $table, 'row_uuid' => $uuid, 'column_name' => $column])->update(['verified_at' => now()]);
                            }

                            continue;
                        }

                        $peer = $this->option('refetch') ? $cloud : null;
                        $index->reconcile($table, $uuid, $column, $path, $expected, null, $peer);
                    }
                });
            }
        }

        $state->put('files.verify', ['at' => now()->getTimestamp(), 'checked' => $checked, 'bad' => count($bad)]);
        $this->info("{$checked} file(s) checked.");
        if ($bad !== []) {
            $this->error(count($bad).' file(s) damaged or missing:');
            $this->table(['Table', 'Record', 'Path', 'Problem'], array_slice($bad, 0, 50));
            if ($this->option('refetch')) {
                $this->line($cloud ? 'They will be fetched again from the cloud in the next sync cycle.' : 'Only a vessel fetches files again; restore them from the file backup.');
            } else {
                $this->line('Restore them from the file backup, or on a vessel run "sync:files verify --refetch".');
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Records every file the records name, after a bootstrap or a restore:
     * here, or (on a vessel) wanted from the cloud.
     */
    private function scan(FileIndex $index, SyncRegistry $registry, AppMode $mode, SyncState $state): int
    {
        $peer = $mode->isVessel() ? $state->get('cloud.node_id') : null;
        if ($mode->isVessel() && $peer === null) {
            $this->warn('This vessel has not reached the cloud yet, so missing files are only listed. Run a sync cycle first to fetch them.');
        }
        $states = [];

        foreach ($this->tables() as $table) {
            $uuidColumn = $registry->uuidColumn($table);
            foreach (FileColumns::forTable($table) as $column => $spec) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }
                DB::table($table)->whereNotNull($column)->orderBy('id')->chunk(500, function ($rows) use ($index, $table, $column, $uuidColumn, $peer, &$states) {
                    foreach ($rows as $row) {
                        $file = $index->columnsOf($table, $row)[$column];
                        $result = $index->reconcile($table, (string) $row->{$uuidColumn}, $column, $file['path'], $file['sha256'], $file['size'], $peer);
                        $states[$result] = ($states[$result] ?? 0) + 1;
                    }
                });
            }
        }

        $this->table(['Result', 'Files'], collect($states)->map(fn ($n, $s) => [$s, $n])->values()->all());

        return self::SUCCESS;
    }

    /** Fills attachments.sha256 for files uploaded before Checkpoint 7. Reads the files only. */
    private function checksum(): int
    {
        if (! Schema::hasColumn('attachments', 'sha256')) {
            return $this->refuse('attachments.sha256 does not exist. Run "php artisan migrate" first.');
        }
        $spec = FileColumns::spec('attachments', 'path');
        $filled = 0;
        $missing = 0;
        DB::table('attachments')->whereNull('sha256')->orderBy('id')->chunk(200, function ($rows) use ($spec, &$filled, &$missing) {
            foreach ($rows as $row) {
                $file = $spec !== null && FileColumns::isSafePath($spec, (string) $row->path) ? FileColumns::absolute($spec, (string) $row->path) : null;
                if ($file === null || ! is_file($file)) {
                    $missing++;

                    continue;
                }
                DB::table('attachments')->where('id', $row->id)->update(['sha256' => hash_file('sha256', $file)]);
                $filled++;
            }
        });
        $this->info("Checksums recorded for {$filled} attachment(s).".($missing ? " {$missing} file(s) were not found." : ''));

        return self::SUCCESS;
    }

    private function retry(): int
    {
        $count = DB::table('sync_files')->where('state', FileIndex::FAILED)->whereNotNull('peer_node')
            ->update(['state' => FileIndex::WANTED, 'attempts' => 0, 'next_attempt_at' => null, 'updated_at' => now()]);
        $this->info("{$count} file(s) will be tried again in the next sync cycle.");

        return self::SUCCESS;
    }

    private function runNow(AppMode $mode, SyncState $state): int
    {
        $agent = app(SyncAgent::class);
        if ($problems = $agent->configurationProblems()) {
            return $this->refuse(implode(' ', $problems));
        }
        $cloud = $state->get('cloud.node_id');
        if ($cloud === null) {
            return $this->refuse('Run a sync cycle first (php artisan sync:run), so this vessel knows the cloud.');
        }
        $result = $agent->transferFiles(SyncClient::fromConfig(), (string) $cloud);
        if (isset($result['error'])) {
            return $this->refuse($result['error']);
        }
        $this->table(['Sent', 'Received', 'Failed', 'Waiting', 'Bytes'], [[$result['uploaded'], $result['downloaded'], $result['failed'], $result['waiting'], $result['bytes']]]);
        foreach ($result['errors'] as $error) {
            $this->warn($error);
        }

        return $result['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @return list<string> */
    private function tables(): array
    {
        $only = $this->option('table');
        $tables = array_values(array_filter(FileColumns::tables(), fn ($t) => Schema::hasTable($t)));

        return $only ? array_values(array_intersect($tables, [$only])) : $tables;
    }

    private function refuse(string $message): int
    {
        $this->error($message);

        return self::FAILURE;
    }
}
