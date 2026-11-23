<?php

namespace App\Console\Commands;

use App\Support\AppMode;
use App\Sync\SyncLogger;
use App\Sync\SyncNodes;
use Illuminate\Console\Command;

/** Revokes a node's access, for example when a master PC is lost or replaced. */
class SyncRevokeNode extends Command
{
    protected $signature = 'sync:revoke-node {node : The node id}';

    protected $description = 'Revoke a sync node\'s access (cloud only)';

    public function handle(AppMode $mode, SyncNodes $nodes, SyncLogger $logger): int
    {
        if (! $mode->isCloud()) {
            $this->error('Nodes are managed on the cloud server only.');

            return self::FAILURE;
        }

        $nodeId = (string) $this->argument('node');

        if (! $nodes->revoke($nodeId)) {
            $this->error("No active node with id {$nodeId}.");

            return self::FAILURE;
        }

        $logger->record('revoke-node', 'warning', ['node_id' => strtolower($nodeId), 'message' => 'Node revoked.']);
        $this->info("Node {$nodeId} is revoked. Its token no longer works.");

        return self::SUCCESS;
    }
}
