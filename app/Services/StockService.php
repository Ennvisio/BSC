<?php

namespace App\Services;

use App\VesselItem;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * The single funnel every stock write goes through.
 *
 * Stock currently lives as a plain balance on vessel_items. Keeping every
 * write behind this service means a full stock_movements ledger ("who changed
 * it, when, why, against which requisition") can be added later by changing
 * only this class - no caller needs to know.
 *
 * Stock is per-vessel ROB (remaining on board) and sparse: a vessel_items row
 * existing means the vessel can order that item, not that it holds any. Rows
 * are only ever touched for items that actually have a figure.
 */
class StockService
{
    /**
     * The hand-maintained figures, each independent of the others: whoever is
     * writing states only the ones they mean to change.
     */
    private const FIGURES = ['stock_qty', 'opening_stock', 'min_qty'];

    /**
     * Set absolute figures for one vessel item by hand - the Master declaring
     * an opening balance, correcting what's physically on board, or setting a
     * reorder threshold. Absolute, not deltas.
     *
     * $values may carry any of 'stock_qty', 'opening_stock' and 'min_qty'.
     * A key that isn't there is left exactly as it was - the same rule the
     * spreadsheet upload follows for a blank cell (see bulkSet), so both write
     * paths behave identically.
     *
     * @param  array{stock_qty?:int,opening_stock?:int,min_qty?:int}  $values
     */
    public function setStock(int $vesselId, int $itemId, array $values, ?int $userId = null): VesselItem
    {
        $row = $this->rowFor($vesselId, $itemId);

        foreach (self::FIGURES as $column) {
            if (array_key_exists($column, $values) && $values[$column] !== null) {
                $row->{$column} = max(0, (int) $values[$column]);
            }
        }

        $row->stock_updated_at = now();
        $row->stock_updated_by = $userId;
        $row->save();

        return $row;
    }

    /**
     * A confirmed receipt adds to what's on board, and is what makes stock
     * self-maintaining rather than drifting from reality between manual
     * corrections. Also stamps the paper form's "last supply" figures.
     */
    public function addFromReceipt(int $vesselId, int $itemId, int $qty, ?int $userId = null, ?CarbonInterface $receivedOn = null): ?VesselItem
    {
        if ($qty <= 0) {
            return null;
        }

        $row = $this->rowFor($vesselId, $itemId);

        // An opening balance that was never loaded is unknown, not zero -
        // don't invent one retroactively from the first receipt.
        $row->stock_qty = max(0, (int) $row->stock_qty + $qty);
        $row->last_supply_qty = $qty;
        $row->last_supply_date = ($receivedOn ?? now())->toDateString();
        $row->stock_updated_at = now();
        $row->stock_updated_by = $userId;
        $row->save();

        return $row;
    }

    /**
     * Current stock figures for a set of items on one vessel, keyed by item id
     * - used both to display ROB in the picker and to snapshot onto a
     * requisition's lines when it's raised.
     *
     * @param  array<int>  $itemIds
     * @return array<int,array{stock_qty:int,opening_stock:?int,last_supply_qty:?int,last_supply_date:?string,min_qty:?int}>
     */
    public function snapshotFor(int $vesselId, array $itemIds): array
    {
        if (empty($itemIds)) {
            return [];
        }

        return VesselItem::where('vessel_id', $vesselId)
            ->whereIn('item_id', $itemIds)
            ->get(['item_id', 'stock_qty', 'opening_stock', 'last_supply_qty', 'last_supply_date', 'min_qty'])
            ->keyBy('item_id')
            ->map(fn ($row) => [
                'stock_qty' => (int) $row->stock_qty,
                'opening_stock' => $row->opening_stock === null ? null : (int) $row->opening_stock,
                'last_supply_qty' => $row->last_supply_qty === null ? null : (int) $row->last_supply_qty,
                'last_supply_date' => $row->last_supply_date?->toDateString(),
                'min_qty' => $row->min_qty === null ? null : (int) $row->min_qty,
            ])
            ->all();
    }

    /**
     * Bulk absolute set for one chunk of a spreadsheet upload - upserts per
     * chunk rather than a query per row, which is what lets a 45k-row file
     * finish in seconds of database time.
     *
     * Every figure is independent: a row simply omits 'stock_qty',
     * 'opening_stock' or 'min_qty' when that cell was left blank, and the
     * corresponding column is then left exactly as it was.
     *
     * An upsert applies ONE update-column list to a whole batch, so rows are
     * grouped by which combination of figures they actually supply and each
     * group is written with its own list. Grouping is derived from the keys
     * present rather than enumerated, so the three figures don't turn into
     * seven hand-written branches.
     *
     * Relies on vessel_items' unique (vessel_id, item_id) index.
     *
     * @param  array<int,array{item_id:int,stock_qty?:int,opening_stock?:int,min_qty?:int}>  $rows
     */
    public function bulkSet(int $vesselId, array $rows, ?int $userId = null): int
    {
        if (empty($rows)) {
            return 0;
        }

        $now = now();
        $groups = [];

        foreach ($rows as $row) {
            $supplied = array_values(array_filter(
                self::FIGURES,
                fn ($column) => array_key_exists($column, $row) && $row[$column] !== null
            ));

            if (empty($supplied)) {
                continue;
            }

            $entry = [
                'vessel_id' => $vesselId,
                'item_id' => $row['item_id'],
                'stock_updated_at' => $now,
                'stock_updated_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            // Every column has to appear in the payload for the INSERT half of
            // the upsert; the ones this row didn't supply take their default
            // and are simply left out of the UPDATE list below.
            foreach (self::FIGURES as $column) {
                $entry[$column] = in_array($column, $supplied, true)
                    ? max(0, (int) $row[$column])
                    : ($column === 'stock_qty' ? 0 : null);
            }

            $groups[implode(',', $supplied)][] = $entry;
        }

        $stamps = ['stock_updated_at', 'stock_updated_by', 'updated_at'];

        $written = 0;
        foreach ($groups as $signature => $entries) {
            DB::table('vessel_items')->upsert(
                $entries,
                ['vessel_id', 'item_id'],
                array_merge(explode(',', $signature), $stamps)
            );
            $written += count($entries);
        }

        return $written;
    }

    /**
     * The vessel_items row for this pair, created if the vessel doesn't have
     * the item linked yet - receiving something that isn't in the vessel's
     * catalog should still register rather than silently vanish.
     */
    private function rowFor(int $vesselId, int $itemId): VesselItem
    {
        $row = VesselItem::where('vessel_id', $vesselId)->where('item_id', $itemId)->first();

        if ($row === null) {
            $row = new VesselItem;
            $row->vessel_id = $vesselId;
            $row->item_id = $itemId;
            $row->stock_qty = 0;
        }

        return $row;
    }
}
