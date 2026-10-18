<?php

namespace App\Http\Controllers;

use App\Exports\PmsComponentTemplateExport;
use App\Http\Controllers\Concerns\PmsAccess;
use App\Services\PmsComponentImporter;
use App\Vessel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Upload -> preview -> confirm. The uploaded file is kept (storage/app/
 * pms-imports) between the preview and the confirm, and both read it fresh,
 * so what's confirmed is checked against the tree as it is at that moment.
 * Same access as adding a component by hand.
 */
class PmsComponentImportController extends Controller
{
    use PmsAccess;

    private const DISK_DIR = 'pms-imports';

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function create(Request $request)
    {
        $this->authorizePmsView();
        $vessel = $this->pmsVessel($request);
        $this->authorizePmsEdit($vessel->id);

        return view('layouts.pms-component-import', [
            'vessel' => $vessel,
            'vessels' => $this->pmsIsShip() ? collect() : Vessel::where('status', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function template()
    {
        $this->authorizePmsView();

        return Excel::download(new PmsComponentTemplateExport, 'PMS Component Import Template.xlsx');
    }

    public function upload(Request $request, PmsComponentImporter $importer)
    {
        $this->authorizePmsView();
        $vessel = $this->pmsVessel($request);
        $this->authorizePmsEdit($vessel->id);

        $request->validate(
            ['file' => ['required', 'file', 'max:10240', 'mimes:xlsx,xls']],
            ['file.mimes' => 'Upload an Excel file (.xlsx or .xls).', 'file.required' => 'Choose the Excel file to import.']
        );

        $this->pruneAbandonedUploads();

        $file = $request->file('file');
        $token = (string) Str::uuid();
        $path = $file->storeAs(self::DISK_DIR, $token.'.'.strtolower($file->getClientOriginalExtension()), 'local');

        try {
            // Fail now on a file that isn't an import sheet at all, rather than on the preview.
            $importer->read(Storage::disk('local')->path($path));
        } catch (ValidationException $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }

        $request->session()->put("pms_import.$token", [
            'path' => $path,
            'vessel_id' => $vessel->id,
            'user_id' => auth()->id(),
            'name' => $file->getClientOriginalName(),
        ]);

        return redirect()->route('pms.components.import.preview', $token);
    }

    public function preview(Request $request, string $token, PmsComponentImporter $importer)
    {
        $upload = $this->pendingUpload($request, $token);
        $vessel = Vessel::findOrFail($upload['vessel_id']);

        $analysis = $importer->analyse($vessel->id, $importer->read(Storage::disk('local')->path($upload['path'])));

        return view('layouts.pms-component-import-preview', [
            'vessel' => $vessel,
            'token' => $token,
            'fileName' => $upload['name'],
            'counts' => $analysis['counts'],
            'issues' => collect($analysis['rows'])->filter(fn ($r) => $r['errors'] || $r['warnings'])->values(),
            'rowCount' => count($analysis['rows']),
            'readings' => collect($analysis['rows'])->where('action', PmsComponentImporter::CREATE)->whereNotNull('reading')->count(),
        ]);
    }

    public function confirm(Request $request, string $token, PmsComponentImporter $importer)
    {
        $upload = $this->pendingUpload($request, $token);
        $path = Storage::disk('local')->path($upload['path']);

        try {
            $result = $importer->import($upload['vessel_id'], $importer->read($path), auth()->id());
        } catch (ValidationException $e) {
            return redirect()->route('pms.components.import.preview', $token)->withErrors($e->errors());
        }

        Storage::disk('local')->delete($upload['path']);
        $request->session()->forget("pms_import.$token");

        return redirect()->route('pms.components.index', ['vessel_id' => $upload['vessel_id']])->with('message',
            "Import finished: {$result['created']} components created, {$result['skipped']} skipped"
            .($result['updated'] ? ", {$result['updated']} existing components updated" : '')
            .($result['readings'] ? ", {$result['readings']} running-hour readings added" : '').'.');
    }

    /** Uploads previewed but never confirmed, left over from more than a day ago. */
    private function pruneAbandonedUploads(): void
    {
        $disk = Storage::disk('local');
        foreach ($disk->files(self::DISK_DIR) as $file) {
            if ($disk->lastModified($file) < now()->subDay()->getTimestamp()) {
                $disk->delete($file);
            }
        }
    }

    /** This user's pending upload for $token, re-checked against their current access. */
    private function pendingUpload(Request $request, string $token): array
    {
        $upload = $request->session()->get("pms_import.$token");
        abort_unless($upload && (int) $upload['user_id'] === (int) auth()->id() && Storage::disk('local')->exists($upload['path']), 404,
            'That upload has expired - upload the file again.');
        $this->authorizePmsEdit((int) $upload['vessel_id']);

        return $upload;
    }
}
