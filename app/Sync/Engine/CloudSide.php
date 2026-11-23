<?php

namespace App\Sync\Engine;

use App\Sync\Models\SyncNode;
use App\Sync\SyncCursors;
use Illuminate\Support\Facades\DB;

/** The cloud receiving changes pushed by one vessel. */
class CloudSide implements ReceivingSide
{
    public function __construct(
        private SyncNode $vessel,
        private ReplicationRules $rules,
        private SyncCursors $cursors,
        private RowScope $scope,
    ) {
    }

    public function name(): string
    {
        return ReplicationRules::CLOUD;
    }

    public function accepts(string $table): bool
    {
        return in_array($table, $this->rules->upTables(), true);
    }

    /**
     * A cloud-side change (made here, or arrived from another vessel) that
     * this vessel has not pulled yet. Only tables the cloud sends down can
     * have one: changes to vessel-owned tables never go to the vessel.
     */
    public function pendingLocalChange(string $table, string $rowUuid): ?object
    {
        if (! in_array($table, $this->rules->downTables(), true)) {
            return null;
        }

        $node = $this->vessel->node_id;
        $delivered = $this->cursors->position($node, SyncServer::STREAM_PULL);

        return DB::table('sync_outbox')
            ->where('table_name', $table)
            ->where('row_uuid', $rowUuid)
            ->where(fn ($q) => $q->whereNull('origin_node')->orWhere('origin_node', '<>', $node))
            ->where(fn ($q) => $q->whereNull('vessel_id')->orWhere('vessel_id', $this->vessel->vessel_id))
            ->where(fn ($q) => $q->whereNull('seq')->orWhere('seq', '>', $delivered))
            ->orderByDesc('id')
            ->first();
    }

    /** A vessel may only touch its own vessel's rows, before and after the change. */
    public function checkScope(string $table, array $event, int|string|null $idBefore, int|string|null $idAfter): void
    {
        if (! $this->scope->isVesselScoped($table)) {
            return; // fleet-wide list the vessels may add to (accepts() checked the table)
        }

        foreach (array_filter([$idBefore, $idAfter], fn ($id) => $id !== null) as $id) {
            $owner = $this->scope->vesselOf($table, $id);
            if ($owner !== (int) $this->vessel->vessel_id) {
                throw new InvalidChange("This {$table} row belongs to ".($owner === null ? 'no vessel' : "vessel {$owner}").", not to vessel {$this->vessel->vessel_id}.");
            }
        }
    }
}
