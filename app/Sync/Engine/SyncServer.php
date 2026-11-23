<?php

namespace App\Sync\Engine;

use App\Sync\Models\SyncBatch;
use App\Sync\Models\SyncConflict;
use App\Sync\Models\SyncNode;
use App\Sync\SyncCursors;
use App\Sync\SyncLogger;
use App\Sync\SyncNodes;
use App\Sync\SyncSql;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * The cloud's half of the protocol: what the /api/sync/v1 endpoints do.
 * See docs/checkpoint-5-sync-engine.md.
 *
 * Cursors kept here, per vessel node:
 *  - pull: how far the vessel has applied the cloud's stream. Moves when the
 *          vessel asks for the next page or acknowledges, never backwards.
 *  - push: the end of the last batch applied from the vessel (information).
 */
class SyncServer
{
    public const PROTOCOL = 1;

    public const STREAM_PULL = 'pull';

    public const STREAM_PUSH = 'push';

    public function __construct(
        private SyncNodes $nodes,
        private SyncCursors $cursors,
        private OutboxSequencer $sequencer,
        private ChangePackager $packager,
        private InboxProcessor $processor,
        private ReplicationRules $rules,
        private RowScope $scope,
        private SyncLogger $logger,
        private RowBases $bases,
    ) {
    }

    public function cloudNodeId(): string
    {
        return $this->nodes->thisNodeId()
            ?? throw new SyncProtocolException('This cloud server has no SYNC_NODE_ID yet.');
    }

    public function handshake(SyncNode $node, array $body): array
    {
        $node->forceFill([
            'app_version' => is_string($body['app_release'] ?? null) ? mb_substr($body['app_release'], 0, 32) : $node->app_version,
            'schema_version' => is_string($body['schema'] ?? null) ? mb_substr($body['schema'], 0, 64) : $node->schema_version,
        ])->save();

        $cloudSchema = DB::table('migrations')->max('migration');

        $this->logger->record('handshake', 'ok', [
            'node_id' => $node->node_id,
            'message' => "Vessel {$node->vessel_id}, release ".($node->app_version ?? '?'),
        ]);

        return [
            'protocol' => self::PROTOCOL,
            'cloud_node_id' => $this->cloudNodeId(),
            'vessel_id' => (int) $node->vessel_id,
            'server_time' => now()->utc()->format('Y-m-d\TH:i:s.uP'),
            'tables' => ['down' => $this->rules->downTables(), 'up' => $this->rules->upTables()],
            'cursors' => [
                'pull' => $this->cursors->position($node->node_id, self::STREAM_PULL),
                'push' => $this->cursors->position($node->node_id, self::STREAM_PUSH),
            ],
            'schema' => $cloudSchema,
            'schema_matches' => ($body['schema'] ?? null) === $cloudSchema,
            'limits' => ['max_events' => (int) config('sync.batch.max_events'), 'max_bytes' => (int) config('sync.batch.max_bytes')],
            // Checkpoint 7: files the cloud waits for from this vessel, so an
            // idle vessel need not ask.
            'files_wanted' => \Illuminate\Support\Facades\Schema::hasTable('sync_files')
                ? DB::table('sync_files')->where('state', 'wanted')->where('peer_node', strtolower($node->node_id))->count() : 0,
        ];
    }

    /** Applies one batch from a vessel, or repeats the answer to a batch already applied. */
    public function push(SyncNode $node, array $body, int $bytes, string $sha256): array
    {
        $started = microtime(true);
        $batchId = $body['batch_id'] ?? null;
        $events = $body['events'] ?? null;
        $from = $body['seq_from'] ?? null;
        $to = $body['seq_to'] ?? null;

        if (! SyncSql::isUuid($batchId) || ! is_int($from) || ! is_int($to) || $from < 1 || $to < $from || ! is_array($events) || ! array_is_list($events)) {
            throw new InvalidChange('A batch needs batch_id, seq_from, seq_to and a list of events.');
        }
        if (count($events) > (int) config('sync.batch.max_events')) {
            throw new InvalidChange('Too many events in one batch.');
        }
        $batchId = strtolower($batchId);

        if ($repeat = $this->storedAnswer($node, $batchId)) {
            return $repeat;
        }

        $side = new CloudSide($node, $this->rules, $this->cursors, $this->scope);

        try {
            $answer = DB::transaction(function () use ($node, $batchId, $events, $from, $to, $bytes, $sha256, $side) {
                // Inserted first: a second copy of this batch arriving at the
                // same moment waits here, then finds the answer stored.
                $batch = SyncBatch::create([
                    'batch_id' => $batchId, 'direction' => SyncBatch::IN, 'node_id' => $node->node_id,
                    'seq_from' => $from, 'seq_to' => $to, 'events' => count($events), 'bytes' => $bytes,
                    'sha256' => $sha256, 'status' => SyncBatch::STATUS_APPLIED, 'attempts' => 1,
                ]);

                $results = $this->processor->process($node->node_id, $events, $side, $batchId);
                $retried = $this->processor->retryDeferred($node->node_id, $side);

                $answer = $this->answer($batchId, $to, $results, $retried);
                $batch->forceFill(['response' => json_encode($answer, JSON_UNESCAPED_UNICODE)])->save();
                $this->cursors->advance($node->node_id, self::STREAM_PUSH, $to);

                return $answer;
            });
        } catch (UniqueConstraintViolationException $e) {
            if ($repeat = $this->storedAnswer($node, $batchId)) {
                return $repeat;
            }
            throw $e;
        }

        $this->logger->record('push', $answer['counts']['rejected'] > 0 ? 'warning' : 'ok', [
            'node_id' => $node->node_id,
            'events' => count($events),
            'bytes' => $bytes,
            'duration_ms' => (int) ((microtime(true) - $started) * 1000),
            'message' => $this->summary($answer['counts']),
            'context' => json_encode(['batch_id' => $batchId, 'rejected' => $answer['rejected']]),
        ]);

        return $answer;
    }

    /** The next page of cloud changes for this vessel. Asking for it acknowledges everything up to $after. */
    public function pull(SyncNode $node, int $after, int $limit): array
    {
        $started = microtime(true);
        $this->sequencer->sequence();
        $highWater = $this->sequencer->highWater();

        if ($after > $highWater) {
            throw new SyncProtocolException("The vessel is at position {$after}, but the cloud's stream ends at {$highWater}. Was the cloud database restored from a backup?");
        }

        $this->cursors->advance($node->node_id, self::STREAM_PULL, $after);
        $this->bases->confirm($node->node_id, $this->cursors->position($node->node_id, self::STREAM_PULL));

        $tables = $this->rules->downTables();
        $vesselId = (int) $node->vessel_id;
        $page = $this->packager->page($after, $highWater, function ($q) use ($tables, $vesselId, $node) {
            $q->whereIn('table_name', $tables === [] ? ['-'] : $tables)
                ->where(fn ($w) => $w->whereNull('vessel_id')->orWhere('vessel_id', $vesselId))
                ->where(fn ($w) => $w->whereNull('origin_node')->orWhere('origin_node', '<>', $node->node_id));
        }, max(1, min($limit, (int) config('sync.batch.max_events'))), (int) config('sync.batch.max_bytes'), $vesselId);

        // What the vessel will hold once it confirms this page, for merging
        // later changes to business records (RowBases).
        foreach ($page['events'] as $event) {
            if ($event['values'] !== null && $this->rules->mergesColumns($event['table'])) {
                $this->bases->offer($node->node_id, $event['table'], $event['row_uuid'], $event['values'], $page['cursor']);
            }
        }

        if ($page['events'] !== [] || $page['dangling'] !== []) {
            $this->logger->record('pull', $page['dangling'] === [] ? 'ok' : 'warning', [
                'node_id' => $node->node_id,
                'events' => count($page['events']),
                'bytes' => $page['bytes'],
                'duration_ms' => (int) ((microtime(true) - $started) * 1000),
                'message' => "Sent {$after} to {$page['cursor']} of {$highWater}.",
                'context' => $page['dangling'] === [] ? null : json_encode(['dangling_references' => $page['dangling']]),
            ]);
        }

        return [
            'protocol' => self::PROTOCOL,
            'after' => $after,
            'cursor' => $page['cursor'],
            'has_more' => $page['has_more'],
            'high_water' => $highWater,
            'events' => $page['events'],
        ];
    }

    public function acknowledge(SyncNode $node, int $cursor): array
    {
        $highWater = $this->sequencer->highWater();
        if ($cursor > $highWater) {
            throw new SyncProtocolException("Acknowledged position {$cursor} is beyond the end of the stream ({$highWater}).");
        }

        $this->cursors->advance($node->node_id, self::STREAM_PULL, $cursor);
        $this->bases->confirm($node->node_id, $this->cursors->position($node->node_id, self::STREAM_PULL));

        return ['protocol' => self::PROTOCOL, 'cursor' => $this->cursors->position($node->node_id, self::STREAM_PULL)];
    }

    private function storedAnswer(SyncNode $node, string $batchId): ?array
    {
        $batch = SyncBatch::query()->where('batch_id', $batchId)->where('direction', SyncBatch::IN)->first();
        if ($batch === null) {
            return null;
        }
        if ($batch->node_id !== $node->node_id) {
            throw new SyncProtocolException('This batch id was already used by another node.');
        }

        $this->logger->record('push', 'ok', ['node_id' => $node->node_id, 'message' => "Batch {$batchId} received again; answered as before."]);

        return ['repeated' => true] + json_decode($batch->response, true);
    }

    private function answer(string $batchId, int $cursor, array $results, array $retried): array
    {
        $counts = array_fill_keys([
            ChangeApplier::APPLIED, ChangeApplier::UNCHANGED, ChangeApplier::TOOK_REMOTE, ChangeApplier::KEPT_LOCAL,
            ChangeApplier::MERGED, ChangeApplier::SKIPPED, InboxProcessor::DUPLICATE, InboxProcessor::DEFERRED, InboxProcessor::REJECTED,
        ], 0);
        $rejected = [];
        $conflicts = [];

        foreach ($results as $r) {
            $counts[$r['status']] = ($counts[$r['status']] ?? 0) + 1;
            if ($r['status'] === InboxProcessor::REJECTED) {
                $rejected[] = ['event_id' => $r['event_id'], 'reason' => $r['note']];
            }
            if ($r['conflict'] !== null) {
                $record = SyncConflict::query()->find($r['conflict']);
                $conflicts[] = [
                    'event_id' => $r['event_id'],
                    'resolution' => match (true) {
                        $r['status'] === InboxProcessor::REJECTED => 'needs_review',
                        $record?->resolution === SyncConflict::MERGED => 'merged',
                        $r['status'] === ChangeApplier::KEPT_LOCAL => 'cloud_kept',
                        default => 'vessel_won',
                    },
                    'review' => $record?->status === SyncConflict::STATUS_NEEDS_REVIEW,
                    'kind' => $record?->kind,
                    'note' => $record?->note,
                    'conflict_id' => $r['conflict'],
                ];
            }
        }

        return [
            'protocol' => self::PROTOCOL,
            'batch_id' => $batchId,
            'status' => 'applied',
            'cursor' => $cursor,
            'counts' => $counts,
            'rejected' => $rejected,
            'conflicts' => $conflicts,
            'retried' => $retried,
        ];
    }

    private function summary(array $counts): string
    {
        return implode(', ', array_map(fn ($k, $v) => "{$k} {$v}", array_keys(array_filter($counts)), array_filter($counts))) ?: 'empty';
    }
}
