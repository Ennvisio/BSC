<?php

namespace App\Sync\Engine;

use App\Support\AppMode;
use App\Sync\Models\SyncBatch;
use App\Sync\Models\SyncConflict;
use App\Sync\SyncCursors;
use App\Sync\SyncLogger;
use App\Sync\SyncNodes;
use App\Sync\SyncSql;
use App\Sync\SyncState;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The vessel's half of the protocol: one sync cycle.
 *
 *   1. handshake  check both sides agree who they are; learn what the cloud
 *                 sends and accepts
 *   2. push       send local changes in batches until none are left. A
 *                 batch is stored before it is sent and sent again,
 *                 unchanged, until the cloud acknowledges it.
 *   3. pull       fetch cloud changes page by page. Each page is applied
 *                 and the cursor moved in one transaction.
 *   4. retry      changes still waiting for a record they refer to
 *
 * Any step can fail at any moment (link drop, power cut). Nothing is lost:
 * the outbox, the stored batch and the cursors are all in the database, and
 * the next cycle continues from where this one stopped.
 *
 * Cursors kept here, against the cloud's node id:
 *  - push: the end of the last batch the cloud acknowledged
 *  - pull: how far the cloud's stream has been applied here
 */
class SyncAgent
{
    public const STREAM_PUSH = 'push';

    public const STREAM_PULL = 'pull';

    public const LOCK = 'navisuite:sync-cycle';

    private ?Closure $clientFactory = null;

    public function __construct(
        private AppMode $mode,
        private SyncNodes $nodes,
        private SyncCursors $cursors,
        private SyncState $state,
        private OutboxSequencer $sequencer,
        private ChangePackager $packager,
        private InboxProcessor $processor,
        private ReplicationRules $rules,
        private RowScope $scope,
        private SyncLogger $logger,
        private RowBases $bases,
    ) {
    }

    /** For tests: how to reach the cloud. */
    public function useClient(Closure $factory): void
    {
        $this->clientFactory = $factory;
    }

    /** @return list<string> what is missing before this vessel can sync */
    public function configurationProblems(): array
    {
        $problems = [];
        if (! $this->mode->isVessel()) {
            $problems[] = 'Only a vessel server syncs with the cloud (APP_MODE=vessel).';
        } elseif ($this->mode->vesselId() === null) {
            $problems[] = 'APP_VESSEL_ID is not set.';
        }
        if ($this->nodes->thisNodeId() === null) {
            $problems[] = 'SYNC_NODE_ID is missing or not a UUID.';
        }
        if (trim((string) config('sync.node_token')) === '') {
            $problems[] = 'The sync token is not set (SYNC_NODE_TOKEN, or "php artisan navisuite:secrets set sync_node_token").';
        }
        $url = (string) config('sync.cloud_url');
        if (! preg_match('#^https://[^\s/]+#i', $url) && ! (app()->environment('testing') && str_starts_with($url, 'http'))) {
            $problems[] = 'SYNC_CLOUD_URL must be the cloud\'s https:// address.';
        }

        return $problems;
    }

    /** One full cycle. Throws on failure; the worker decides when to try again. */
    public function cycle(): array
    {
        if ($problems = $this->configurationProblems()) {
            throw new SyncProtocolException(implode(' ', $problems));
        }

        $lock = Cache::lock(self::LOCK, 1800);
        if (! $lock->get()) {
            return ['skipped' => 'Another sync cycle is running.'];
        }

        try {
            $started = microtime(true);
            $client = $this->client();
            $hello = $this->handshake($client);
            $cloud = $hello['cloud_node_id'];

            $push = $this->push($client, $cloud, $hello['tables']['up']);
            $pull = $this->pull($client, $cloud, $hello['tables']['down']);
            $retried = $this->processor->retryDeferred($cloud, $this->side($cloud, $hello['tables']['down']));
            // Ask for the cloud's list only when it may hold something: it
            // said so, or this cycle sent records (which may name files).
            $files = $this->transferFiles($client, $cloud, ($hello['files_wanted'] ?? 1) !== 0 || $push['events'] > 0);

            $summary = [
                'pushed' => $push, 'pulled' => $pull, 'retried' => $retried, 'files' => $files,
                'clock_skew' => $hello['clock_skew'],
                'duration_ms' => (int) ((microtime(true) - $started) * 1000),
            ];
            $this->logger->record('cycle', 'ok', [
                'node_id' => $cloud,
                'events' => $push['events'] + $pull['events'],
                'bytes' => $push['bytes'] + $pull['bytes'],
                'duration_ms' => $summary['duration_ms'],
                'message' => "Sent {$push['events']} in {$push['batches']} batch(es), received {$pull['events']} in {$pull['pages']} page(s).",
            ]);

            return $summary;
        } finally {
            $lock->release();
        }
    }

    private function handshake(SyncClient $client): array
    {
        $sentAt = microtime(true);
        $answer = $client->post('/handshake', [
            'protocol' => SyncServer::PROTOCOL,
            'node_id' => $this->nodes->thisNodeId(),
            'vessel_id' => $this->mode->vesselId(),
            'app_release' => config('navisuite.release'),
            'schema' => DB::table('migrations')->max('migration'),
            'time' => now()->utc()->format('Y-m-d\TH:i:s.uP'),
        ]);
        $receivedAt = microtime(true);

        $cloud = $answer['cloud_node_id'] ?? null;
        if (! SyncSql::isUuid($cloud)) {
            throw new SyncProtocolException('The cloud did not say who it is.');
        }
        $cloud = strtolower($cloud);

        if (($answer['vessel_id'] ?? null) !== $this->mode->vesselId()) {
            throw new SyncProtocolException("The cloud knows this node as vessel {$answer['vessel_id']}, but this server is vessel {$this->mode->vesselId()}. Check SYNC_NODE_ID and SYNC_NODE_TOKEN.");
        }

        $known = $this->state->get('cloud.node_id');
        if ($known !== null && $known !== $cloud) {
            throw new SyncProtocolException("The cloud's identity changed from {$known} to {$cloud}. Is SYNC_CLOUD_URL pointing at the right server? If the cloud really was replaced, see docs/checkpoint-5-sync-engine.md (\"Cloud replaced\").");
        }
        if ($known === null) {
            $this->state->put('cloud.node_id', $cloud);
        }

        $tables = $answer['tables'] ?? [];
        foreach (['down', 'up'] as $direction) {
            $list = $tables[$direction] ?? null;
            if (! is_array($list) || ! array_is_list($list)) {
                throw new SyncProtocolException('The cloud did not list the tables it syncs.');
            }
            $tables[$direction] = array_values(array_filter($list, 'is_string'));
        }

        // Half the round trip is the best guess of when the cloud read its clock.
        $skew = null;
        if (is_string($answer['server_time'] ?? null)) {
            $cloudTime = Carbon::parse($answer['server_time'])->getPreciseTimestamp(3) / 1000;
            $skew = (int) round($cloudTime - ($sentAt + $receivedAt) / 2);
        }

        $this->state->put('cloud.last_handshake', [
            'at' => now()->toIso8601String(), 'clock_skew' => $skew, 'tables' => $tables,
            'schema_matches' => (bool) ($answer['schema_matches'] ?? false), 'cloud_schema' => $answer['schema'] ?? null,
        ]);

        if ($skew !== null && abs($skew) > (int) config('sync.clock_skew_warning', 120)) {
            $this->logger->record('handshake', 'warning', ['node_id' => $cloud, 'message' => "This PC's clock is {$skew} s off the cloud's. Conflicts are decided by time; please set the clock."]);
        }
        $notHere = array_diff([...$tables['down'], ...$tables['up']], array_keys($this->rules->defined()));
        if ($notHere !== []) {
            $this->logger->record('handshake', 'warning', ['node_id' => $cloud, 'message' => 'The cloud syncs tables this release has no rule for: '.implode(', ', $notHere).'. Update this server.']);
        }

        return ['cloud_node_id' => $cloud, 'tables' => $tables, 'clock_skew' => $skew,
            'files_wanted' => is_int($answer['files_wanted'] ?? null) ? $answer['files_wanted'] : null];
    }

    private function push(SyncClient $client, string $cloud, array $cloudAccepts): array
    {
        $tables = array_values(array_intersect($this->rules->upTables(), $cloudAccepts));
        $totals = ['batches' => 0, 'events' => 0, 'bytes' => 0, 'rejected' => 0, 'conflicts' => 0];
        $maxPages = (int) config('sync.worker.max_pages', 50);

        for ($i = 0; $i < $maxPages; $i++) {
            $batch = SyncBatch::query()->where('direction', SyncBatch::OUT)->where('status', SyncBatch::STATUS_PENDING)->orderBy('id')->first();

            if ($batch === null) {
                $batch = $this->nextBatch($cloud, $tables);
                if ($batch === null) {
                    break; // nothing left to send
                }
            }

            $batch->forceFill(['attempts' => $batch->attempts + 1])->save();
            try {
                $answer = $client->post('/push', json_decode($batch->body, true));
            } catch (SyncTransportException $e) {
                $batch->forceFill(['last_error' => mb_substr($e->getMessage(), 0, 2000)])->save();
                throw $e;
            }

            if (($answer['batch_id'] ?? null) !== $batch->batch_id || ! is_array($answer['counts'] ?? null)) {
                throw new SyncProtocolException("The cloud's answer does not match batch {$batch->batch_id}.");
            }

            DB::transaction(function () use ($batch, $answer, $cloud) {
                $this->mirrorConflicts($batch, $answer, $cloud);
                $this->rememberAgreed($batch, $answer, $cloud);
                $batch->forceFill([
                    'status' => SyncBatch::STATUS_ACKED, 'acked_at' => now(), 'last_error' => null,
                    'response' => json_encode($answer, JSON_UNESCAPED_UNICODE),
                    'body' => null, // no longer needed
                ])->save();
                $this->cursors->advance($cloud, self::STREAM_PUSH, $batch->seq_to);
            });

            $totals['batches']++;
            $totals['events'] += $batch->events;
            $totals['bytes'] += $batch->bytes;
            $totals['rejected'] += (int) ($answer['counts'][InboxProcessor::REJECTED] ?? 0);
            $totals['conflicts'] += count($answer['conflicts'] ?? []);

            $this->logger->record('push', ($answer['counts'][InboxProcessor::REJECTED] ?? 0) > 0 ? 'warning' : 'ok', [
                'node_id' => $cloud,
                'events' => $batch->events,
                'bytes' => $batch->bytes,
                'message' => ($answer['repeated'] ?? false ? 'Acknowledged again (already applied). ' : '')."Batch {$batch->batch_id}: positions {$batch->seq_from} to {$batch->seq_to}.",
                'context' => ($answer['rejected'] ?? []) === [] ? null : json_encode(['rejected' => $answer['rejected']]),
            ]);
        }

        return $totals;
    }

    /** Packages and stores the next batch, or moves the cursor past events nobody needs. */
    private function nextBatch(string $cloud, array $tables): ?SyncBatch
    {
        for ($guard = 0; $guard < 1000; $guard++) {
            $this->sequencer->sequence();
            $highWater = $this->sequencer->highWater();
            $after = $this->cursors->position($cloud, self::STREAM_PUSH);
            if ($after >= $highWater) {
                return null;
            }

            $page = $this->packager->page($after, $highWater, function ($q) use ($tables) {
                $q->whereIn('table_name', $tables === [] ? ['-'] : $tables)->whereNull('origin_node');
            }, (int) config('sync.batch.max_events'), (int) config('sync.batch.max_bytes'));

            if ($page['events'] === []) {
                // Only changes that stay here (other tables, or changes that
                // came from the cloud): nothing to send for this stretch.
                $this->cursors->advance($cloud, self::STREAM_PUSH, $page['cursor']);

                continue;
            }

            $batchId = (string) Str::uuid();
            $body = [
                'protocol' => SyncServer::PROTOCOL,
                'batch_id' => $batchId,
                'seq_from' => $after + 1,
                'seq_to' => $page['cursor'],
                'created_at' => now()->utc()->format('Y-m-d\TH:i:s.uP'),
                'events' => $page['events'],
            ];
            $json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

            if ($page['dangling'] !== []) {
                $this->logger->record('push', 'warning', ['node_id' => $cloud, 'message' => 'Rows point at records that do not exist; sent without the reference.', 'context' => json_encode(['dangling_references' => $page['dangling']])]);
            }

            return SyncBatch::create([
                'batch_id' => $batchId, 'direction' => SyncBatch::OUT, 'node_id' => $cloud,
                'seq_from' => $after + 1, 'seq_to' => $page['cursor'], 'events' => count($page['events']),
                'bytes' => strlen($json), 'sha256' => hash('sha256', $json), 'body' => $json,
                'status' => SyncBatch::STATUS_PENDING, 'attempts' => 0,
            ]);
        }

        return null;
    }

    private function pull(SyncClient $client, string $cloud, array $cloudSends): array
    {
        $side = $this->side($cloud, $cloudSends);
        $totals = ['pages' => 0, 'events' => 0, 'bytes' => 0, 'deferred' => 0, 'rejected' => 0, 'conflicts' => 0];
        $after = $this->cursors->position($cloud, self::STREAM_PULL);
        $start = $after;
        $maxPages = (int) config('sync.worker.max_pages', 50);

        for ($i = 0; $i < $maxPages; $i++) {
            $page = $client->get('/pull', ['after' => $after, 'limit' => (int) config('sync.batch.max_events')]);

            $cursor = $page['cursor'] ?? null;
            $events = $page['events'] ?? null;
            if (! is_int($cursor) || $cursor < $after || ! is_array($events) || ! array_is_list($events)) {
                throw new SyncProtocolException('The cloud sent a malformed page.');
            }

            $pageId = (string) Str::uuid();
            $results = DB::transaction(function () use ($cloud, $events, $side, $pageId, $cursor) {
                $results = $this->processor->process($cloud, $events, $side, $pageId);
                $this->cursors->advance($cloud, self::STREAM_PULL, $cursor);

                return $results;
            });

            $totals['pages']++;
            $totals['events'] += count($events);
            $totals['bytes'] += strlen(json_encode($events));
            foreach ($results as $r) {
                $totals['deferred'] += $r['status'] === InboxProcessor::DEFERRED ? 1 : 0;
                $totals['rejected'] += $r['status'] === InboxProcessor::REJECTED ? 1 : 0;
                $totals['conflicts'] += $r['conflict'] !== null ? 1 : 0;
            }
            if ($events !== []) {
                $this->logger->record('pull', $totals['rejected'] > 0 ? 'warning' : 'ok', [
                    'node_id' => $cloud, 'events' => count($events),
                    'message' => "Applied positions {$after} to {$cursor}.",
                    'context' => json_encode(['problems' => array_values(array_filter($results, fn ($r) => in_array($r['status'], [InboxProcessor::REJECTED, InboxProcessor::DEFERRED], true)))]),
                ]);
            }

            $after = $cursor;
            if (! ($page['has_more'] ?? false)) {
                break;
            }
        }

        if ($after > $start) {
            // Lets the cloud know at once, rather than at the next pull, so
            // its conflict check knows what this vessel has seen.
            try {
                $client->post('/ack', ['protocol' => SyncServer::PROTOCOL, 'stream' => self::STREAM_PULL, 'cursor' => $after]);
            } catch (SyncTransportException $e) {
                $this->logger->record('ack', 'warning', ['node_id' => $cloud, 'message' => 'Acknowledgement not delivered; the next pull repeats it. '.$e->getMessage()]);
            }
        }

        return $totals;
    }

    /**
     * Business records the cloud applied exactly as sent: both sides now
     * hold these values (RowBases), which later merges compare against.
     */
    private function rememberAgreed(SyncBatch $batch, array $answer, string $cloud): void
    {
        $notAsSent = array_merge(
            array_column(array_filter((array) ($answer['rejected'] ?? []), 'is_array'), 'event_id'),
            array_column(array_filter((array) ($answer['conflicts'] ?? []), 'is_array'), 'event_id'),
        );

        foreach (json_decode($batch->body ?? '[]', true)['events'] ?? [] as $event) {
            if (($event['values'] ?? null) !== null && ! in_array($event['event_id'], $notAsSent, true) && $this->rules->mergesColumns($event['table'])) {
                $this->bases->remember($cloud, $event['table'], $event['row_uuid'], $event['values']);
            }
        }
    }

    /** Conflicts the cloud found in our batch, recorded here too, so the crew can see them. */
    private function mirrorConflicts(SyncBatch $batch, array $answer, string $cloud): void
    {
        foreach ($answer['conflicts'] ?? [] as $c) {
            if (! is_array($c) || ! SyncSql::isUuid($c['event_id'] ?? null)) {
                continue;
            }
            $event = collect(json_decode($batch->body ?? '[]', true)['events'] ?? [])->firstWhere('event_id', $c['event_id']);
            SyncConflict::create([
                'event_id' => $c['event_id'], 'source_node' => $cloud, 'detected_by' => ReplicationRules::CLOUD,
                'table_name' => $event['table'] ?? '?', 'row_uuid' => $event['row_uuid'] ?? $c['event_id'],
                'kind' => is_string($c['kind'] ?? null) ? mb_substr($c['kind'], 0, 24) : SyncConflict::KIND_UPDATE_UPDATE, 'policy' => null,
                'resolution' => match ($c['resolution'] ?? null) {
                    'vessel_won' => SyncConflict::KEPT_LOCAL,
                    'cloud_kept' => SyncConflict::TOOK_REMOTE,
                    'merged' => SyncConflict::MERGED,
                    default => SyncConflict::NEEDS_REVIEW,
                },
                'remote_version' => null, 'local_version' => $event['version'] ?? null,
                'local_values' => $event['values'] ?? null,
                'status' => ($c['resolution'] ?? null) === 'needs_review' || ($c['review'] ?? false) === true ? SyncConflict::STATUS_NEEDS_REVIEW : SyncConflict::STATUS_RESOLVED,
                'note' => trim((is_string($c['note'] ?? null) ? mb_substr($c['note'], 0, 1000).' ' : '').'Found by the cloud while applying batch '.$batch->batch_id.'.'),
                'created_at' => now()->format('Y-m-d H:i:s.u'),
            ]);
        }
    }

    /**
     * Files of the records, after the records (Checkpoint 7). A problem here
     * is recorded and reported (sync:health) but never fails the cycle: the
     * records are already in, and the files are tried again next time.
     */
    public function transferFiles(SyncClient $client, string $cloud, bool $askCloud = true): array
    {
        if (! config('sync.files.enabled', true)) {
            return ['skipped' => 'File sync is off (SYNC_FILES).'];
        }

        $state = (array) $this->state->get('files', []);
        $state['last_run_at'] = now()->getTimestamp();
        try {
            $result = app(\App\Sync\Files\FileTransfer::class)->run($client, $cloud, $askCloud);
            $state['last_ok_at'] = $state['last_run_at'];
            $state['last_error'] = $result['errors'][0] ?? null;
            $state['waiting'] = $result['waiting'];
            if ($result['uploaded'] + $result['downloaded'] + $result['failed'] > 0) {
                $this->logger->record('files', $result['failed'] > 0 ? 'warning' : 'ok', [
                    'node_id' => $cloud, 'bytes' => $result['bytes'],
                    'message' => "Files: {$result['uploaded']} sent, {$result['downloaded']} received, {$result['failed']} failed, {$result['waiting']} waiting.",
                    'context' => $result['errors'] === [] ? null : json_encode(['errors' => $result['errors']]),
                ]);
            }
        } catch (\Throwable $e) {
            $state['last_error'] = mb_substr(class_basename($e).': '.$e->getMessage(), 0, 1000);
            $result = ['error' => $state['last_error']];
            $this->logger->record('files', 'warning', ['node_id' => $cloud, 'message' => 'Files not transferred: '.$state['last_error']]);
        }
        $this->state->put('files', $state);

        return $result;
    }

    public function side(string $cloud, array $cloudSends): VesselSide
    {
        return new VesselSide($cloud, (int) $this->mode->vesselId(), $cloudSends, $this->rules, $this->cursors, $this->scope);
    }

    private function client(): SyncClient
    {
        return $this->clientFactory ? ($this->clientFactory)() : SyncClient::fromConfig();
    }
}
