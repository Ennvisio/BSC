<?php

namespace App\Sync;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * A row that moves from one vessel to another (a crew member's role moved
 * to another ship) is routed to its new vessel only: the capture triggers
 * work out the vessel from the row as it is now. The old vessel would never
 * hear of the move and would keep letting that person sign in.
 *
 * So when that happens, the row (and the rows that depend on it, such as
 * the user of a role) is also queued once for the vessel it left. That
 * vessel accepts the change although the row no longer belongs to it
 * (rule 'departures', VesselSide::checkScope), and from then on refuses
 * the person at sign-in (Checkpoint 7).
 */
class VesselDepartures
{
    public function __construct(private SyncRegistry $registry)
    {
    }

    /** @param array<string, list<int|string>> $rows table => ids */
    public function record(int $leftVessel, array $rows): void
    {
        if (! SyncContext::captureEnabled() || SyncContext::origin() !== null || ! Schema::hasTable('sync_outbox')) {
            return; // not a change made here, or no sync on this server
        }

        foreach ($rows as $table => $ids) {
            if (! $this->registry->isSynced($table)) {
                continue;
            }
            $uuidColumn = $this->registry->uuidColumn($table);
            foreach (DB::table($table)->whereIn('id', $ids)->get(['id', $uuidColumn, 'sync_version']) as $row) {
                if ($row->{$uuidColumn} === null) {
                    continue;
                }
                DB::table('sync_outbox')->insert([
                    'event_id' => (string) Str::uuid(), 'table_name' => $table, 'row_id' => $row->id,
                    'row_uuid' => strtolower((string) $row->{$uuidColumn}), 'operation' => 'update',
                    'row_version' => $row->sync_version, 'vessel_id' => $leftVessel, 'origin_node' => null,
                    'created_at' => now()->format('Y-m-d H:i:s.u'),
                ]);
            }
        }
    }
}
