<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PmsAccess;
use App\PmsComponent;
use App\PmsCounterReading;
use App\Services\PmsCounterReadingService;
use App\Services\PmsJobScheduler;
use Illuminate\Http\Request;

/**
 * Running-hour meter readings for one component (has_counter only). The
 * rules for a new reading live in PmsCounterReadingService, shared with the
 * vessel-wide Counters page.
 */
class PmsCounterReadingController extends Controller
{
    use PmsAccess;

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(PmsComponent $component)
    {
        $this->authorizePmsVesselView($component->vessel_id);

        $component->load(['vessel:id,name', 'location:id,name', 'parent:id,name']);
        $readings = $component->counterReadings()->with('enteredBy:id,name')->get();

        // Hours run since the previous reading - what a reader actually wants
        // to see next to a raw meter total.
        $ascending = $readings->sortBy('reading_date')->values();
        $deltas = [];
        foreach ($ascending as $i => $reading) {
            $deltas[$reading->id] = $i > 0 ? (float) $reading->value - (float) $ascending[$i - 1]->value : null;
        }

        return view('layouts.pms-counter-readings', [
            'component' => $component,
            'readings' => $readings,
            'deltas' => $deltas,
            'canEdit' => $this->canEditPmsVessel($component->vessel_id),
        ]);
    }

    public function store(Request $request, PmsComponent $component, PmsCounterReadingService $readings)
    {
        $this->authorizePmsEdit($component->vessel_id);

        // Same rules as the Counters page - see PmsCounterReadingService.
        $readings->record($component, $request->input('reading_date'), $request->input('value'), auth()->id());

        return response()->json(['message' => 'Reading saved.']);
    }

    public function destroy(PmsCounterReading $reading, PmsJobScheduler $scheduler)
    {
        $component = PmsComponent::withTrashed()->findOrFail($reading->component_id);
        $this->authorizePmsEdit($component->vessel_id);

        $reading->delete();
        $scheduler->refreshComponent($component);

        return response()->json(['message' => 'Reading deleted.']);
    }
}
