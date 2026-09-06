<?php

namespace App\Http\Controllers;

use App\Category;
use App\Imports\ItemCatalogImport;
use App\ItemImport;
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

    public function history()
    {
        $imports = ItemImport::with(['vessel', 'category', 'uploadedBy'])
            ->when($this->isShipUser(), fn ($q) => $q->where('vessel_id', auth()->user()->role->vessel_id))
            ->orderBy('created_at', 'desc')
            ->get();

        return view('layouts.item-import-history', compact('imports'));
    }

    private function isShipUser(): bool
    {
        return ! empty(auth()->user()->role->user_type) && auth()->user()->role->user_type === 'ship';
    }
}
