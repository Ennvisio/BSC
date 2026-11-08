<?php

namespace App\Http\Controllers;

use App\Category;
use App\Exports\PmsReportExport;
use App\Order;
use App\StockConsumption;
use App\StockMovement;
use App\Support\InventoryGate;
use App\Vessel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * The fleet-level view of the stock_movements ledger Current Stock/the item
 * card only ever look at one vessel's own slice of - what moved, by type,
 * over time, and (unlike those two) summed across the whole fleet when a
 * fleet-wide viewer doesn't narrow to one vessel.
 *
 * Also carries the requisition-to-consumption trace: for one requisition,
 * how much of what it brought aboard has actually been used since (see
 * requisitionTrace()).
 */
class StockMovementsReportController extends Controller
{
    private InventoryGate $gate;

    public function __construct(InventoryGate $gate)
    {
        $this->gate = $gate;

        $this->middleware('auth');
    }

    public function index()
    {
        $this->authorizeViewer();

        $lockedVessel = $this->gate->isFleetWide() ? null : auth()->user()->role->vessel;

        return view('layouts.stock-movements-report', [
            'lockedVessel' => $lockedVessel,
            'vessels' => $lockedVessel ? null : Vessel::where('status', true)->orderBy('name')->get(),
            'categories' => Category::where('is_catalog', true)->where('symbol', '!=', 'IMP')
                ->where('status', true)->orderBy('name')->get(),
            'types' => $this->typeOptions(),
        ]);
    }

    /** AJAX: the movement rows, standard DataTables server-side protocol. */
    public function data(Request $request)
    {
        $this->authorizeViewer();

        $query = $this->filteredQuery($request);

        $recordsTotal = (clone $query)->count();

        $search = trim((string) $request->input('search.value'));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('items.name', 'like', "%{$search}%")
                    ->orWhere('items.article_number', 'like', "%{$search}%");
            });
        }

        $recordsFiltered = (clone $query)->count();

        $orderable = ['stock_movements.moved_on', 'items.name', 'stock_movements.type', 'stock_movements.qty', 'stock_movements.balance_after', 'vessels.name'];
        $orderColumn = $orderable[(int) $request->input('order.0.column', 0)] ?? $orderable[0];
        $orderDir = $request->input('order.0.dir') === 'desc' ? 'desc' : 'asc';

        $rows = $query->orderBy($orderColumn, $orderDir)
            ->orderBy('stock_movements.id', $orderDir)
            ->offset((int) $request->input('start', 0))
            ->limit(min(500, max(1, (int) $request->input('length', 25))))
            ->get($this->movementColumns());

        return response()->json([
            'draw' => (int) $request->input('draw', 1),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows->map(fn ($row) => $this->mapMovementRow($row)),
        ]);
    }

    /**
     * AJAX: the three summary views - received vs consumed per month (feeds
     * a Chart.js bar chart), consumption by type (feeds a doughnut chart),
     * and the top 10 consumed items (a plain ranked list - a chart adds
     * nothing a sorted table doesn't already say better for ten rows).
     */
    public function summary(Request $request)
    {
        $this->authorizeViewer();

        $base = $this->filteredQuery($request);

        // MySQL (live) and SQLite (tests) format a date to 'YYYY-MM'
        // differently - one selectRaw() built with whichever applies, not
        // two chained calls (selectRaw() APPENDS columns, so calling it
        // twice would ask for 'ym' aliased two different ways in one query).
        $monthExpr = DB::connection()->getDriverName() === 'mysql'
            ? "DATE_FORMAT(stock_movements.moved_on, '%Y-%m')"
            : "strftime('%Y-%m', stock_movements.moved_on)";

        $byMonth = (clone $base)
            ->selectRaw("{$monthExpr} as ym,
                SUM(CASE WHEN stock_movements.type = 'receipt' THEN stock_movements.qty ELSE 0 END) as received,
                SUM(CASE WHEN stock_movements.type = 'consumption' THEN -stock_movements.qty ELSE 0 END) as consumed")
            ->groupBy('ym')->orderBy('ym')->get();

        $byType = (clone $base)->where('stock_movements.type', StockMovement::TYPE_CONSUMPTION)
            ->selectRaw("COALESCE(stock_consumptions.consumption_type, 'other') as consumption_type, SUM(-stock_movements.qty) as qty")
            ->groupBy('consumption_type')->orderByDesc('qty')->get();

        $topConsumed = (clone $base)->where('stock_movements.type', StockMovement::TYPE_CONSUMPTION)
            ->selectRaw('items.name, items.unit, SUM(-stock_movements.qty) as qty')
            ->groupBy('items.id', 'items.name', 'items.unit')
            ->orderByDesc('qty')->limit(10)->get();

        return response()->json([
            'by_month' => $byMonth->map(fn ($r) => ['month' => $r->ym, 'received' => (int) $r->received, 'consumed' => (int) $r->consumed]),
            'by_type' => $byType->map(fn ($r) => ['type' => $r->consumption_type, 'label' => StockConsumption::TYPES[$r->consumption_type] ?? ucfirst($r->consumption_type), 'qty' => (int) $r->qty]),
            'top_consumed' => $topConsumed->map(fn ($r) => ['name' => $r->name, 'unit' => $r->unit, 'qty' => (int) $r->qty]),
        ]);
    }

    public function export(Request $request)
    {
        $this->authorizeViewer();

        $query = $this->filteredQuery($request);
        $rows = $query->orderBy('stock_movements.moved_on')->get($this->movementColumns())
            ->map(fn ($row) => $this->mapMovementRow($row))->all();

        $vesselId = $this->gate->resolveOptionalVesselId($request);
        $vessel = $vesselId ? Vessel::find($vesselId) : null;
        $scopeName = $vessel->name ?? 'Fleet';

        $columns = [
            'moved_on' => 'Date', 'vessel' => 'Vessel', 'name' => 'Item', 'type' => 'Type', 'qty' => 'Qty',
            'balance_after' => 'Balance After', 'department' => 'Department', 'source' => 'Source', 'user' => 'By',
        ];
        $meta = ['Report' => 'Stock Movements', 'Vessel' => $scopeName, 'Rows' => (string) count($rows), 'Exported' => now()->format('d/m/Y H:i')];
        $file = 'Stock Movements - '.preg_replace('/[^A-Za-z0-9 ._-]/', '', $scopeName).' - '.now()->format('Y-m-d').'.xlsx';

        return Excel::download(new PmsReportExport('Stock Movements', $meta, $columns, $rows, []), $file);
    }

    public function pdf(Request $request)
    {
        $this->authorizeViewer();

        $query = $this->filteredQuery($request);
        $rows = $query->orderBy('stock_movements.moved_on')->get($this->movementColumns())
            ->map(fn ($row) => $this->mapMovementRow($row));

        $vesselId = $this->gate->resolveOptionalVesselId($request);

        $name = 'Stock Movements - '.now()->format('Y-m-d');

        return Pdf::loadView('layouts.stock-movements-report-pdf', [
            'vessel' => $vesselId ? Vessel::find($vesselId) : null,
            'rows' => $rows,
            'from' => $request->query('from') ?: now()->subMonths(12)->toDateString(),
            'to' => $request->query('to') ?: now()->toDateString(),
        ])->setPaper('a4', 'landscape')->download($name.'.pdf');
    }

    // ------------------------------------------------------------- requisition trace

    /** AJAX: requisitions this viewer may trace, matched by req_no - the typeahead this feeds. */
    public function requisitionSearch(Request $request)
    {
        $this->authorizeViewer();

        $term = trim((string) $request->query('q'));
        $vesselId = $this->gate->resolveOptionalVesselId($request);

        $query = Order::where('ord_status', true);
        if ($vesselId) {
            $query->where('vessel_id', $vesselId);
        }
        if ($term !== '') {
            $query->where('req_no', 'like', "%{$term}%");
        }

        $orders = $query->orderBy('created_at', 'desc')->limit(20)->get(['id', 'req_no', 'vessel_id']);

        return response()->json($orders->map(fn ($o) => ['id' => $o->id, 'req_no' => $o->req_no ?: '#'.$o->id]));
    }

    /**
     * AJAX: one requisition's lines, received qty against each one vs
     * everything logged as consumed against it since
     * (stock_consumptions.order_id) - the loop a requisition closes once
     * what it brought aboard actually starts getting used.
     */
    public function requisitionTrace(Request $request)
    {
        $this->authorizeViewer();

        $request->validate(['order_id' => 'required|exists:orders,id']);

        $order = Order::with('orderItems.item')->findOrFail($request->order_id);

        if (! $this->gate->isFleetWide()) {
            abort_unless($order->vessel_id == auth()->user()->role->vessel_id, 403);
        }

        $consumed = DB::table('stock_consumptions')
            ->where('order_id', $order->id)
            ->groupBy('item_id')
            ->pluck(DB::raw('SUM(qty) as total'), 'item_id');

        $lines = $order->orderItems->map(function ($line) use ($consumed) {
            $received = (int) ($line->rcv_item_qty ?? $line->del_item_qty ?? 0);
            $consumedQty = (int) ($consumed[$line->item_id] ?? 0);

            return [
                'item_name' => $line->item->name ?? '',
                'unit' => $line->item->unit ?? '',
                'required_qty' => (int) $line->item_qty,
                'received_qty' => $received,
                'consumed_qty' => $consumedQty,
                'remaining_qty' => max(0, $received - $consumedQty),
            ];
        });

        return response()->json(['req_no' => $order->req_no ?: '#'.$order->id, 'lines' => $lines]);
    }

    // ------------------------------------------------------------- shared

    private function filteredQuery(Request $request)
    {
        $vesselId = $this->gate->resolveOptionalVesselId($request);
        $from = $request->filled('from') ? $request->query('from') : now()->subMonths(12)->toDateString();
        $to = $request->filled('to') ? $request->query('to') : now()->toDateString();

        $query = DB::table('stock_movements')
            ->join('items', 'items.id', '=', 'stock_movements.item_id')
            ->join('vessels', 'vessels.id', '=', 'stock_movements.vessel_id')
            ->leftJoin('stock_consumptions', 'stock_consumptions.id', '=', 'stock_movements.stock_consumption_id')
            ->leftJoin('users', 'users.id', '=', 'stock_movements.user_id')
            ->whereBetween('stock_movements.moved_on', [$from, $to]);

        if ($vesselId) {
            $query->where('stock_movements.vessel_id', $vesselId);
        }

        if ($request->filled('category_id')) {
            $query->where('items.category_id', (int) $request->query('category_id'));
        }

        if ($request->filled('item_id')) {
            $query->where('stock_movements.item_id', (int) $request->query('item_id'));
        }

        if ($request->filled('department')) {
            $query->where('stock_consumptions.department', $request->query('department'));
        }

        if ($request->filled('type')) {
            $type = $request->query('type');
            if (array_key_exists($type, StockConsumption::TYPES) || $type === StockConsumption::TYPE_PMS_JOB) {
                $query->where('stock_movements.type', StockMovement::TYPE_CONSUMPTION)
                    ->where('stock_consumptions.consumption_type', $type);
            } else {
                $query->where('stock_movements.type', $type);
            }
        }

        return $query;
    }

    private function movementColumns(): array
    {
        return [
            'stock_movements.id', 'stock_movements.moved_on', 'stock_movements.type', 'stock_movements.qty',
            'stock_movements.balance_after', 'stock_movements.item_id', 'stock_movements.order_id',
            'stock_movements.vessel_id', 'stock_movements.remarks', 'items.name', 'vessels.name as vessel_name',
            'stock_consumptions.department', 'stock_consumptions.purpose', 'stock_consumptions.consumption_type',
            'users.name as user_name',
        ];
    }

    private function mapMovementRow($row): array
    {
        $source = null;
        if ($row->order_id) {
            $source = 'Req #'.$row->order_id;
        } elseif ($row->purpose) {
            $source = \Illuminate\Support\Str::limit($row->purpose, 50);
        }

        return [
            'item_id' => $row->item_id,
            'vessel_id' => $row->vessel_id,
            'moved_on' => $row->moved_on,
            'vessel' => $row->vessel_name,
            'name' => $row->name,
            'type' => StockMovement::TYPES[$row->type] ?? ucfirst($row->type),
            'consumption_type' => $row->consumption_type ? (StockConsumption::TYPES[$row->consumption_type] ?? $row->consumption_type) : null,
            'qty' => (int) $row->qty,
            'balance_after' => (int) $row->balance_after,
            'department' => $row->department,
            'source' => $source,
            'order_id' => $row->order_id,
            'remarks' => $row->remarks,
            'user' => $row->user_name,
        ];
    }

    /** value => label for the Type filter dropdown. */
    private function typeOptions(): array
    {
        $options = [
            StockMovement::TYPE_RECEIPT => 'Receipt',
            StockMovement::TYPE_OPENING => 'Opening',
            StockMovement::TYPE_ADJUSTMENT => 'Adjustment',
        ];

        foreach (StockConsumption::TYPES as $value => $label) {
            $options[$value] = 'Consumption - '.$label;
        }
        $options[StockConsumption::TYPE_PMS_JOB] = 'Consumption - PMS job';

        return $options;
    }

    private function authorizeViewer(): void
    {
        abort_unless($this->gate->canView(), 403, 'You are not authorised to view inventory.');
    }
}
