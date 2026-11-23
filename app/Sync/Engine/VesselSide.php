<?php

namespace App\Sync\Engine;

use App\Sync\SyncCursors;
use Illuminate\Support\Facades\DB;

/** A vessel server receiving changes pulled from the cloud. */
class VesselSide implements ReceivingSide
{
    /** @param list<string> $cloudSends tables the cloud said it sends, from the handshake */
    public function __construct(
        private string $cloudNode,
        private int $vesselId,
        private array $cloudSends,
        private ReplicationRules $rules,
        private SyncCursors $cursors,
        private RowScope $scope,
    ) {
    }

    public function name(): string
    {
        return ReplicationRules::VESSEL;
    }

    public function accepts(string $table): bool
    {
        $rule = $this->rules->rule($table);

        return $rule !== null && $rule['direction'] !== ReplicationRules::UP && in_array($table, $this->cloudSends, true);
    }

    /**
     * A change made on board that the cloud has not acknowledged yet. Only
     * tables the vessel sends up can have one: local changes to cloud-owned
     * tables are never sent, so the cloud's version simply replaces them.
     */
    public function pendingLocalChange(string $table, string $rowUuid): ?object
    {
        if (! in_array($table, $this->rules->upTables(), true)) {
            return null;
        }

        $acked = $this->cursors->position($this->cloudNode, SyncAgent::STREAM_PUSH);

        return DB::table('sync_outbox')
            ->where('table_name', $table)
            ->where('row_uuid', $rowUuid)
            ->whereNull('origin_node')
            ->where(fn ($q) => $q->whereNull('seq')->orWhere('seq', '>', $acked))
            ->orderByDesc('id')
            ->first();
    }

    /** The cloud only sends this vessel's rows and fleet-wide ones; check anyway. */
    public function checkScope(string $table, array $event, int|string|null $idBefore, int|string|null $idAfter): void
    {
        if ($event['vessel_id'] !== null && $event['vessel_id'] !== $this->vesselId) {
            throw new InvalidChange("Change for vessel {$event['vessel_id']} sent to vessel {$this->vesselId}.");
        }

        if ($idAfter !== null && $this->scope->isVesselScoped($table)) {
            $owner = $this->scope->vesselOf($table, $idAfter);
            $departure = $this->rules->acceptsDepartures($table) && $event['vessel_id'] === $this->vesselId;
            if ($owner !== null && $owner !== $this->vesselId && ! $departure) {
                throw new InvalidChange("This {$table} row would belong to vessel {$owner}.");
            }
        }
    }
}
