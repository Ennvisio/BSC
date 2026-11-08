<?php

namespace App\Services;

use App\StockConsumption;
use App\StockMovement;
use App\VesselItem;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The single funnel every stock write goes through.
 *
 * vessel_items.stock_qty is the live balance; stock_movements (see
 * App\StockMovement) is the ledger behind it - every method below writes
 * exactly one movement row in the same transaction as the balance change,
 * so the two can never drift apart. No caller needs to know this happens.
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
        return DB::transaction(function () use ($vesselId, $itemId, $values, $userId) {
            $row = $this->rowFor($vesselId, $itemId);
            $oldStockQty = (int) $row->stock_qty;
            $oldOpeningStock = $row->opening_stock;

            foreach (self::FIGURES as $column) {
                if (array_key_exists($column, $values) && $values[$column] !== null) {
                    $row->{$column} = max(0, (int) $values[$column]);
                }
            }

            $row->stock_updated_at = now();
            $row->stock_updated_by = $userId;
            $row->save();

            // Only stock_qty and opening_stock move the ledger - min_qty is a
            // threshold, not a quantity that was ever "in" or "out".
            if (array_key_exists('stock_qty', $values) && $values['stock_qty'] !== null && (int) $row->stock_qty !== $oldStockQty) {
                $this->recordMovement($vesselId, $itemId, StockMovement::TYPE_ADJUSTMENT, (int) $row->stock_qty - $oldStockQty, (int) $row->stock_qty, $userId, [
                    'remarks' => 'Manual stock correction.',
                ]);
            }

            if (array_key_exists('opening_stock', $values) && $values['opening_stock'] !== null && $row->opening_stock !== $oldOpeningStock) {
                $this->recordMovement($vesselId, $itemId, StockMovement::TYPE_OPENING, (int) $row->opening_stock, (int) $row->stock_qty, $userId, [
                    'remarks' => $oldOpeningStock === null
                        ? 'Opening balance declared.'
                        : "Opening balance corrected from {$oldOpeningStock}.",
                ]);
            }

            return $row;
        });
    }

    /**
     * A confirmed receipt adds to what's on board, and is what makes stock
     * self-maintaining rather than drifting from reality between manual
     * corrections. Also stamps the paper form's "last supply" figures.
     *
     * $orderId/$orderItemId are the requisition line this came from, if any -
     * RoleController::approveRequisition() always has them at the receipt-
     * confirmation call site, so the ledger row can link straight back to
     * the requisition that brought this item aboard.
     */
    public function addFromReceipt(int $vesselId, int $itemId, int $qty, ?int $userId = null, ?CarbonInterface $receivedOn = null, ?int $orderId = null, ?int $orderItemId = null): ?VesselItem
    {
        if ($qty <= 0) {
            return null;
        }

        return DB::transaction(function () use ($vesselId, $itemId, $qty, $userId, $receivedOn, $orderId, $orderItemId) {
            $row = $this->rowFor($vesselId, $itemId);

            // An opening balance that was never loaded is unknown, not zero -
            // don't invent one retroactively from the first receipt.
            $row->stock_qty = max(0, (int) $row->stock_qty + $qty);
            $row->last_supply_qty = $qty;
            $row->last_supply_date = ($receivedOn ?? now())->toDateString();
            $row->stock_updated_at = now();
            $row->stock_updated_by = $userId;
            $row->save();

            $this->recordMovement($vesselId, $itemId, StockMovement::TYPE_RECEIPT, $qty, (int) $row->stock_qty, $userId, [
                'moved_on' => ($receivedOn ?? now())->toDateString(),
                'order_id' => $orderId,
                'order_item_id' => $orderItemId,
            ]);

            return $row;
        });
    }

    /**
     * The third way stock moves - and the only one that takes it DOWN. An
     * officer stating an item was used/damaged/expired/lost deducts it from
     * ROB immediately (no approval chain - see StockConsumption's own doc
     * comment) and leaves a permanent record of why, unlike setStock() which
     * just restates a figure with no reason attached.
     *
     * Blocks consuming more than what's actually on board rather than
     * letting stock_qty go negative - a real discrepancy between the books
     * and the store gets resolved through setStock() (the Master's own
     * "declare the real figure" tool), not silently absorbed here.
     *
     * Wrapped in a transaction: the balance and the ledger row have to move
     * together, or a failure partway through would silently deduct stock
     * with no record of why, or record a consumption that never actually
     * happened to the balance.
     *
     * $consumedOn takes either a string ('Y-m-d', straight from the form) or
     * a real date object - StockConsumption's own 'consumed_on' => 'date'
     * cast normalizes whichever it gets, so this doesn't have to.
     *
     * @param  array{order_id?:?int,department?:?string,remarks?:?string}  $details
     */
    public function consume(int $vesselId, int $itemId, int $qty, string $consumptionType, string $purpose, \DateTimeInterface|string $consumedOn, int $userId, array $details = []): StockConsumption
    {
        if ($qty <= 0) {
            throw new RuntimeException('Quantity must be greater than zero.');
        }

        return DB::transaction(function () use ($vesselId, $itemId, $qty, $consumptionType, $purpose, $consumedOn, $userId, $details) {
            $row = $this->rowFor($vesselId, $itemId);

            if ($qty > $row->stock_qty) {
                throw new RuntimeException(
                    "Only {$row->stock_qty} in stock - cannot log consuming {$qty}."
                );
            }

            $row->stock_qty -= $qty;
            $row->stock_updated_at = now();
            $row->stock_updated_by = $userId;
            $row->save();

            $consumption = StockConsumption::create([
                'vessel_id' => $vesselId,
                'item_id' => $itemId,
                'order_id' => $details['order_id'] ?? null,
                'consumption_type' => $consumptionType,
                'qty' => $qty,
                'consumed_on' => $consumedOn,
                'department' => $details['department'] ?? null,
                'purpose' => $purpose,
                'remarks' => $details['remarks'] ?? null,
                'recorded_by' => $userId,
            ]);

            $this->recordMovement($vesselId, $itemId, StockMovement::TYPE_CONSUMPTION, -$qty, (int) $row->stock_qty, $userId, [
                'moved_on' => $consumption->consumed_on->toDateString(),
                'order_id' => $details['order_id'] ?? null,
                'stock_consumption_id' => $consumption->id,
                'remarks' => $purpose,
            ]);

            return $consumption;
        });
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
     * $stockImportId links every 'adjustment'/'opening' movement this chunk
     * writes back to the stock_imports row for the upload it came from.
     *
     * @param  array<int,array{item_id:int,stock_qty?:int,opening_stock?:int,min_qty?:int}>  $rows
     */
    public function bulkSet(int $vesselId, array $rows, ?int $userId = null, ?int $stockImportId = null): int
    {
        if (empty($rows)) {
            return 0;
        }

        return DB::transaction(function () use ($vesselId, $rows, $userId, $stockImportId) {
            $now = now();
            $today = $now->toDateString();

            // One query for every item this chunk touches, read before the
            // upsert below overwrites them - the only way to tell "changed"
            // from "restated the same figure" (skipped, per the class docs)
            // without a query per row.
            $itemIds = array_column($rows, 'item_id');
            $old = VesselItem::where('vessel_id', $vesselId)
                ->whereIn('item_id', $itemIds)
                ->get(['item_id', 'stock_qty', 'opening_stock'])
                ->keyBy('item_id');

            $groups = [];
            $movements = [];

            foreach ($rows as $row) {
                $supplied = array_values(array_filter(
                    self::FIGURES,
                    fn ($column) => array_key_exists($column, $row) && $row[$column] !== null
                ));

                if (empty($supplied)) {
                    continue;
                }

                $itemId = $row['item_id'];
                $oldRow = $old->get($itemId);
                $oldStockQty = $oldRow ? (int) $oldRow->stock_qty : 0;
                $oldOpeningStock = $oldRow?->opening_stock;

                $entry = [
                    'vessel_id' => $vesselId,
                    'item_id' => $itemId,
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

                // New stock_qty if this row restates it, otherwise whatever
                // it already was (this row left it alone) - either way, the
                // real balance the moment this row is written.
                $newStockQty = in_array('stock_qty', $supplied, true) ? $entry['stock_qty'] : $oldStockQty;

                if (in_array('stock_qty', $supplied, true) && $newStockQty !== $oldStockQty) {
                    $movements[] = [
                        'vessel_id' => $vesselId, 'item_id' => $itemId,
                        'type' => StockMovement::TYPE_ADJUSTMENT,
                        'qty' => $newStockQty - $oldStockQty, 'balance_after' => $newStockQty,
                        'moved_on' => $today, 'stock_import_id' => $stockImportId,
                        'remarks' => 'Bulk stock upload.', 'user_id' => $userId,
                        'created_at' => $now, 'updated_at' => $now,
                    ];
                }

                if (in_array('opening_stock', $supplied, true) && $entry['opening_stock'] !== $oldOpeningStock) {
                    $movements[] = [
                        'vessel_id' => $vesselId, 'item_id' => $itemId,
                        'type' => StockMovement::TYPE_OPENING,
                        'qty' => $entry['opening_stock'], 'balance_after' => $newStockQty,
                        'moved_on' => $today, 'stock_import_id' => $stockImportId,
                        'remarks' => 'Bulk stock upload.', 'user_id' => $userId,
                        'created_at' => $now, 'updated_at' => $now,
                    ];
                }
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

            // One insert for the whole chunk's worth of movements, same
            // reasoning as the upsert above - a per-row insert is what the
            // chunking exists to avoid.
            if (! empty($movements)) {
                DB::table('stock_movements')->insert($movements);
            }

            return $written;
        });
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

    /**
     * Reconstructs the ledger for one vessel from history that predates it:
     * every item's opening balance, every requisition receipt, every logged
     * consumption, oldest first - then one reconciliation row per item if
     * the rebuilt total still doesn't match today's real stock_qty (it
     * always will the first time something was never captured, e.g. a
     * receipt confirmed before this table existed, or a stock figure that
     * was restated directly in the database).
     *
     * Idempotent by construction, not by tracking a "this was a backfill"
     * flag: an opening/receipt/consumption event is only ever inserted when
     * NOTHING already points at it (no 'opening' movement yet for that item,
     * no movement with that order_item_id, no movement with that
     * stock_consumption_id) - a live write after the first backfill already
     * satisfies that check, and running this again afterwards finds nothing
     * left to add. The reconciliation row follows the same logic one level
     * up: it only appears when the rebuilt sum (everything that already
     * exists, plus whatever this run just added) still disagrees with
     * stock_qty, so a second run - now that the first run's own
     * reconciliation row is part of "everything that already exists" -
     * finds the books already balanced and adds nothing.
     *
     * @return array{opening:int,receipt:int,consumption:int,adjustment:int}
     */
    public function backfillMovementsForVessel(int $vesselId): array
    {
        return DB::transaction(function () use ($vesselId) {
            $counts = ['opening' => 0, 'receipt' => 0, 'consumption' => 0, 'adjustment' => 0];

            $items = VesselItem::where('vessel_id', $vesselId)->get(['item_id', 'opening_stock', 'stock_qty']);

            if ($items->isEmpty()) {
                return $counts;
            }

            $itemIds = $items->pluck('item_id')->all();

            // Everything already in the ledger for this vessel, grouped by
            // item - both to seed each item's running total and to know
            // what's already covered.
            $existing = StockMovement::where('vessel_id', $vesselId)
                ->whereIn('item_id', $itemIds)
                ->get(['item_id', 'type', 'qty', 'order_item_id', 'stock_consumption_id'])
                ->groupBy('item_id');

            // Confirmed receipts: order_items of this vessel's orders that
            // have actually been received, oldest first.
            $receipts = DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('orders.vessel_id', $vesselId)
                ->whereNotNull('orders.rcv_date')
                ->whereIn('order_items.item_id', $itemIds)
                ->orderBy('orders.rcv_date')
                ->get([
                    'order_items.id as order_item_id', 'order_items.item_id', 'order_items.order_id',
                    'order_items.rcv_item_qty', 'order_items.del_item_qty', 'orders.rcv_date',
                ])
                ->groupBy('item_id');

            $consumptions = StockConsumption::where('vessel_id', $vesselId)
                ->whereIn('item_id', $itemIds)
                ->orderBy('consumed_on')
                ->get(['id', 'item_id', 'order_id', 'qty', 'consumed_on'])
                ->groupBy('item_id');

            $now = now();
            $today = $now->toDateString();
            $newRows = [];

            foreach ($items as $vesselItem) {
                $itemId = $vesselItem->item_id;
                $already = $existing->get($itemId, collect());
                $coveredOrderItemIds = $already->pluck('order_item_id')->filter()->all();
                $coveredConsumptionIds = $already->pluck('stock_consumption_id')->filter()->all();
                $hasOpening = $already->contains('type', StockMovement::TYPE_OPENING);

                $events = [];

                if ($vesselItem->opening_stock !== null && ! $hasOpening) {
                    $events[] = [
                        'date' => $today, 'order' => 0, 'type' => StockMovement::TYPE_OPENING,
                        'qty' => (int) $vesselItem->opening_stock, 'affects_balance' => false,
                        'remarks' => 'Backfilled from the vessel\'s declared opening balance.',
                    ];
                }

                foreach ($receipts->get($itemId, collect()) as $line) {
                    if (in_array($line->order_item_id, $coveredOrderItemIds, true)) {
                        continue;
                    }
                    $qty = (int) ($line->rcv_item_qty ?? $line->del_item_qty ?? 0);
                    if ($qty <= 0) {
                        continue;
                    }
                    $events[] = [
                        'date' => $line->rcv_date, 'order' => 1, 'type' => StockMovement::TYPE_RECEIPT,
                        'qty' => $qty, 'affects_balance' => true,
                        'order_id' => $line->order_id, 'order_item_id' => $line->order_item_id,
                        'remarks' => 'Backfilled from the requisition\'s confirmed receipt.',
                    ];
                }

                foreach ($consumptions->get($itemId, collect()) as $row) {
                    if (in_array($row->id, $coveredConsumptionIds, true)) {
                        continue;
                    }
                    $events[] = [
                        'date' => $row->consumed_on->toDateString(), 'order' => 1, 'type' => StockMovement::TYPE_CONSUMPTION,
                        'qty' => -(int) $row->qty, 'affects_balance' => true,
                        'order_id' => $row->order_id, 'stock_consumption_id' => $row->id,
                        'remarks' => 'Backfilled from the logged consumption.',
                    ];
                }

                // An item with no new events at all can still need its sole
                // reconciliation row below, so there's nothing to guard here.
                usort($events, fn ($a, $b) => [$a['date'], $a['order']] <=> [$b['date'], $b['order']]);

                $running = $already->whereIn('type', [StockMovement::TYPE_RECEIPT, StockMovement::TYPE_CONSUMPTION, StockMovement::TYPE_ADJUSTMENT])->sum('qty');

                foreach ($events as $event) {
                    if ($event['affects_balance']) {
                        $running += $event['qty'];
                    }

                    $newRows[] = [
                        'vessel_id' => $vesselId, 'item_id' => $itemId,
                        'type' => $event['type'], 'qty' => $event['qty'], 'balance_after' => $running,
                        'moved_on' => $event['date'],
                        'order_id' => $event['order_id'] ?? null, 'order_item_id' => $event['order_item_id'] ?? null,
                        'stock_consumption_id' => $event['stock_consumption_id'] ?? null,
                        'remarks' => $event['remarks'], 'user_id' => null,
                        'created_at' => $now, 'updated_at' => $now,
                    ];

                    $counts[$event['type']]++;
                }

                $actual = (int) $vesselItem->stock_qty;
                if ($running !== $actual) {
                    $newRows[] = [
                        'vessel_id' => $vesselId, 'item_id' => $itemId,
                        'type' => StockMovement::TYPE_ADJUSTMENT, 'qty' => $actual - $running, 'balance_after' => $actual,
                        'moved_on' => $today,
                        'order_id' => null, 'order_item_id' => null, 'stock_consumption_id' => null,
                        'remarks' => 'Backfill reconciliation.', 'user_id' => null,
                        'created_at' => $now, 'updated_at' => $now,
                    ];
                    $counts['adjustment']++;
                }
            }

            foreach (array_chunk($newRows, 500) as $chunk) {
                DB::table('stock_movements')->insert($chunk);
            }

            return $counts;
        });
    }

    /**
     * One ledger row - the only place that writes to stock_movements, so
     * every caller above gets the same shape. $extra may carry any of
     * 'order_id', 'order_item_id', 'stock_consumption_id', 'stock_import_id',
     * 'remarks' or 'moved_on' (defaults to today).
     */
    private function recordMovement(int $vesselId, int $itemId, string $type, int $qty, int $balanceAfter, ?int $userId, array $extra = []): StockMovement
    {
        return StockMovement::create($extra + [
            'vessel_id' => $vesselId,
            'item_id' => $itemId,
            'type' => $type,
            'qty' => $qty,
            'balance_after' => $balanceAfter,
            'moved_on' => now()->toDateString(),
            'user_id' => $userId,
        ]);
    }
}
