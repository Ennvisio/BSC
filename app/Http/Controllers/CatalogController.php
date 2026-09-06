<?php

namespace App\Http\Controllers;

use App\Category;
use App\Item;
use App\ItemGroup;
use App\Services\StockService;
use App\Vessel;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    /** Same guard HomeController applies: auth is wired per-controller in
     * this app, not on the route groups, so a controller without this is
     * reachable by a guest and fatals on auth()->user()->role. */
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function browse()
    {
        $categories = Category::where('is_catalog', true)->orderBy('name')->get();

        if ($this->isShipUser()) {
            $vessel = auth()->user()->role->vessel;

            return view('layouts.catalog-browse', [
                'vessel' => $vessel,
                'groupCount' => ItemGroup::count(),
                'itemCount' => $vessel->items()->count(),
                'vesselCount' => null,
                'categories' => $categories,
            ]);
        }

        return view('layouts.catalog-browse', [
            'vessel' => null,
            'groupCount' => ItemGroup::count(),
            'itemCount' => Item::count(),
            'vesselCount' => Vessel::where('status', true)->count(),
            'categories' => $categories,
        ]);
    }

    /**
     * AJAX: children of a group (or top-level groups when $parentId is null),
     * plus how many items live directly in each one - lets the UI show a
     * leaf (no children, has items) differently from a branch still worth
     * drilling into. For ship users, both the list itself and the counts
     * are scoped to their own vessel's catalog.
     */
    public function children(Request $request, $parentId = null)
    {
        $categoryId = (int) $request->query('category_id');

        if ($this->isShipUser()) {
            $vesselId = auth()->user()->role->vessel_id;
            $visibleIds = $this->visibleGroupIdsForVessel($vesselId, $categoryId);
            $childrenMap = $this->childrenMap();

            $groups = ItemGroup::where('parent_id', $parentId)
                ->where('category_id', $categoryId)
                ->whereIn('id', $visibleIds)
                ->orderBy('name')
                ->get(['id', 'parent_id', 'name'])
                ->map(function ($group) use ($childrenMap, $visibleIds, $vesselId) {
                    $group->children_count = count(array_intersect($childrenMap[$group->id] ?? [], $visibleIds));
                    $group->items_count = Item::where('item_group_id', $group->id)
                        ->whereHas('vessels', fn ($q) => $q->where('vessel_id', $vesselId))
                        ->count();

                    return $group;
                });

            return response()->json($groups->values());
        }

        $groups = ItemGroup::where('parent_id', $parentId)
            ->where('category_id', $categoryId)
            ->withCount(['children', 'items'])
            ->orderBy('name')
            ->get(['id', 'parent_id', 'name']);

        return response()->json($groups);
    }

    /**
     * AJAX: items that live directly in one group, with which vessels
     * actually stock each one. Ship users only ever see their own vessel's
     * items - never other vessels' items even if this group also holds them.
     */
    public function items($groupId)
    {
        $query = Item::where('item_group_id', $groupId)
            ->where('status', true);

        if ($this->isShipUser()) {
            $vesselId = auth()->user()->role->vessel_id;
            $query->whereHas('vessels', fn ($q) => $q->where('vessel_id', $vesselId));
        }

        $items = $query->with(['vessels' => fn ($q) => $q->select('vessels.id', 'vessels.name')])
            ->orderBy('name')
            ->get([
                'id', 'name', 'article_number', 'unit', 'account_number', 'description',
                'part_number', 'drawing_number', 'hs_code', 'manufacturer',
            ]);

        return response()->json($this->withVesselStock($items));
    }

    /**
     * AJAX: "Suggest Items or Folders" - matches the search term against
     * folder names and item names/article numbers in one category at once,
     * so a user can jump straight to either without knowing which one it is.
     * Same vessel-scoping as children()/items().
     */
    public function search(Request $request)
    {
        $term = trim((string) $request->query('q'));
        $categoryId = (int) $request->query('category_id');

        if ($term === '' || $categoryId === 0) {
            return response()->json(['groups' => [], 'items' => []]);
        }

        $groupsQuery = ItemGroup::where('category_id', $categoryId)
            ->where('name', 'like', "%{$term}%");

        $itemsQuery = Item::where('category_id', $categoryId)
            ->where('status', true)
            ->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('article_number', 'like', "%{$term}%");
            });

        if ($this->isShipUser()) {
            $vesselId = auth()->user()->role->vessel_id;
            $visibleIds = $this->visibleGroupIdsForVessel($vesselId, $categoryId);
            $groupsQuery->whereIn('id', $visibleIds);
            $itemsQuery->whereHas('vessels', fn ($q) => $q->where('vessel_id', $vesselId));
        }

        $groups = $groupsQuery->orderBy('name')->limit(20)->get(['id', 'parent_id', 'name']);
        $items = $itemsQuery->orderBy('name')->limit(20)->get([
            'id', 'name', 'article_number', 'unit', 'part_number', 'drawing_number', 'manufacturer',
        ]);

        return response()->json(['groups' => $groups, 'items' => $this->withVesselStock($items)]);
    }

    /**
     * Attach the current vessel's stock figures to each item, so the picker
     * can show what's already on board before anyone requests more. Only
     * meaningful for ship users - shore staff aren't looking at one vessel.
     *
     * Deliberately one query for the whole page of items rather than one per
     * item, and items with no stock row simply report 0.
     */
    private function withVesselStock($items)
    {
        if (! $this->isShipUser() || $items->isEmpty()) {
            return $items;
        }

        $stock = app(StockService::class)
            ->snapshotFor(auth()->user()->role->vessel_id, $items->pluck('id')->all());

        $canEditStock = (auth()->user()->role->role ?? null) === 'master';

        return $items->map(function ($item) use ($stock, $canEditStock) {
            $item->stock_qty = $stock[$item->id]['stock_qty'] ?? 0;
            $item->opening_stock = $stock[$item->id]['opening_stock'] ?? null;
            $item->min_qty = $stock[$item->id]['min_qty'] ?? null;
            $item->last_supply_qty = $stock[$item->id]['last_supply_qty'] ?? null;
            $item->last_supply_date = $stock[$item->id]['last_supply_date'] ?? null;
            $item->low_stock = $item->min_qty !== null && $item->stock_qty <= $item->min_qty;
            $item->can_edit_stock = $canEditStock;

            return $item;
        });
    }

    private function isShipUser(): bool
    {
        return ! empty(auth()->user()->role->user_type) && auth()->user()->role->user_type === 'ship';
    }

    /**
     * id => parent_id for every item_group, fetched once per request - used
     * to walk ancestor/descendant chains in memory instead of recursive
     * queries (the tree is only ~3-6 levels deep but can have 1000+ nodes).
     */
    private function parentMap(): array
    {
        static $map = null;

        if ($map === null) {
            $map = ItemGroup::pluck('parent_id', 'id')->all();
        }

        return $map;
    }

    /** parent_id => [child ids], the reverse of parentMap(). */
    private function childrenMap(): array
    {
        static $map = null;

        if ($map === null) {
            $map = [];
            foreach ($this->parentMap() as $id => $parentId) {
                $map[$parentId ?? 0][] = $id;
            }
        }

        return $map;
    }

    /**
     * Every item_group that a vessel's own items live in, plus every
     * ancestor of those groups (so the branches leading down to them stay
     * visible while drilling through the tree) - not the whole fleet-wide
     * tree, which is the point of vessel-scoping in the first place.
     */
    private function visibleGroupIdsForVessel(int $vesselId, int $categoryId): array
    {
        $parentMap = $this->parentMap();

        $leafIds = Item::whereHas('vessels', fn ($q) => $q->where('vessel_id', $vesselId))
            ->where('category_id', $categoryId)
            ->whereNotNull('item_group_id')
            ->distinct()
            ->pluck('item_group_id');

        $visible = [];
        foreach ($leafIds as $id) {
            while ($id !== null && ! isset($visible[$id])) {
                $visible[$id] = true;
                $id = $parentMap[$id] ?? null;
            }
        }

        return array_keys($visible);
    }
}
