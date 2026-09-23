<?php

namespace App\Http\Controllers;

use App\Imports\VesselEquipmentImport;
use App\Exports\EquipmentTemplateExport;
use App\VesselEquipment;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * A vessel's own Equipment & Maker List - what a Shore Repair service
 * requisition line points at (see ServiceRequisitionController@searchEquipment).
 *
 * Master/Chief Engineer only, and only for their own vessel - the same
 * vessel-is-the-authority reasoning StockController already applies to stock
 * (they're the ones who actually know what's fitted and who made it), widened
 * to Chief Engineer as well since a lot of this list is engine-room kit.
 */
class EquipmentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $this->authorizeManager();

        $equipment = VesselEquipment::where('vessel_id', auth()->user()->role->vessel_id)
            ->orderBy('name')
            ->get();

        return view('layouts.equipment', compact('equipment'));
    }

    /** Manual add of one row. */
    public function store(Request $request)
    {
        $this->authorizeManager();

        $request->validate([
            'name' => 'required|string|max:255',
            'maker' => 'nullable|string|max:255',
        ]);

        $vesselId = auth()->user()->role->vessel_id;

        $exists = VesselEquipment::where('vessel_id', $vesselId)
            ->where('name', trim($request->name))
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'This equipment is already on your list.'], 422);
        }

        VesselEquipment::create([
            'vessel_id' => $vesselId,
            'name' => trim($request->name),
            'maker' => $request->maker ? trim($request->maker) : null,
            'status' => true,
            'created_by' => auth()->id(),
        ]);

        return response()->json(['message' => 'Equipment added.']);
    }

    public function update(Request $request)
    {
        $this->authorizeManager();

        $request->validate([
            'id' => 'required|exists:vessel_equipment,id',
            'name' => 'required|string|max:255',
            'maker' => 'nullable|string|max:255',
        ]);

        $vesselId = auth()->user()->role->vessel_id;
        $equipment = VesselEquipment::where('id', $request->id)->where('vessel_id', $vesselId)->firstOrFail();

        $duplicate = VesselEquipment::where('vessel_id', $vesselId)
            ->where('name', trim($request->name))
            ->where('id', '!=', $equipment->id)
            ->exists();

        if ($duplicate) {
            return response()->json(['message' => 'Another item already uses that name.'], 422);
        }

        $equipment->update([
            'name' => trim($request->name),
            'maker' => $request->maker ? trim($request->maker) : null,
            'updated_by' => auth()->id(),
        ]);

        return response()->json(['message' => 'Equipment updated.']);
    }

    public function destroy(Request $request)
    {
        $this->authorizeManager();

        $vesselId = auth()->user()->role->vessel_id;
        $equipment = VesselEquipment::where('id', $request->id)->where('vessel_id', $vesselId)->first();

        if (! $equipment) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        // Soft-disable rather than delete outright - a past Shore Repair
        // requisition may already reference this row by id (once that
        // persistence exists), and hard-deleting would orphan it the same
        // way items do (see the vessel_items/items orphan cleanup earlier).
        $equipment->update(['status' => false, 'updated_by' => auth()->id()]);

        return response()->json(['message' => 'Equipment removed from your list.']);
    }

    public function template()
    {
        $this->authorizeManager();

        $vessel = auth()->user()->role->vessel;
        $filename = 'equipment-'.str_replace(' ', '-', strtolower($vessel->name)).'.xlsx';

        return Excel::download(new EquipmentTemplateExport($vessel->id), $filename);
    }

    public function upload(Request $request)
    {
        $this->authorizeManager();

        set_time_limit(300);

        $request->validate([
            'equipment_file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        $vesselId = auth()->user()->role->vessel_id;
        $import = new VesselEquipmentImport($vesselId, auth()->id());

        Excel::import($import, $request->file('equipment_file'));

        $message = "Equipment upload finished: {$import->upsertedCount} added/updated, "
            ."{$import->skippedCount} blank rows skipped, {$import->failedCount} failed, "
            ."out of {$import->rowCount} rows.";

        if ($import->failedCount > 0) {
            $message .= ' First error: '.($import->errors[0] ?? '');
        }

        return redirect()->route('equipment.index')->with('message', $message);
    }

    private function isManager(): bool
    {
        return in_array(auth()->user()->role->role ?? null, ['master', 'chief-engineer'], true)
            && ! empty(auth()->user()->role->vessel_id);
    }

    private function authorizeManager(): void
    {
        abort_unless($this->isManager(), 403, 'Only the Master or Chief Engineer can maintain the equipment list.');
    }
}
