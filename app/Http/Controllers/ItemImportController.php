<?php

namespace App\Http\Controllers;

use App\Category;
use App\Imports\ItemCatalogImport;
use App\Item;
use App\ItemImport;
use App\Services\CatalogService;
use App\Services\StockService;
use App\Vessel;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ItemImportController extends Controller
{
    /** Same guard HomeController applies: auth is wired per-controller in
     * this app, not on the route groups, so a controller without this is
     * reachable by a guest and fatals on auth()->user()->role. */
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function create()
    {
        // "Imported Catalog" (IMP) is deliberately excluded here - it's the
        // legacy placeholder every pre-existing upload went through before
        // category selection existed; new uploads always pick a real one.
        $categories = Category::where('is_catalog', true)->where('symbol', '!=', 'IMP')
            ->orderBy('name')->get();

        if ($this->isShipUser()) {
            return view('layouts.item-import', [
                'vessels' => null,
                'lockedVessel' => auth()->user()->role->vessel,
                'categories' => $categories,
            ]);
        }

        return view('layouts.item-import', [
            'vessels' => Vessel::orderBy('name')->where('status', true)->get(),
            'lockedVessel' => null,
            'categories' => $categories,
        ]);
    }

    public function store(Request $request)
    {
        // A large catalog file's shared-strings XML alone can take PhpSpreadsheet's
        // security scanner past PHP's default 30s limit - request more time here
        // rather than depending on whatever max_execution_time the server happens
        // to be configured with (dev box, XAMPP, production all differ).
        set_time_limit(300);

        // Ship users can only ever import for their own vessel - the vessel_id
        // they're authoritative for comes from their role, never trusted from
        // the request, even though the form also sends it as a hidden field.
        if ($this->isShipUser()) {
            $vesselId = auth()->user()->role->vessel_id;
        } else {
            $request->validate(['vessel_id' => 'required|exists:vessels,id']);
            $vesselId = (int) $request->vessel_id;
        }

        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'catalog_file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        $categoryId = (int) $request->category_id;

        $file = $request->file('catalog_file');
        $import = new ItemCatalogImport($vesselId, $categoryId, auth()->user()->name);

        $importRecord = ItemImport::create([
            'vessel_id' => $vesselId,
            'category_id' => $categoryId,
            'uploaded_by' => auth()->user()->id,
            'filename' => $file->getClientOriginalName(),
            'status' => 'processing',
        ]);

        try {
            Excel::import($import, $file);

            $importRecord->update([
                'status' => $import->failedCount > 0 ? 'completed_with_errors' : 'completed',
                'row_count' => $import->rowCount,
                'imported_count' => $import->importedCount,
                'failed_count' => $import->failedCount,
                'error_log' => empty($import->errors) ? null : implode("\n", $import->errors),
            ]);

            $data = "Catalog import finished: {$import->importedCount} imported, {$import->failedCount} failed, out of {$import->rowCount} rows.";
        } catch (\Throwable $e) {
            $importRecord->update([
                'status' => 'failed',
                'row_count' => $import->rowCount,
                'imported_count' => $import->importedCount,
                'failed_count' => $import->failedCount,
                'error_log' => $e->getMessage(),
            ]);

            $data = 'Catalog import failed: '.$e->getMessage();
        }

        return redirect('/catalog/import/history')->with('message', $data);
    }

    /** Demo sheet with the expected columns and a few example rows. */
    public function sample()
    {
        return Excel::download(new \App\Exports\ItemCatalogSampleExport, 'catalog-import-sample.xlsx');
    }

    public function history()
    {
        $imports = ItemImport::with(['vessel', 'category', 'uploadedBy'])
            ->when($this->isShipUser(), fn ($q) => $q->where('vessel_id', auth()->user()->role->vessel_id))
            ->orderBy('created_at', 'desc')
            ->get();

        return view('layouts.item-import-history', compact('imports'));
    }

    /**
     * AJAX: does an item with this article number already exist? The "Add
     * item manually" tab checks on blur so a typo'd duplicate doesn't
     * silently fork into two items for the same real part - article_number
     * is the bulk import's own match key (see ItemCatalogImport), so
     * manual entry has to respect it just as strictly.
     */
    public function checkArticleNumber(Request $request)
    {
        $articleNumber = trim((string) $request->query('article_number'));

        if ($articleNumber === '') {
            return response()->json(['exists' => false]);
        }

        $item = Item::with(['category:id,name', 'itemGroup:id,path'])
            ->where('article_number', $articleNumber)
            ->first();

        if (! $item) {
            return response()->json(['exists' => false]);
        }

        return response()->json([
            'exists' => true,
            'item' => [
                'id' => $item->id,
                'name' => $item->name,
                'category' => $item->category->name ?? '',
                'group_path' => $item->itemGroup->path ?? '',
            ],
        ]);
    }

    /**
     * The "Add item manually" tab's save - one item instead of a whole
     * spreadsheet, through the exact same CatalogService the bulk import
     * uses (see ItemCatalogImport), so an item created here is
     * indistinguishable from one a spreadsheet would have produced.
     *
     * An article number that already belongs to another item is never
     * silently duplicated NOR silently linked - the caller has to come back
     * with existing_item_id set (see checkArticleNumber() above, and the
     * "Link to this vessel instead" button it drives) before this links
     * rather than creates.
     */
    public function storeManual(Request $request, CatalogService $catalog, StockService $stock)
    {
        if ($this->isShipUser()) {
            $vesselId = auth()->user()->role->vessel_id;
        } else {
            $request->validate(['vessel_id' => 'required|exists:vessels,id']);
            $vesselId = (int) $request->vessel_id;
        }

        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'existing_item_id' => 'nullable|integer|exists:items,id',
            'article_number' => 'required|string|max:255',
            'name' => 'required_without:existing_item_id|string|max:255',
            'item_group_id' => 'nullable|integer|exists:item_groups,id',
            'item_group_path' => 'nullable|string|max:500',
            'unit' => 'nullable|string|max:50',
            'account_number' => 'nullable|string|max:255',
            'part_number' => 'nullable|string|max:255',
            'drawing_number' => 'nullable|string|max:255',
            'hs_code' => 'nullable|string|max:255',
            'manufacturer' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:2000',
            'opening_stock' => 'nullable|integer|min:0',
            'min_qty' => 'nullable|integer|min:0',
        ]);

        $categoryId = (int) $request->category_id;
        $articleNumber = trim($request->article_number);

        if ($request->filled('existing_item_id')) {
            $item = Item::findOrFail($request->existing_item_id);
            // Defends against a stale form (the article number box edited
            // after "Link to this vessel instead" was already clicked).
            abort_unless($item->article_number === $articleNumber, 422, 'That item no longer matches this article number - check and try again.');
        } else {
            $duplicate = Item::where('article_number', $articleNumber)->first();
            if ($duplicate) {
                return response()->json([
                    'message' => 'An item with this article number already exists.',
                    'existing_item' => [
                        'id' => $duplicate->id,
                        'name' => $duplicate->name,
                    ],
                ], 409);
            }

            $groupId = $request->filled('item_group_id') ? (int) $request->item_group_id : null;

            if (! $groupId) {
                $path = trim((string) $request->item_group_path);
                $segments = array_values(array_filter(array_map('trim', explode('->', $path)), fn ($s) => $s !== ''));

                if (empty($segments)) {
                    return response()->json(['message' => 'Pick an existing group, or type a new group path.'], 422);
                }

                $groupMap = $catalog->loadGroupMap();
                $groupId = $catalog->resolveGroupPath($segments, $categoryId, $groupMap);
            }

            $item = $catalog->upsertItem($articleNumber, [
                'item_group_id' => $groupId,
                'category_id' => $categoryId,
                'name' => trim($request->name),
                'unit' => trim((string) $request->unit) ?: 'PC',
                'account_number' => $request->account_number ?: null,
                'part_number' => $request->part_number ?: null,
                'drawing_number' => $request->drawing_number ?: null,
                'hs_code' => $request->hs_code ?: null,
                'manufacturer' => $request->manufacturer ?: null,
                'description' => $request->description ?: null,
            ], auth()->user()->name);
        }

        $catalog->linkToVessel($item->id, $vesselId);

        // Opening stock/min qty only ever go through StockService, and only
        // when the person saving is actually this vessel's own Master - the
        // same authority every other stock write in the app requires (see
        // StockController).
        $isOwnVesselMaster = (auth()->user()->role->role ?? null) === 'master'
            && (int) auth()->user()->role->vessel_id === $vesselId;

        if ($isOwnVesselMaster && ($request->filled('opening_stock') || $request->filled('min_qty'))) {
            $values = [];
            if ($request->filled('opening_stock')) {
                $values['opening_stock'] = (int) $request->opening_stock;
            }
            if ($request->filled('min_qty')) {
                $values['min_qty'] = (int) $request->min_qty;
            }
            $stock->setStock($vesselId, $item->id, $values, auth()->user()->id);
        }

        ItemImport::create([
            'vessel_id' => $vesselId,
            'category_id' => $categoryId,
            'uploaded_by' => auth()->user()->id,
            'filename' => 'Manual entry',
            'status' => 'completed',
            'row_count' => 1,
            'imported_count' => 1,
        ]);

        return response()->json([
            'message' => '"'.$item->name.'" saved to the catalog.',
            'item' => [
                'id' => $item->id,
                'name' => $item->name,
                'article_number' => $item->article_number,
                'item_group_id' => $item->item_group_id,
            ],
            'browse_url' => url('/catalog/browse'),
        ]);
    }

    private function isShipUser(): bool
    {
        return ! empty(auth()->user()->role->user_type) && auth()->user()->role->user_type === 'ship';
    }
}
