<?php

namespace App\Http\Controllers;

use App\Item;
use App\ItemGroup;
use App\Vessel;

class CatalogController extends Controller
{
    public function browse()
    {
        if ($this->isShipUser()) {
            $vessel = auth()->user()->role->vessel;
            $visibleIds = $this->visibleGroupIdsForVessel($vessel->id);

            return view('layouts.catalog-browse', [
                'vessel' => $vessel,
                'groupCount' => count($visibleIds),
                'itemCount' => $vessel->items()->count(),
                'vesselCount' => null,
            ]);
        }

        return view('layouts.catalog-browse', [
            'vessel' => null,
            'groupCount' => ItemGroup::count(),
            'itemCount' => Item::count(),
            'vesselCount' => Vessel::where('status', true)->count(),
        ]);
    }

    /**
     * AJAX: children of a group (or top-level groups when $parentId is null),
     * plus how many items live directly in each one - lets the UI show a
     * leaf (no children, has items) differently from a branch still worth
     * drilling into. For ship users, both the list itself and the counts
     * are scoped to their own vessel's catalog.
     */
    public function children($parentId = null)
    {
        if ($this->isShipUser()) {
            $vesselId = auth()->user()->role->vessel_id;
            $visibleIds = $this->visibleGroupIdsForVessel($vesselId);
            $childrenMap = $this->childrenMap();

            $groups = ItemGroup::where('parent_id', $parentId)
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

        return response()->json($items);
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
    private function visibleGroupIdsForVessel(int $vesselId): array
    {
        $parentMap = $this->parentMap();

        $leafIds = Item::whereHas('vessels', fn ($q) => $q->where('vessel_id', $vesselId))
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
