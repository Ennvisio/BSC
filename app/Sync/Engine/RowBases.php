<?php

namespace App\Sync\Engine;

use Illuminate\Support\Facades\DB;

/**
 * The values of a row that this server and one peer last agreed on: what
 * was last received from the peer, or what the peer confirmed receiving.
 *
 * A column-merged table (a requisition) uses it when both sides changed the
 * row before seeing each other's change: a column that still has the agreed
 * value on one side was only changed on the other, so that side's value is
 * taken without a conflict. Only a column changed on both sides is one.
 *
 * Values are kept as they travel (references as UUIDs), so they compare
 * directly with what a peer sends.
 */
class RowBases
{
    /** @return array<string, mixed>|null */
    public function get(string $peer, string $table, string $uuid): ?array
    {
        $json = DB::table('sync_row_bases')
            ->where('peer_node', strtolower($peer))->where('table_name', $table)->where('row_uuid', strtolower($uuid))
            ->value('base_values');

        return $json === null ? null : json_decode($json, true);
    }

    /** The peer and this server now hold these values. */
    public function remember(string $peer, string $table, string $uuid, array $values): void
    {
        DB::table('sync_row_bases')->updateOrInsert(
            ['peer_node' => strtolower($peer), 'table_name' => $table, 'row_uuid' => strtolower($uuid)],
            ['base_values' => json_encode($values, JSON_UNESCAPED_UNICODE), 'updated_at' => now()],
        );
    }

    /** Sent to the peer at stream position $seq; agreed once the peer confirms it (confirm()). */
    public function offer(string $peer, string $table, string $uuid, array $values, int $seq): void
    {
        DB::table('sync_row_bases')->updateOrInsert(
            ['peer_node' => strtolower($peer), 'table_name' => $table, 'row_uuid' => strtolower($uuid)],
            ['offered_values' => json_encode($values, JSON_UNESCAPED_UNICODE), 'offered_seq' => $seq, 'updated_at' => now()],
        );
    }

    /** The peer confirmed it has applied everything up to $seq. */
    public function confirm(string $peer, int $seq): void
    {
        DB::table('sync_row_bases')
            ->where('peer_node', strtolower($peer))
            ->whereNotNull('offered_seq')
            ->where('offered_seq', '<=', $seq)
            ->update(['base_values' => DB::raw('offered_values'), 'offered_values' => null, 'offered_seq' => null, 'updated_at' => now()]);
    }

    public function forget(string $table, string $uuid): void
    {
        DB::table('sync_row_bases')->where('table_name', $table)->where('row_uuid', strtolower($uuid))->delete();
    }
}
