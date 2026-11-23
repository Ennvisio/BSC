<?php

namespace App\Console\Commands;

use App\Support\AppMode;
use App\Sync\Engine\OutboxSequencer;
use App\Sync\Engine\ReplicationRules;
use App\Sync\Engine\SyncAgent;
use App\Sync\Engine\SyncServer;
use App\Sync\Engine\SyncWorker;
use App\Sync\Models\SyncBatch;
use App\Sync\Models\SyncConflict;
use App\Sync\Models\SyncCursor;
use App\Sync\Models\SyncInboxEvent;
use App\Sync\Models\SyncNode;
use App\Sync\Models\SyncOutboxEvent;
use App\Sync\SyncCursors;
use App\Sync\SyncNodes;
use App\Sync\SyncRegistry;
use App\Sync\SyncState;
use App\Sync\TriggerManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/** Read-only overview of this server's sync readiness. */
class SyncStatus extends Command
{
    protected $signature = 'sync:status';

    protected $description = 'Show sync identity, change capture, replication, worker, backlog, conflicts and registered nodes';

    public function handle(AppMode $mode, SyncNodes $nodes, SyncRegistry $registry, TriggerManager $triggers): int
    {
        $errors = [];

        foreach ($registry->definitionErrors() as $error) {
            $errors[] = 'config/sync.php: '.$error;
        }

        if (! Schema::hasTable('sync_outbox')) {
            $this->error('The sync tables do not exist yet. Run "php artisan migrate".');

            return self::FAILURE;
        }

        $nodeId = $nodes->thisNodeId();
        if ($nodeId === null && $mode->isVessel()) {
            $errors[] = 'SYNC_NODE_ID is missing or not a UUID. Register this vessel on the cloud with "php artisan sync:register-node vessel --vessel=ID".';
        }

        $status = $triggers->status();
        $captured = count(array_filter($status, fn ($s) => $s['installed'] === $s['expected']));
        $missingColumns = $triggers->tablesMissingColumns();

        $this->table(['Item', 'Value'], [
            ['Mode', $mode->mode()],
            ['This node', $nodeId ?? '-'],
            ['Synchronised tables present', count($status).' of '.count($registry->tableNames())],
            ['Tables without sync columns', count($missingColumns)],
            ['Tables with change capture', "{$captured} of ".count($status)],
            ['Tables that cannot be captured', count($triggers->tablesWithMissingReferences())],
            ['Outbox events', SyncOutboxEvent::query()->count()],
            ['Inbox events pending', SyncInboxEvent::query()->where('status', SyncInboxEvent::STATUS_PENDING)->count()],
            ['Inbox events failed', SyncInboxEvent::query()->where('status', SyncInboxEvent::STATUS_FAILED)->count()],
            ['Cursors', SyncCursor::query()->count()],
            ['Active nodes', SyncNode::query()->where('status', SyncNode::STATUS_ACTIVE)->count()],
        ]);

        if ($triggers->tablesWithMissingReferences() !== []) {
            $errors[] = 'Some tables cannot be captured because a table they depend on is missing: '.implode(', ', $triggers->tablesWithMissingReferences()).'.';
        }

        if ($missingColumns !== []) {
            $errors[] = 'Some tables have no sync columns. Run "php artisan migrate".';
        } elseif ($captured < count($status)) {
            $this->warn('Change capture is not installed on every table. Run "php artisan sync:triggers install".');
        }

        if (Schema::hasTable('sync_batches') && Schema::hasTable('sync_conflicts') && Schema::hasTable('sync_state')) {
            $errors = [...$errors, ...$this->engine($mode)];
        }

        foreach ($errors as $error) {
            $this->error($error);
        }

        return $errors === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Checkpoint 5: replication rules, the stream, and per side either the
     * worker (vessel) or how far behind each vessel is (cloud).
     *
     * @return list<string> errors
     */
    private function engine(AppMode $mode): array
    {
        $errors = [];
        $rules = app(ReplicationRules::class);
        $sequencer = app(OutboxSequencer::class);
        $cursors = app(SyncCursors::class);
        $highWater = $sequencer->highWater();

        foreach ($rules->definitionErrors() as $error) {
            $errors[] = 'Replication: '.$error;
        }
        foreach ($rules->warnings() as $warning) {
            $this->warn('Replication: '.$warning);
        }

        $this->newLine();
        $this->line('<info>Replication</info>');
        $this->table(['Item', 'Value'], [
            ['Enabled tables', implode(', ', $rules->enabled()) ?: '-'],
            ['Sent down (cloud to vessel)', implode(', ', $rules->downTables()) ?: '-'],
            ['Sent up (vessel to cloud)', implode(', ', $rules->upTables()) ?: '-'],
            ['Stream position (last numbered event)', $highWater],
            ['Outbox events not yet numbered', SyncOutboxEvent::query()->whereNull('seq')->count()],
            ['Received changes waiting for a record', SyncInboxEvent::query()->where('status', SyncInboxEvent::STATUS_DEFERRED)->count()],
            ['Conflicts needing review', SyncConflict::query()->where('status', SyncConflict::STATUS_NEEDS_REVIEW)->count()],
        ]);

        if ($mode->isVessel()) {
            return [...$errors, ...$this->vessel($cursors)];
        }

        $nodes = SyncNode::query()->where('kind', SyncNode::KIND_VESSEL)->where('status', SyncNode::STATUS_ACTIVE)->orderBy('vessel_id')->get();
        if ($nodes->isNotEmpty()) {
            $this->newLine();
            $this->line('<info>Vessels</info>');
            $this->table(['Vessel', 'Node', 'Name', 'Last seen', 'Pulled to', 'Behind', 'Pushed to'], $nodes->map(function (SyncNode $n) use ($cursors, $highWater) {
                $pulled = $cursors->position($n->node_id, SyncServer::STREAM_PULL);

                return [
                    $n->vessel_id, substr($n->node_id, 0, 8), $n->name, $n->last_seen_at ? (string) $n->last_seen_at : 'never',
                    $pulled, max(0, $highWater - $pulled), $cursors->position($n->node_id, SyncServer::STREAM_PUSH),
                ];
            })->all());
        }

        return $errors;
    }

    /** @return list<string> errors */
    private function vessel(SyncCursors $cursors): array
    {
        $configured = filled(config('sync.cloud_url'));
        $problems = app(SyncAgent::class)->configurationProblems();
        $worker = app(SyncWorker::class)->status();
        $cloud = app(SyncState::class)->get('cloud.node_id');
        $pushed = $cloud ? $cursors->position($cloud, SyncAgent::STREAM_PUSH) : 0;
        $when = fn (?int $t) => $t ? date('Y-m-d H:i:s', $t) : '-';

        $this->newLine();
        $this->line('<info>Sync with the cloud</info>');
        $this->table(['Item', 'Value'], [
            ['Cloud address', config('sync.cloud_url') ?: 'not set (sync off)'],
            ['Cloud node', $cloud ?? 'not contacted yet'],
            ['Last successful sync', $when($worker['last_success_at'])],
            ['Failures in a row', $worker['failures']],
            ['Last error', $worker['last_error'] ?? '-'],
            ['Next attempt', $worker['run_now'] ? 'as soon as possible (requested)' : $when($worker['next_attempt_at'])],
            ['Local changes waiting to be sent', SyncOutboxEvent::query()->whereNull('origin_node')
                ->where(fn ($q) => $q->whereNull('seq')->orWhere('seq', '>', $pushed))->count()],
            ['Batch sent, not yet confirmed', SyncBatch::query()->where('direction', SyncBatch::OUT)->where('status', SyncBatch::STATUS_PENDING)->value('batch_id') ?? '-'],
            ['Received up to (cloud position)', $cloud ? $cursors->position($cloud, SyncAgent::STREAM_PULL) : 0],
            ['Received changes that failed', SyncInboxEvent::query()->where('status', SyncInboxEvent::STATUS_FAILED)->count()],
        ]);

        return $configured ? array_map(fn ($p) => 'Sync: '.$p, $problems) : [];
    }
}
