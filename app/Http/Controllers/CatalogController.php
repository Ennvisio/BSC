<?php

namespace App\Http\Controllers;

use App\Item;
use App\ItemGroup;

class CatalogController extends Controller
{
    public function browse()
    {
        $vesselCount = \App\Vessel::where('status', true)->count();
        $groupCount = ItemGroup::count();
        $itemCount = Item::count();

        return view('layouts.catalog-browse', compact('vesselCount', 'groupCount', 'itemCount'));
    }

    /**
     * AJAX: children of a group (or top-level groups when $parentId is null),
     * plus how many items live directly in each one - lets the UI show a
     * leaf (no children, has items) differently from a branch still worth
     * drilling into.
     */
    public function children($parentId = null)
    {
        $groups = ItemGroup::where('parent_id', $parentId)
            ->withCount(['children', 'items'])
            ->orderBy('name')
            ->get(['id', 'parent_id', 'name']);

        return response()->json($groups);
    }

    /**
     * AJAX: items that live directly in one group, with which vessels
     * actually stock each one.
     */
    public function items($groupId)
    {
        $items = Item::where('item_group_id', $groupId)
            ->where('status', true)
            ->with(['vessels' => fn ($q) => $q->select('vessels.id', 'vessels.name')])
            ->orderBy('name')
            ->get([
                'id', 'name', 'article_number', 'unit', 'part_number',
                'drawing_number', 'hs_code', 'manufacturer',
            ]);

        return response()->json($items);
    }
}
