<?php

namespace App\Http\Controllers;

use App\Category;
use App\ItemGroup;
use App\Vessel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
    private const FLEET_WIDE_ROLES = [
        'gm-srd', 'dgm-srd', 'agm-srd', 'am-srd', 'superintendent-srd',
        'dgm-ssm', 'agm-ssm', 'am-ssm', 'superintendent-ssm', 'super-admin',
    ];

    private const OWN_VESSEL_ROLES = ['master', 'chief-engineer'];

    public function __construct()
    {
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
        return in_array(auth()->user()->role->role ?? null, self::FLEET_WIDE_ROLES, true);
    }

    private function authorizeViewer(): void
    {
        $role = auth()->user()->role->role ?? null;

        abort_unless(
            in_array($role, self::FLEET_WIDE_ROLES, true) || in_array($role, self::OWN_VESSEL_ROLES, true),
            403,
            'You are not authorised to view stock reports.'
        );
    }
}
