<?php

namespace App\Http\Controllers;

use App\Category;
use App\Exports\StockTemplateExport;
use App\Imports\VesselStockImport;
use App\Services\StockService;
use App\StockImport;
use App\VesselItem;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Per-vessel stock (ROB) maintenance.
 *
 * Both manual routes in here are Master-only. Stock is a statement about what
 * is physically in the ship's store, so the authority for it sits with the
 * Master on board - not with shore staff, who can't see the store, and not
 * with the officers who raise requisitions against it.
 *
 * The third way stock moves - confirmed receipts topping it up - is automatic
 * and lives in RoleController@approveRequisition.
 */
class StockController extends Controller
{
    private StockService $stock;

    public function __construct(StockService $stock)
    {
        // Auth is wired per-controller in this app, not on the route groups.
        // The Master-only checks below all read auth()->user()->role, which
        // fatals rather than redirecting if a guest ever gets this far.
        $this->middleware('auth');

        $this->stock = $stock;
    }

    public function create()
    {
        $this->authorizeMaster();

        return view('layouts.stock-upload', [
            'vessel' => auth()->user()->role->vessel,
            'categories' => $this->catalogCategories(),
        ]);
    }

    /**
     * Download the fill-in template for one category, pre-filled with the
     * vessel's current figures.
     */
    public function template(Request $request)
    {
        $this->authorizeMaster();

        $request->validate(['category_id' => 'required|exists:categories,id']);

        $vessel = auth()->user()->role->vessel;
        $category = Category::findOrFail($request->category_id);

        $filename = 'stock-'.str_replace(' ', '-', strtolower($vessel->name)).'-'
            .str_replace(' ', '-', strtolower($category->name)).'.xlsx';

        return Excel::download(new StockTemplateExport($vessel->id, (int) $request->category_id), $filename);
    }

    public function store(Request $request)
    {
        $this->authorizeMaster();

        // Same reasoning as the catalog import: a large file's parsing alone
        // can outrun PHP's default 30s limit.
        set_time_limit(300);

        $request->validate([
            'category_id' => 'nullable|exists:categories,id',
            'stock_file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        // The vessel always comes from the Master's own role, never the
        // request - a Master can only ever state their own ship's stock.
        $vesselId = auth()->user()->role->vessel_id;
        $file = $request->file('stock_file');

        $import = new VesselStockImport($vesselId, auth()->user()->id, $this->stock);

        $record = StockImport::create([
            'vessel_id' => $vesselId,
            'category_id' => $request->category_id ?: null,
            'uploaded_by' => auth()->user()->id,
            'filename' => $file->getClientOriginalName(),
            'status' => 'processing',
        ]);

        try {
            Excel::import($import, $file);

            $record->update([
                'status' => $import->failedCount > 0 ? 'completed_with_errors' : 'completed',
                'row_count' => $import->rowCount,
                'updated_count' => $import->updatedCount,
                'skipped_count' => $import->skippedCount,
                'failed_count' => $import->failedCount,
                'error_log' => empty($import->errors) ? null : implode("\n", array_slice($import->errors, 0, 500)),
            ]);

            $message = "Stock upload finished: {$import->updatedCount} updated, "
                ."{$import->skippedCount} left unchanged, {$import->failedCount} failed, "
                ."out of {$import->rowCount} rows.";
        } catch (\Throwable $e) {
            $record->update([
                'status' => 'failed',
                'row_count' => $import->rowCount,
                'updated_count' => $import->updatedCount,
                'skipped_count' => $import->skippedCount,
                'failed_count' => $import->failedCount,
                'error_log' => $e->getMessage(),
            ]);

            $message = 'Stock upload failed: '.$e->getMessage();
        }

        return redirect()->route('stock.history')->with('message', $message);
    }

    public function history()
    {
        $this->authorizeMaster();

        $imports = StockImport::with(['category', 'uploadedBy'])
            ->where('vessel_id', auth()->user()->role->vessel_id)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('layouts.stock-history', compact('imports'));
    }

    /**
     * AJAX: correct one item's stock from the Browse Catalog screen - the
     * small-scale counterpart to the spreadsheet upload.
     */
    public function update(Request $request)
    {
        if (! $this->isMaster()) {
            return response()->json(['message' => 'Only the Master can update stock.'], 403);
        }

        $request->validate([
            'item_id' => 'required|exists:items,id',
            'stock_qty' => 'nullable|numeric|min:0',
            'opening_stock' => 'nullable|numeric|min:0',
            'min_qty' => 'nullable|numeric|min:0',
        ]);

        // Each figure is edited in its own box, so a save carries only the one
        // that changed - but at least one has to be there.
        $values = [];
        foreach (['stock_qty', 'opening_stock', 'min_qty'] as $figure) {
            if ($request->filled($figure)) {
                $values[$figure] = (int) $request->input($figure);
            }
        }

        if (empty($values)) {
            return response()->json(['message' => 'Nothing to update.'], 422);
        }

        $vesselId = auth()->user()->role->vessel_id;

        // A Master can only set stock for items their own vessel carries.
        $carriesItem = VesselItem::where('vessel_id', $vesselId)
            ->where('item_id', $request->item_id)
            ->exists();

        if (! $carriesItem) {
            return response()->json(['message' => 'This item is not in your vessel\'s catalog.'], 422);
        }

        $row = $this->stock->setStock($vesselId, (int) $request->item_id, $values, auth()->user()->id);

        return response()->json([
            'message' => 'Stock updated.',
            'stock_qty' => (int) $row->stock_qty,
            'opening_stock' => $row->opening_stock === null ? null : (int) $row->opening_stock,
            'min_qty' => $row->min_qty === null ? null : (int) $row->min_qty,
            'low' => $row->isLowStock(),
        ]);
    }

    /** Catalog categories a stock sheet can be scoped to. */
    private function catalogCategories()
    {
        return Category::where('is_catalog', true)
            ->where('symbol', '!=', 'IMP')
            ->where('status', true)
            ->orderBy('name')
            ->get();
    }

    private function isMaster(): bool
    {
        return (auth()->user()->role->role ?? null) === 'master'
            && ! empty(auth()->user()->role->vessel_id);
    }

    private function authorizeMaster(): void
    {
        abort_unless($this->isMaster(), 403, 'Only the Master can maintain vessel stock.');
    }
}
