<?php

namespace App\Http\Controllers;

use App\Category;
use App\Exports\PmsReportExport;
use App\Item;
use App\ItemGroup;
use App\PmsUsedPart;
use App\StockMovement;
use App\Support\InventoryGate;
use App\Vessel;
use App\VesselItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Read-only stock (ROB) reporting, as opposed to StockController which
 * MAINTAINS a vessel's figures.
 *
 * Master/Chief Engineer see only their own vessel - the same "it's their
 * store, not shore's" reasoning StockController itself is built on. GM (SRD),
 * the four SRD delegates GM can hand a requisition to (DGM/AGM/AM/
 * Superintendent), and every SSM role see any vessel, since all of them act
 * on requisitions fleet-wide and need to see what a vessel actually holds
 * before approving or sourcing against it. super-admin is included for the
 * same reason it sits on every other fleet-wide screen (Browse Catalog,
 * Certificates, Vessels) - it is the one role assumed to see everything.
 */
class StockReportController extends Controller
{
    private const OWN_VESSEL_ROLES = ['master', 'chief-engineer'];

    private InventoryGate $gate;

    public function __construct(InventoryGate $gate)
    {
        $this->gate = $gate;

        $this->middleware('auth');
    }

    public function index()
    {
        $this->authorizeViewer();

        $lockedVessel = $this->isFleetWide() ? null : auth()->user()->role->vessel;

        return view('layouts.stock-report', [
            'lockedVessel' => $lockedVessel,
            'vessels' => $lockedVessel ? null : Vessel::where('status', true)->orderBy('name')->get(),
            'categories' => Category::where('is_catalog', true)->where('symbol', '!=', 'IMP')
                ->where('status', true)->orderBy('name')->get(),
        ]);
    }

    /** AJAX: the two headline figures shown above the table. */
    public function summary(Request $request)
    {
        $this->authorizeViewer();

        $vesselId = $this->resolveVesselId($request);
        $categoryId = (int) $request->query('category_id');

        $base = DB::table('vessel_items')
            ->join('items', 'items.id', '=', 'vessel_items.item_id')
            ->where('vessel_items.vessel_id', $vesselId)
            ->where('items.category_id', $categoryId)
            ->where('items.status', true);
        $this->scopeToGroup($base, $request, $categoryId);

        return response()->json([
            'total_items' => (clone $base)->count(),
            'zero_stock' => (clone $base)->where('vessel_items.stock_qty', '<=', 0)->count(),
        ]);
    }

    /**
     * AJAX: the item rows, in the standard DataTables server-side protocol
     * (draw/start/length/search/order in, {draw, recordsTotal,
     * recordsFiltered, data} out) - unlike Browse Catalog's plain JSON list,
     * a report scoped to a whole category can be tens of thousands of rows
     * (this fleet's own Stores category alone runs to 45,000+ for one
     * vessel), too many to ship to the browser and paginate client-side.
     */
    public function data(Request $request)
    {
        $this->authorizeViewer();

        $vesselId = $this->resolveVesselId($request);
        $categoryId = (int) $request->query('category_id');

        $query = DB::table('vessel_items')
            ->join('items', 'items.id', '=', 'vessel_items.item_id')
            ->where('vessel_items.vessel_id', $vesselId)
            ->where('items.category_id', $categoryId)
            ->where('items.status', true);
        $this->scopeToGroup($query, $request, $categoryId);

        $recordsTotal = (clone $query)->count();

        if ($request->boolean('low_only')) {
            $query->whereNotNull('vessel_items.min_qty')
                ->whereColumn('vessel_items.stock_qty', '<=', 'vessel_items.min_qty');
        }

        $search = trim((string) $request->input('search.value'));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('items.name', 'like', "%{$search}%")
                    ->orWhere('items.article_number', 'like', "%{$search}%")
                    ->orWhere('items.impa_code', 'like', "%{$search}%");
            });
        }

        $recordsFiltered = (clone $query)->count();

        // Index into the <thead> the view renders, not an arbitrary label -
        // has to move in lockstep with stock-report.blade.php's column order.
        $orderable = [
            'items.name', 'items.article_number', 'items.impa_code', 'items.unit',
            'vessel_items.opening_stock', 'vessel_items.stock_qty', 'vessel_items.min_qty',
            'vessel_items.last_supply_qty', 'vessel_items.last_supply_date',
        ];
        $orderColumn = $orderable[(int) $request->input('order.0.column', 0)] ?? $orderable[0];
        $orderDir = $request->input('order.0.dir') === 'desc' ? 'desc' : 'asc';

        $rows = $query->orderBy($orderColumn, $orderDir)
            ->offset((int) $request->input('start', 0))
            ->limit(min(500, max(1, (int) $request->input('length', 25))))
            ->get([
                'items.id as item_id', 'items.name', 'items.article_number', 'items.impa_code', 'items.unit',
                'vessel_items.opening_stock', 'vessel_items.stock_qty', 'vessel_items.min_qty',
                'vessel_items.last_supply_qty', 'vessel_items.last_supply_date',
            ]);

        return response()->json([
            'draw' => (int) $request->input('draw', 1),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows->map(fn ($row) => [
                'item_id' => $row->item_id,
                'name' => $row->name,
                'article_number' => $row->article_number,
                'impa_code' => $row->impa_code,
                'unit' => $row->unit,
                'opening_stock' => $row->opening_stock,
                'stock_qty' => (int) $row->stock_qty,
                'min_qty' => $row->min_qty,
                'low' => $row->min_qty !== null && (int) $row->stock_qty <= (int) $row->min_qty,
                'last_supply_qty' => $row->last_supply_qty,
                'last_supply_date' => $row->last_supply_date,
            ]),
        ]);
    }

    // ================================================================== Current Stock

    /**
     * The Inventory module's own page: "opening + received − consumed ±
     * corrections = current" for every item in one vessel's catalog, pulled
     * from stock_movements rather than vessel_items alone. Open to any ship
     * role (own vessel only), not just Master/Chief Engineer - wider than
     * Stock Report above, which this never changes.
     */
    public function stock()
    {
        $this->authorizeInventoryViewer();

        $lockedVessel = $this->gate->isFleetWide() ? null : auth()->user()->role->vessel;

        return view('layouts.stock-current', [
            'lockedVessel' => $lockedVessel,
            'vessels' => $lockedVessel ? null : Vessel::where('status', true)->orderBy('name')->get(),
            'categories' => Category::where('is_catalog', true)->where('symbol', '!=', 'IMP')
                ->where('status', true)->orderBy('name')->get(),
        ]);
    }

    /** AJAX: the four headline figures above the table. */
    public function stockSummary(Request $request)
    {
        $this->authorizeInventoryViewer();

        $vesselId = $this->gate->resolveVesselId($request);
        $categoryId = (int) $request->query('category_id');
        $base = $this->stockBaseQuery($request, $vesselId, $categoryId);

        $consumedThisMonth = (int) (clone $base)
            ->join('stock_movements', function ($join) use ($vesselId) {
                $join->on('stock_movements.item_id', '=', 'vessel_items.item_id')
                    ->where('stock_movements.vessel_id', $vesselId)
                    ->where('stock_movements.type', StockMovement::TYPE_CONSUMPTION);
            })
            ->where('stock_movements.moved_on', '>=', now()->startOfMonth()->toDateString())
            ->sum(DB::raw('-stock_movements.qty'));

        return response()->json([
            'in_stock' => (clone $base)->where('vessel_items.stock_qty', '>', 0)->count(),
            'zero_stock' => (clone $base)->where('vessel_items.stock_qty', '<=', 0)->count(),
            'below_minimum' => (clone $base)->whereNotNull('vessel_items.min_qty')
                ->whereColumn('vessel_items.stock_qty', '<=', 'vessel_items.min_qty')->count(),
            'consumed_this_month' => $consumedThisMonth,
        ]);
    }

    /** AJAX: the item rows, same server-side DataTables protocol as data() above. */
    public function stockData(Request $request)
    {
        $this->authorizeInventoryViewer();

        $vesselId = $this->gate->resolveVesselId($request);
        $categoryId = (int) $request->query('category_id');
        $query = $this->stockBaseQuery($request, $vesselId, $categoryId);

        $recordsTotal = (clone $query)->count();

        if ($request->boolean('low_only')) {
            $query->whereNotNull('vessel_items.min_qty')
                ->whereColumn('vessel_items.stock_qty', '<=', 'vessel_items.min_qty');
        }

        $search = trim((string) $request->input('search.value'));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('items.name', 'like', "%{$search}%")
                    ->orWhere('items.article_number', 'like', "%{$search}%")
                    ->orWhere('items.impa_code', 'like', "%{$search}%");
            });
        }

        $recordsFiltered = (clone $query)->count();

        // Index into the <thead> stock-current.blade.php renders.
        $orderable = [
            'items.name', 'items.article_number', 'items.impa_code', 'items.unit',
            'vessel_items.opening_stock', 'total_received', 'total_consumed', 'net_adjustments',
            'vessel_items.stock_qty', 'vessel_items.min_qty',
            'vessel_items.last_supply_qty', 'vessel_items.last_supply_date', 'last_consumed_date',
        ];
        $orderColumn = $orderable[(int) $request->input('order.0.column', 0)] ?? $orderable[0];
        $orderDir = $request->input('order.0.dir') === 'desc' ? 'desc' : 'asc';

        $rows = $query->orderBy($orderColumn, $orderDir)
            ->offset((int) $request->input('start', 0))
            ->limit(min(500, max(1, (int) $request->input('length', 25))))
            ->get($this->stockColumns());

        return response()->json([
            'draw' => (int) $request->input('draw', 1),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows->map(fn ($row) => $this->mapStockRow($row)),
        ]);
    }

    /** Every matching row (filters applied, no pagination) as an Excel download, via the same generic report export PMS reports use. */
    public function stockExport(Request $request)
    {
        $this->authorizeInventoryViewer();

        $vesselId = $this->gate->resolveVesselId($request);
        $categoryId = (int) $request->query('category_id');
        $query = $this->stockBaseQuery($request, $vesselId, $categoryId);

        if ($request->boolean('low_only')) {
            $query->whereNotNull('vessel_items.min_qty')
                ->whereColumn('vessel_items.stock_qty', '<=', 'vessel_items.min_qty');
        }

        $rows = $query->orderBy('items.name')->get($this->stockColumns())
            ->map(fn ($row) => $this->mapStockRow($row))
            ->all();

        $vessel = Vessel::find($vesselId);
        $category = Category::find($categoryId);
        $scopeName = $vessel->name ?? 'Fleet';

        $meta = [
            'Report' => 'Current Stock', 'Vessel' => $scopeName, 'Category' => $category->name ?? '',
            'Rows' => (string) count($rows), 'Exported' => now()->format('d/m/Y H:i'),
        ];
        $file = 'Current Stock - '.preg_replace('/[^A-Za-z0-9 ._-]/', '', $scopeName).' - '.now()->format('Y-m-d').'.xlsx';

        return Excel::download(new PmsReportExport('Current Stock', $meta, $this->stockExportColumns(), $rows, []), $file);
    }

    /** The same filtered rows as a plain printable page - no pagination, no admin chrome. */
    public function stockPrint(Request $request)
    {
        $this->authorizeInventoryViewer();

        $vesselId = $this->gate->resolveVesselId($request);
        $categoryId = (int) $request->query('category_id');
        $query = $this->stockBaseQuery($request, $vesselId, $categoryId);

        if ($request->boolean('low_only')) {
            $query->whereNotNull('vessel_items.min_qty')
                ->whereColumn('vessel_items.stock_qty', '<=', 'vessel_items.min_qty');
        }

        $rows = $query->orderBy('items.name')->get($this->stockColumns())
            ->map(fn ($row) => $this->mapStockRow($row));

        return view('layouts.stock-current-print', [
            'vessel' => Vessel::find($vesselId),
            'category' => Category::find($categoryId),
            'rows' => $rows,
        ]);
    }

    // ================================================================== Item stock card

    /**
     * One item's whole story on one vessel: "opening + received − consumed ±
     * corrections = current", as the chronological movements that actually
     * produced it - each one's balance_after is read straight off the
     * ledger row (see StockMovement/StockService), never recomputed, so it
     * can never drift from what's displayed.
     */
    public function stockItem(Request $request, $itemId)
    {
        $this->authorizeInventoryViewer();

        $vesselId = $this->gate->resolveVesselId($request);
        $item = Item::findOrFail($itemId);
        [$from, $to] = $this->itemCardDateRange($request);

        return view('layouts.stock-item-card', [
            'item' => $item,
            'vessel' => Vessel::find($vesselId),
            'vesselItem' => VesselItem::where('vessel_id', $vesselId)->where('item_id', $itemId)->first(),
            'movements' => $this->itemCardMovements($vesselId, (int) $itemId, $from, $to),
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function stockItemExport(Request $request, $itemId)
    {
        $this->authorizeInventoryViewer();

        $vesselId = $this->gate->resolveVesselId($request);
        $item = Item::findOrFail($itemId);
        [$from, $to] = $this->itemCardDateRange($request);
        $movements = $this->itemCardMovements($vesselId, (int) $itemId, $from, $to);
        $vessel = Vessel::find($vesselId);

        $columns = [
            'moved_on' => 'Date', 'type' => 'Type', 'qty' => 'Qty', 'balance_after' => 'Balance After',
            'source' => 'Source', 'remarks' => 'Remarks', 'user' => 'By',
        ];
        $rows = $movements->map(fn ($m) => [
            'moved_on' => $m->moved_on->toDateString(),
            'type' => $m->typeLabel(),
            'qty' => $m->qty,
            'balance_after' => $m->balance_after,
            'source' => $this->itemCardSourceLabel($m),
            'remarks' => $m->remarks,
            'user' => $m->user->name ?? '',
        ])->all();

        $meta = [
            'Report' => 'Item Stock Card', 'Item' => $item->name, 'Vessel' => $vessel->name ?? '',
            'From' => $from, 'To' => $to, 'Rows' => (string) count($rows), 'Exported' => now()->format('d/m/Y H:i'),
        ];
        $file = 'Stock Card - '.preg_replace('/[^A-Za-z0-9 ._-]/', '', $item->name).' - '.now()->format('Y-m-d').'.xlsx';

        return Excel::download(new PmsReportExport('Item Stock Card', $meta, $columns, $rows, []), $file);
    }

    public function stockItemPdf(Request $request, $itemId)
    {
        $this->authorizeInventoryViewer();

        $vesselId = $this->gate->resolveVesselId($request);
        $item = Item::findOrFail($itemId);
        [$from, $to] = $this->itemCardDateRange($request);

        $data = [
            'item' => $item,
            'vessel' => Vessel::find($vesselId),
            'vesselItem' => VesselItem::where('vessel_id', $vesselId)->where('item_id', $itemId)->first(),
            'movements' => $this->itemCardMovements($vesselId, (int) $itemId, $from, $to),
            'from' => $from,
            'to' => $to,
        ];

        $name = 'Stock Card - '.preg_replace('/[^A-Za-z0-9 ._-]/', '', $item->name);

        return Pdf::loadView('layouts.stock-item-card-pdf', $data)
            ->setPaper('a4', 'portrait')
            ->download($name.'.pdf');
    }

    /** [from, to] as 'Y-m-d' strings - defaults to the last 12 months, same convention PMS reports use. */
    private function itemCardDateRange(Request $request): array
    {
        $from = $request->filled('from') ? $request->query('from') : now()->subMonths(12)->toDateString();
        $to = $request->filled('to') ? $request->query('to') : now()->toDateString();

        return [$from, $to];
    }

    /**
     * One item's movements on one vessel, oldest first, with everything the
     * card needs to link back to where each one came from already eager-
     * loaded - a requisition line (order), who made the write (user), and a
     * logged consumption's own detail (type/purpose/department/attachments),
     * further resolved to the PMS job it was taken for if it was one.
     */
    private function itemCardMovements(int $vesselId, int $itemId, string $from, string $to)
    {
        $movements = StockMovement::where('vessel_id', $vesselId)
            ->where('item_id', $itemId)
            ->whereBetween('moved_on', [$from, $to])
            ->with(['order:id,req_no', 'user:id,name', 'stockConsumption.attachments', 'stockConsumption.recordedBy', 'stockImport:id,filename'])
            ->orderBy('moved_on')
            ->orderBy('id')
            ->get();

        $consumptionIds = $movements->pluck('stock_consumption_id')->filter()->all();
        if (! empty($consumptionIds)) {
            $pmsJobIds = PmsUsedPart::whereIn('stock_consumption_id', $consumptionIds)->pluck('job_id', 'stock_consumption_id');
            $movements->each(function ($m) use ($pmsJobIds) {
                $m->pmsJobId = $pmsJobIds[$m->stock_consumption_id] ?? null;
            });
        }

        return $movements;
    }

    /** Plain-text "where this came from", for the Excel export (the screen view links it instead). */
    private function itemCardSourceLabel(StockMovement $m): string
    {
        if ($m->order) {
            return 'Requisition '.($m->order->req_no ?: '#'.$m->order_id);
        }
        if ($m->stockConsumption) {
            return 'Consumption: '.$m->stockConsumption->typeLabel().' - '.\Illuminate\Support\Str::limit($m->stockConsumption->purpose, 60);
        }
        if ($m->stockImport) {
            return 'Upload: '.$m->stockImport->filename;
        }

        return '—';
    }

    /**
     * vessel_items/items joined with one GROUP BY pass over stock_movements
     * (total received/consumed/net adjustments/last consumed date per item) -
     * the totals are computed once here, not per row, however many items
     * the category holds.
     */
    private function stockBaseQuery(Request $request, int $vesselId, int $categoryId)
    {
        $movementTotals = DB::table('stock_movements')
            ->where('vessel_id', $vesselId)
            ->selectRaw(
                "item_id,
                SUM(CASE WHEN type = 'receipt' THEN qty ELSE 0 END) as total_received,
                SUM(CASE WHEN type = 'consumption' THEN -qty ELSE 0 END) as total_consumed,
                SUM(CASE WHEN type = 'adjustment' THEN qty ELSE 0 END) as net_adjustments,
                MAX(CASE WHEN type = 'consumption' THEN moved_on END) as last_consumed_date"
            )
            ->groupBy('item_id');

        $query = DB::table('vessel_items')
            ->join('items', 'items.id', '=', 'vessel_items.item_id')
            ->leftJoinSub($movementTotals, 'movement_totals', function ($join) {
                $join->on('movement_totals.item_id', '=', 'vessel_items.item_id');
            })
            ->where('vessel_items.vessel_id', $vesselId)
            ->where('items.category_id', $categoryId)
            ->where('items.status', true);

        $this->scopeToGroup($query, $request, $categoryId);

        return $query;
    }

    private function stockColumns(): array
    {
        return [
            'items.id as item_id', 'items.name', 'items.article_number', 'items.impa_code', 'items.unit',
            'vessel_items.opening_stock', 'vessel_items.stock_qty', 'vessel_items.min_qty',
            'vessel_items.last_supply_qty', 'vessel_items.last_supply_date',
            DB::raw('COALESCE(movement_totals.total_received, 0) as total_received'),
            DB::raw('COALESCE(movement_totals.total_consumed, 0) as total_consumed'),
            DB::raw('COALESCE(movement_totals.net_adjustments, 0) as net_adjustments'),
            'movement_totals.last_consumed_date',
        ];
    }

    private function mapStockRow($row): array
    {
        return [
            'item_id' => $row->item_id,
            'name' => $row->name,
            'article_number' => $row->article_number,
            'impa_code' => $row->impa_code,
            'unit' => $row->unit,
            'opening_stock' => $row->opening_stock,
            'total_received' => (int) $row->total_received,
            'total_consumed' => (int) $row->total_consumed,
            'net_adjustments' => (int) $row->net_adjustments,
            'stock_qty' => (int) $row->stock_qty,
            'min_qty' => $row->min_qty,
            'low' => $row->min_qty !== null && (int) $row->stock_qty <= (int) $row->min_qty,
            'last_supply_qty' => $row->last_supply_qty,
            'last_supply_date' => $row->last_supply_date,
            'last_consumed_date' => $row->last_consumed_date,
        ];
    }

    /** row key => column heading, for the Excel export (PmsReportExport's generic shape). */
    private function stockExportColumns(): array
    {
        return [
            'name' => 'Item Name', 'article_number' => 'Article Number', 'impa_code' => 'IMPA Code', 'unit' => 'Unit',
            'opening_stock' => 'Opening Stock', 'total_received' => 'Total Received', 'total_consumed' => 'Total Consumed',
            'net_adjustments' => 'Net Adjustments', 'stock_qty' => 'Current Stock', 'min_qty' => 'Reorder Level',
            'last_supply_qty' => 'Last Supply Qty', 'last_supply_date' => 'Last Supply Date',
            'last_consumed_date' => 'Last Consumed Date',
        ];
    }

    private function authorizeInventoryViewer(): void
    {
        abort_unless($this->gate->canView(), 403, 'You are not authorised to view inventory.');
    }

    // ================================================================== Stock Report (existing)

    /**
     * AJAX: one level of the category's group tree for the Group picker -
     * the top level when parent_id is absent. Only branches this vessel
     * actually holds something under are returned, each with how many of
     * the vessel's items sit anywhere beneath it, so the tree never offers
     * a folder that would open onto an empty report.
     */
    public function groups(Request $request)
    {
        $this->authorizeViewer();

        $vesselId = $this->resolveVesselId($request);
        $categoryId = (int) $request->query('category_id');
        $parentId = $request->filled('parent_id') ? (int) $request->query('parent_id') : null;

        $counts = $this->subtreeCounts($vesselId, $categoryId);

        $groups = ItemGroup::where('category_id', $categoryId)
            ->where('parent_id', $parentId)
            ->orderBy('name')
            ->get(['id', 'name', 'path'])
            ->filter(fn ($group) => ($counts[$group->id] ?? 0) > 0);

        $children = $this->childrenMap($categoryId);

        return response()->json($groups->map(fn ($group) => [
            'id' => $group->id,
            'name' => $group->name,
            'path' => $group->path,
            'items' => $counts[$group->id],
            'has_children' => collect($children[$group->id] ?? [])->contains(fn ($id) => ($counts[$id] ?? 0) > 0),
        ])->values());
    }

    /**
     * Narrows a report query to one group and everything under it. Picking a
     * parent means "all of it", picking a leaf means just that leaf - the
     * same id set either way, it's only the size that differs.
     */
    private function scopeToGroup($query, Request $request, int $categoryId): void
    {
        if (! $request->filled('group_id')) {
            return;
        }

        $groupId = (int) $request->query('group_id');
        abort_unless(
            ItemGroup::where('id', $groupId)->where('category_id', $categoryId)->exists(),
            422,
            'That group is not part of the chosen category.'
        );

        $query->whereIn('items.item_group_id', $this->descendantIds($categoryId, $groupId));
    }

    /** $groupId plus every group beneath it, walked in memory. */
    private function descendantIds(int $categoryId, int $groupId): array
    {
        $children = $this->childrenMap($categoryId);
        $ids = [];
        $stack = [$groupId];

        while ($stack) {
            $id = array_pop($stack);
            $ids[] = $id;
            array_push($stack, ...($children[$id] ?? []));
        }

        return $ids;
    }

    /** parent_id => [child ids] for one category's tree (a few thousand rows at most). */
    private function childrenMap(int $categoryId): array
    {
        static $maps = [];

        if (! isset($maps[$categoryId])) {
            $maps[$categoryId] = [];
            foreach (ItemGroup::where('category_id', $categoryId)->get(['id', 'parent_id']) as $group) {
                $maps[$categoryId][$group->parent_id ?? 0][] = $group->id;
            }
        }

        return $maps[$categoryId];
    }

    /**
     * group id => number of this vessel's items anywhere beneath it. Counted
     * per group in one query, then each count carried up through its
     * ancestors, rather than one subtree query per node shown.
     */
    private function subtreeCounts(int $vesselId, int $categoryId): array
    {
        $direct = DB::table('vessel_items')
            ->join('items', 'items.id', '=', 'vessel_items.item_id')
            ->where('vessel_items.vessel_id', $vesselId)
            ->where('items.category_id', $categoryId)
            ->where('items.status', true)
            ->whereNotNull('items.item_group_id')
            ->groupBy('items.item_group_id')
            ->pluck(DB::raw('count(*)'), 'items.item_group_id');

        $parents = ItemGroup::where('category_id', $categoryId)->pluck('parent_id', 'id')->all();

        $totals = [];
        foreach ($direct as $groupId => $count) {
            for ($id = $groupId; $id !== null; $id = $parents[$id] ?? null) {
                $totals[$id] = ($totals[$id] ?? 0) + (int) $count;
            }
        }

        return $totals;
    }

    /**
     * The vessel this request is scoped to. Locked roles never get to
     * override it from the query string - the same "never trust the
     * request for this" rule StockController itself follows for Master.
     */
    private function resolveVesselId(Request $request): int
    {
        if (! $this->isFleetWide()) {
            return (int) auth()->user()->role->vessel_id;
        }

        $vesselId = (int) $request->query('vessel_id');
        abort_unless(
            $vesselId > 0 && Vessel::where('id', $vesselId)->where('status', true)->exists(),
            422,
            'Choose a vessel first.'
        );

        return $vesselId;
    }

    private function isFleetWide(): bool
    {
        return in_array(auth()->user()->role->role ?? null, InventoryGate::FLEET_WIDE_ROLES, true);
    }

    private function authorizeViewer(): void
    {
        $role = auth()->user()->role->role ?? null;

        abort_unless(
            in_array($role, InventoryGate::FLEET_WIDE_ROLES, true) || in_array($role, self::OWN_VESSEL_ROLES, true),
            403,
            'You are not authorised to view stock reports.'
        );
    }
}
