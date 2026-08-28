<?php

namespace App\Http\Controllers;

use App\Imports\ItemCatalogImport;
use App\ItemImport;
use App\Vessel;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ItemImportController extends Controller
{
    public function create()
    {
        if ($this->isShipUser()) {
            return view('layouts.item-import', [
                'vessels' => null,
                'lockedVessel' => auth()->user()->role->vessel,
            ]);
        }

        return view('layouts.item-import', [
            'vessels' => Vessel::orderBy('name')->where('status', true)->get(),
            'lockedVessel' => null,
        ]);
    }

    public function store(Request $request)
    {
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
            'catalog_file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        $file = $request->file('catalog_file');
        $import = new ItemCatalogImport($vesselId, auth()->user()->name);

        $importRecord = ItemImport::create([
            'vessel_id' => $vesselId,
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
        $imports = ItemImport::with(['vessel', 'uploadedBy'])
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
