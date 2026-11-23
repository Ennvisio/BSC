<?php

namespace App\Console\Commands;

use App\Support\AppMode;
use App\Sync\Engine\OutboxSequencer;
use App\Sync\Engine\SyncServer;
use App\Sync\Models\SyncNode;
use App\Sync\SyncCursors;
use App\Sync\SyncLogger;
use App\Sync\SyncNodes;
use Illuminate\Console\Command;
use Throwable;

/**
 * Registers a server for sync on the cloud and prints its node id and token
 * once. Put both into that server's .env (SYNC_NODE_ID, SYNC_NODE_TOKEN).
 */
class SyncRegisterNode extends Command
{
    protected $signature = 'sync:register-node
        {kind : vessel or cloud}
        {--vessel= : The vessel id (vessel nodes only)}
        {--name= : A label, such as the PC name}
        {--replace : Retire the vessel\'s current node first, e.g. after replacing its master PC}';

    protected $description = 'Register the cloud or a vessel server for sync (cloud only) and show its token once';

    public function handle(AppMode $mode, SyncNodes $nodes, SyncLogger $logger): int
    {
        if (! $mode->isCloud()) {
            $this->error('Nodes are registered on the cloud server only.');

            return self::FAILURE;
        }

        $kind = (string) $this->argument('kind');
        $vesselId = $this->option('vessel') !== null && ctype_digit((string) $this->option('vessel'))
            ? (int) $this->option('vessel')
            : null;

        if ($this->option('vessel') !== null && $vesselId === null) {
            $this->error('--vessel must be a vessel id.');

            return self::FAILURE;
        }

        $name = (string) ($this->option('name') ?: ($kind === SyncNode::KIND_CLOUD ? 'Cloud' : "Vessel {$vesselId}"));

        try {
            ['node' => $node, 'token' => $token] = $nodes->register($kind, $vesselId, $name, (bool) $this->option('replace'));
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $start = null;
        if ($kind === SyncNode::KIND_VESSEL && \Illuminate\Support\Facades\Schema::hasColumn('sync_outbox', 'seq')) {
            // A new vessel starts from the cloud as it is now: its database
            // is copied from the cloud after this point (the vessel data
            // bootstrap), so it needs only the changes made from now on.
            $sequencer = app(OutboxSequencer::class);
            $sequencer->sequence();
            $start = $sequencer->highWater();
            app(SyncCursors::class)->advance($node->node_id, SyncServer::STREAM_PULL, $start);
        }

        $logger->record('register-node', 'ok', ['node_id' => $node->node_id, 'message' => "Registered {$kind} node \"{$name}\"".($start === null ? '.' : ", starting at position {$start}.")]);

        $this->info("Registered {$kind} node \"{$name}\".");
        $this->line('Add these lines to that server\'s .env. The token is shown only now.');
        $this->newLine();
        $this->line("SYNC_NODE_ID={$node->node_id}");
        $this->line("SYNC_NODE_TOKEN={$token}");
        if ($start !== null) {
            $this->newLine();
            $this->line("The vessel will receive cloud changes made from now on (position {$start}). Copy the cloud database for this vessel only after this point.");
        }

        return self::SUCCESS;
    }
}
