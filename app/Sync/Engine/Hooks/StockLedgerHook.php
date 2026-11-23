<?php

namespace App\Sync\Engine\Hooks;

use App\Sync\Engine\ApplyHook;
use App\Sync\Engine\MissingReference;
use App\Sync\Engine\ReceivingSide;
use App\Sync\Engine\ReplicationRules;
use App\Sync\Models\SyncConflict;
use App\Sync\SyncContext;
use Illuminate\Support\Facades\DB;

/**
 * Inventory on the cloud follows the vessel's stock ledger.
 *
 * On board, every stock change goes through App\Services\StockService,
 * which writes the balance (vessel_items.stock_qty) and a stock_movements
 * line in one transaction. Only the movement lines travel: the balance is
 * never copied over (it is a local column). When the cloud receives a new
 * movement, it moves its own balance for that vessel and item:
 *
 *  - receipt, consumption, adjustment: the balance here plus the movement's
 *    signed quantity must equal the movement's balance_after. It always
 *    does while both sides started from the same figure.
 *  - opening: a declaration of the opening balance, which does not move the
 *    stock on hand (StockService::setStock records it with the unchanged
 *    balance), so there is nothing to add.
 *
 * If the sum does not agree, the books differ between ship and office (for
 * example stock changed on the cloud copy before the vessel went offline).
 * That is recorded as a stock_mismatch conflict for a person. What the
 * cloud's balance becomes meanwhile is config('sync.inventory.on_mismatch'):
 * 'adopt' (default) takes the vessel's figure, since the ship counts what
 * is physically on board; 'keep' leaves the office figure as it was.
 *
 * A movement applied late (it waited for a record it refers to) never moves
 * the balance back: only the newest movement sets it.
 */
class StockLedgerHook implements ApplyHook
{
    public function prepare(string $table, array $values, ?object $local, ReceivingSide $side): array
    {
        return $values;
    }

    public function applied(string $table, array $event, string $source, ?object $before, ?object $after, ReceivingSide $side): void
    {
        if ($table !== 'stock_movements' || $before !== null || $after === null || $side->name() !== ReplicationRules::CLOUD) {
            return;
        }

        $link = DB::table('vessel_items')->where('vessel_id', $after->vessel_id)->where('item_id', $after->item_id)->first();
        if ($link === null) {
            // The vessel's catalog link comes first in the same transaction
            // on board; wait for it.
            throw new MissingReference('vessel_items', 'vessel_id, item_id', "{$after->vessel_id}/{$after->item_id}");
        }

        $newest = DB::table('stock_movements')
            ->where('vessel_id', $after->vessel_id)->where('item_id', $after->item_id)
            ->orderByDesc('created_at')->orderByDesc('id')
            ->first();
        if ($newest === null || (int) $newest->id !== (int) $after->id) {
            return; // an older line arriving late
        }

        $here = (int) $link->stock_qty;
        $theirs = (int) $after->balance_after;
        $expected = $after->type === 'opening' ? $here : $here + (int) $after->qty;

        if ($expected !== $theirs) {
            $adopt = config('sync.inventory.on_mismatch', 'adopt') !== 'keep';
            SyncConflict::create([
                'event_id' => $event['event_id'],
                'source_node' => $source,
                'detected_by' => ReplicationRules::CLOUD,
                'table_name' => 'vessel_items',
                'row_uuid' => strtolower((string) $link->sync_uuid),
                'kind' => SyncConflict::KIND_STOCK_MISMATCH,
                'policy' => null,
                'resolution' => $adopt ? SyncConflict::TOOK_REMOTE : SyncConflict::KEPT_LOCAL,
                'local_values' => ['stock_qty' => $here],
                'remote_values' => ['type' => $after->type, 'qty' => (int) $after->qty, 'balance_after' => $theirs, 'movement_uuid' => $after->sync_uuid],
                'status' => SyncConflict::STATUS_NEEDS_REVIEW,
                'note' => "Stock of item {$after->item_id} on vessel {$after->vessel_id}: the office had {$here}, the vessel's {$after->type} of {$after->qty} should give {$expected}, but the vessel's balance is {$theirs}. "
                    .($adopt ? "The office balance is now the vessel's {$theirs}." : "The office balance stays {$here}."),
                'created_at' => now()->format('Y-m-d H:i:s.u'),
            ]);
            if (! $adopt) {
                return;
            }
        }

        if ($theirs !== $here) {
            SyncContext::asOrigin($source, fn () => DB::table('vessel_items')->where('id', $link->id)->update(['stock_qty' => $theirs]));
        }
    }
}
