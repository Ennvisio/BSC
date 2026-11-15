<?php

namespace App\Http\Controllers;

use App\Imports\VesselEquipmentImport;
use App\Exports\EquipmentTemplateExport;
use App\Vessel;
use App\VesselEquipment;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * A vessel's own Equipment & Maker List - what a Shore Repair service
 * requisition line points at (see ServiceRequisitionController@searchEquipment).
 *
 * Ship-level roles maintain their own vessel's list - the same
 * vessel-is-the-authority reasoning StockController already applies to stock
 * (the crew are the ones who actually know what's fitted and who made it).
 * Super-admin can maintain any vessel's list: they pick the vessel (vessel_id)
 * and every action below then applies to that vessel only.
 */
class EquipmentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $this->authorizeManager();

        // Super-admin picks a vessel (the first one until they choose);
        // everyone else is locked to their own.
        $vessels = null;
        if ($this->isSuperAdmin()) {
            $vessels = Vessel::where('status', true)->orderBy('name')->get();
            if (! $request->filled('vessel_id') && $vessels->isNotEmpty()) {
                $request->merge(['vessel_id' => $vessels->first()->id]);
            }
        }

        $vesselId = $this->vesselId($request);
        $vessel = Vessel::find($vesselId);

        $equipment = VesselEquipment::where('vessel_id', $vesselId)
            ->where('status', true)
            ->orderBy('name')
            ->get();

        return view('layouts.equipment', compact('equipment', 'vessel', 'vessels'));
    }

    /** Manual add of one row. */
    public function store(Request $request)
    {
        $this->authorizeManager();

        $request->validate([
            'name' => 'required|string|max:255',
            'maker' => 'nullable|string|max:255',
        ]);

        $vesselId = $this->vesselId($request);

        $exists = VesselEquipment::where('vessel_id', $vesselId)
            ->where('name', trim($request->name))
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'This equipment is already on the list.'], 422);
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

        $vesselId = $this->vesselId($request);
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

        $vesselId = $this->vesselId($request);
        $equipment = VesselEquipment::where('id', $request->id)->where('vessel_id', $vesselId)->first();

        if (! $equipment) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        // Soft-disable rather than delete outright - a past Shore Repair
        // requisition may already reference this row by id (once that
        // persistence exists), and hard-deleting would orphan it the same
        // way items do (see the vessel_items/items orphan cleanup earlier).
        $equipment->update(['status' => false, 'updated_by' => auth()->id()]);

        return response()->json(['message' => 'Equipment removed from the list.']);
    }

    public function template(Request $request)
    {
        $this->authorizeManager();

        $vessel = Vessel::findOrFail($this->vesselId($request));
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

        $vesselId = $this->vesselId($request);
        $import = new VesselEquipmentImport($vesselId, auth()->id());

        Excel::import($import, $request->file('equipment_file'));

        $message = "Equipment upload finished: {$import->upsertedCount} added/updated, "
            ."{$import->skippedCount} blank rows skipped, {$import->failedCount} failed, "
            ."out of {$import->rowCount} rows.";

        if ($import->failedCount > 0) {
            $message .= ' First error: '.($import->errors[0] ?? '');
        }

        return redirect()->route('equipment.index', $this->isSuperAdmin() ? ['vessel_id' => $vesselId] : [])->with('message', $message);
    }

    private function isSuperAdmin(): bool
    {
        return (auth()->user()->role->role ?? null) === 'super-admin';
    }

    /**
     * Which vessel this request is about: a ship-level user's own, always;
     * super-admin's is the one they chose (vessel_id), which has to exist.
     */
    private function vesselId(Request $request): int
    {
        if ($this->isSuperAdmin()) {
            $id = (int) $request->input('vessel_id');
            abort_unless($id > 0 && Vessel::whereKey($id)->exists(), 422, 'Choose a vessel first.');

            return $id;
        }

        return (int) auth()->user()->role->vessel_id;
    }

    private function isManager(): bool
    {
        // Any ship-level role with a vessel (Master, Chief Engineer, Chief
        // Officer, Second Engineer, Operator) - always scoped to their own vessel.
        return (auth()->user()->role->user_type ?? null) === 'ship'
            && ! empty(auth()->user()->role->vessel_id);
    }

    private function authorizeManager(): void
    {
        abort_unless($this->isSuperAdmin() || $this->isManager(), 403, 'Only super-admin or a vessel\'s own crew can maintain an equipment list.');
    }
}
