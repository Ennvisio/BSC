<?php

namespace App\Sync;

use App\Sync\Models\SyncNode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Registration of the servers that take part in sync: the cloud and one
 * server per vessel. The registry lives on the cloud.
 *
 * Each node gets a UUID (its permanent identity, SYNC_NODE_ID in its .env)
 * and a secret token. Only a SHA-256 hash of the token is stored, so the
 * token is shown once, at registration, and cannot be recovered later.
 */
class SyncNodes
{
    /** This server's own node id, from SYNC_NODE_ID, or null when unset or invalid. */
    public function thisNodeId(): ?string
    {
        $id = trim((string) config('sync.node_id', ''));

        return SyncSql::isUuid($id) ? strtolower($id) : null;
    }

    /**
     * @return array{node: SyncNode, token: string}
     */
    public function register(string $kind, ?int $vesselId, string $name, bool $replace = false): array
    {
        if (! in_array($kind, [SyncNode::KIND_CLOUD, SyncNode::KIND_VESSEL], true)) {
            throw new RuntimeException('A node is either "cloud" or "vessel".');
        }
        if ($kind === SyncNode::KIND_VESSEL && ! $vesselId) {
            throw new RuntimeException('A vessel node needs a vessel id.');
        }
        if ($kind === SyncNode::KIND_CLOUD && $vesselId) {
            throw new RuntimeException('The cloud node does not belong to a vessel.');
        }
        if ($vesselId && ! DB::table('vessels')->where('id', $vesselId)->exists()) {
            throw new RuntimeException("No vessel with id {$vesselId}.");
        }

        return DB::transaction(function () use ($kind, $vesselId, $name, $replace) {
            $current = SyncNode::query()
                ->where('kind', $kind)
                ->where('status', SyncNode::STATUS_ACTIVE)
                ->when($kind === SyncNode::KIND_VESSEL, fn ($q) => $q->where('vessel_id', $vesselId))
                ->lockForUpdate()
                ->get();

            if ($current->isNotEmpty() && ! $replace) {
                throw new RuntimeException($kind === SyncNode::KIND_VESSEL
                    ? "Vessel {$vesselId} already has an active node ({$current->first()->node_id}). Use --replace to retire it, for example after replacing the master PC."
                    : "An active cloud node already exists ({$current->first()->node_id}).");
            }

            foreach ($current as $old) {
                $this->retire($old);
            }

            $token = Str::random(64);
            $node = SyncNode::create([
                'node_id' => (string) Str::uuid(),
                'kind' => $kind,
                'vessel_id' => $vesselId,
                'name' => $name,
                'token_hash' => hash('sha256', $token),
                'status' => SyncNode::STATUS_ACTIVE,
            ]);

            return ['node' => $node, 'token' => $token];
        });
    }

    /** The active node with this id and token, or null. Records that it was seen. */
    public function authenticate(string $nodeId, string $token): ?SyncNode
    {
        if (! SyncSql::isUuid($nodeId) || $token === '') {
            return null;
        }

        $node = SyncNode::query()->where('node_id', strtolower($nodeId))->first();

        if ($node === null || ! $node->isActive() || $node->token_hash === null
            || ! hash_equals($node->token_hash, hash('sha256', $token))) {
            return null;
        }

        $node->forceFill(['last_seen_at' => now()])->save();

        return $node;
    }

    public function revoke(string $nodeId): bool
    {
        $node = SyncNode::query()->where('node_id', strtolower($nodeId))->first();

        if ($node === null || ! $node->isActive()) {
            return false;
        }

        $this->retire($node);

        return true;
    }

    private function retire(SyncNode $node): void
    {
        $node->forceFill([
            'status' => SyncNode::STATUS_REVOKED,
            'revoked_at' => now(),
            'token_hash' => null,
        ])->save();
    }
}
