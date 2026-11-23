<?php

namespace App\Sync\Engine;

use App\Support\AppMode;
use App\Sync\Models\SyncBatch;
use App\Sync\Models\SyncInboxEvent;
use App\Sync\Models\SyncNode;
use App\Sync\SyncCursors;
use App\Sync\SyncRegistry;
use App\Sync\SyncSql;
use App\Sync\SyncState;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Housekeeping: pruning, retrying held-back changes, re-sending a table. */
class SyncMaintenance
{
    private const CHUNK = 2000;

    public function __construct(
        private AppMode $mode,
        private SyncRegistry $registry,
        private ReplicationRules $rules,
        private SyncCursors $cursors,
        private SyncState $state,
        private InboxProcessor $processor,
        private OutboxSequencer $sequencer,
        private RowScope $scope,
        private SyncAgent $agent,
    ) {
    }

    /**
     * Deletes bookkeeping nobody needs any more. Outbox events go only when
     * every reader has passed them and they are older than the retention
     * period. Failed or waiting inbox events, and conflicts, are kept.
     *
     * @return array<string, int> rows deleted (or that would be, with $dryRun)
     */
    public function prune(bool $dryRun = false): array
    {
        $days = config('sync.retention');
        $done = [];

        $passed = $this->outboxPassedBy();
        $done['outbox'] = $this->deleteWhere('sync_outbox', fn ($q) => $q->whereNotNull('seq')->where('seq', '<=', $passed)
            ->where('created_at', '<', now()->subDays((int) $days['outbox_days'])), $dryRun);

        $done['inbox'] = $this->deleteWhere('sync_inbox', fn ($q) => $q->whereIn('status', [SyncInboxEvent::STATUS_APPLIED, SyncInboxEvent::STATUS_SKIPPED])
            ->where('received_at', '<', now()->subDays((int) $days['inbox_days'])), $dryRun);

        $done['batches'] = $this->deleteWhere('sync_batches', fn ($q) => $q->whereIn('status', [SyncBatch::STATUS_ACKED, SyncBatch::STATUS_APPLIED])
            ->where('updated_at', '<', now()->subDays((int) $days['batches_days'])), $dryRun);

        $done['logs'] = $this->deleteWhere('sync_logs', fn ($q) => $q->where('created_at', '<', now()->subDays((int) $days['logs_days'])), $dryRun);

        return $done;
    }

    /** Every outbox event up to this position has been passed by all its readers. */
    public function outboxPassedBy(): int
    {
        if ($this->mode->isVessel()) {
            $cloud = $this->state->get('cloud.node_id');

            return $cloud ? $this->cursors->position($cloud, SyncAgent::STREAM_PUSH) : 0;
        }

        $nodes = SyncNode::query()->where('kind', SyncNode::KIND_VESSEL)->where('status', SyncNode::STATUS_ACTIVE)->pluck('node_id');
        if ($nodes->isEmpty()) {
            return $this->sequencer->highWater();
        }

        return $nodes->map(fn ($n) => $this->cursors->position($n, SyncServer::STREAM_PULL))->min();
    }

    /**
     * Applies again the changes still waiting for a record they refer to,
     * and with $includeFailed also those that failed (for example after a
     * table was enabled, or a bug fixed).
     *
     * @return array<string, array{applied: int, deferred: int, failed: int}> per source node
     */
    public function retry(bool $includeFailed): array
    {
        $statuses = $includeFailed ? [SyncInboxEvent::STATUS_DEFERRED, SyncInboxEvent::STATUS_FAILED] : [SyncInboxEvent::STATUS_DEFERRED];
        $sources = SyncInboxEvent::query()->whereIn('status', $statuses)->distinct()->pluck('source_node');
        $results = [];

        foreach ($sources as $source) {
            $results[$source] = DB::transaction(fn () => $this->processor->retryDeferred($source, $this->sideFor($source), $includeFailed, 100000));
        }

        return $results;
    }

    /**
     * Records every current row of $table as changed, so the next sync sends
     * all of it. Needed when a table is enabled after changes were made to
     * it: those changes were captured while the table was not synced, and
     * the cursors have moved past them. Sending everything again is safe:
     * rows the other side already has are recognised as unchanged.
     */
    public function resend(string $table, ?int $vesselId = null): int
    {
        if (! $this->registry->isSynced($table)) {
            throw new RuntimeException("{$table} is not a synchronised table.");
        }

        $driver = DB::connection()->getDriverName();
        $uuid = $this->registry->uuidColumn($table);
        $vessel = str_replace('{row}', 't', $this->registry->definition($table)['vessel'] ?? 'NULL');
        $quote = fn ($name) => $driver === 'mysql' ? "`{$name}`" : "\"{$name}\"";

        $sql = 'INSERT INTO sync_outbox (event_id, table_name, row_id, row_uuid, operation, row_version, vessel_id, origin_node, created_at) '
            .'SELECT '.SyncSql::uuidExpression($driver).", ?, t.id, t.{$quote($uuid)}, 'update', t.sync_version, {$vessel}, NULL, ".SyncSql::nowExpression($driver)
            ." FROM {$quote($table)} t WHERE t.{$quote($uuid)} IS NOT NULL";
        $bindings = [$table];

        if ($vesselId !== null) {
            if (! $this->scope->isVesselScoped($table)) {
                throw new RuntimeException("{$table} is fleet-wide; --vessel does not apply.");
            }
            $sql .= " AND ({$vessel}) = ?";
            $bindings[] = $vesselId;
        }

        return DB::affectingStatement($sql, $bindings);
    }

    private function sideFor(string $source): ReceivingSide
    {
        if ($this->mode->isVessel()) {
            $tables = $this->state->get('cloud.last_handshake')['tables']['down'] ?? $this->rules->downTables();

            return $this->agent->side($source, $tables);
        }

        $node = SyncNode::query()->where('node_id', $source)->first()
            ?? throw new RuntimeException("Unknown source node {$source}.");

        return new CloudSide($node, $this->rules, $this->cursors, $this->scope);
    }

    private function deleteWhere(string $table, callable $where, bool $dryRun): int
    {
        if ($dryRun) {
            return DB::table($table)->tap($where)->count();
        }

        $total = 0;
        do {
            $key = $table === 'sync_state' ? 'key' : 'id';
            $ids = DB::table($table)->tap($where)->orderBy($key)->limit(self::CHUNK)->pluck($key)->all();
            if ($ids !== []) {
                $total += DB::table($table)->whereIn($key, $ids)->delete();
            }
        } while (count($ids) === self::CHUNK);

        return $total;
    }
}
