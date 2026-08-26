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
        $vessels = Vessel::orderBy('name')->where('status', true)->get();

        return view('layouts.item-import', compact('vessels'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'vessel_id' => 'required|exists:vessels,id',
            'catalog_file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        $file = $request->file('catalog_file');
        $import = new ItemCatalogImport((int) $request->vessel_id, auth()->user()->name);

        $importRecord = ItemImport::create([
            'vessel_id' => $request->vessel_id,
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
            ->orderBy('created_at', 'desc')
            ->get();

        return view('layouts.item-import-history', compact('imports'));
    }
}
