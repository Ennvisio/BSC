<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PmsAccess;
use App\PmsComponent;
use App\PmsCounterReading;
use App\PmsLocation;
use App\Services\PmsCounterReadingService;
use App\Services\PmsJobScheduler;
use App\Vessel;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Every running-hour counter on a vessel on one page, for entering the
 * day's (or month's) readings in one go. Same access as a component's own
 * Running Hours page; the rules are PmsCounterReadingService's.
 */
class PmsCounterController extends Controller
{
    use PmsAccess;

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request, PmsJobScheduler $scheduler)
    {
        $this->authorizePmsView();
        $vessel = $this->pmsVessel($request);

        return view('layouts.pms-counters', [
            'vessel' => $vessel,
            'vessels' => $this->pmsIsShip() ? collect() : Vessel::where('status', true)->orderBy('name')->get(['id', 'name']),
            'canEdit' => $this->canEditPmsVessel($vessel->id),
            'counters' => $this->counters($vessel->id, $scheduler),
        ]);
    }

    /** Save the filled rows; report each bad row by component id. */
    public function store(Request $request, PmsCounterReadingService $readings, PmsJobScheduler $scheduler)
    {
        $this->authorizePmsView();
        $vessel = $this->pmsVessel($request);
        $this->authorizePmsEdit($vessel->id);

        $request->validate(['readings' => ['required', 'array', 'max:2000']]);
        $result = $readings->recordMany($vessel->id, (array) $request->input('readings'), auth()->id());

        $saved = count($result['saved']);
        $failed = count($result['errors']);

        return response()->json([
            'message' => $saved === 0 && $failed === 0 ? 'Enter at least one new RH.'
                : trim(($saved ? $saved.' '.str('reading')->plural($saved).' saved.' : '').($failed ? ' '.$failed.' '.str('row')->plural($failed).' not saved - see the messages.' : '')),
            'saved' => array_keys($result['saved']),
            'errors' => $result['errors'],
            // Fresh figures for the saved rows, so the page needn't reload.
            'rows' => $this->counters($vessel->id, $scheduler, array_keys($result['saved']))->keyBy('id'),
        ], $saved === 0 && $failed > 0 ? 422 : 200);
    }

    /** One row per counter: tree path, last reading and average hours per day. */
    private function counters(int $vesselId, PmsJobScheduler $scheduler, ?array $only = null): Collection
    {
        $all = PmsComponent::where('vessel_id', $vesselId)->get(['id', 'name', 'parent_id', 'location_id', 'has_counter', 'is_inactive', 'sort_order'])->keyBy('id');
        $counters = $all->where('has_counter', true)->when($only !== null, fn ($c) => $c->whereIn('id', $only));
        if ($counters->isEmpty()) {
            return collect();
        }

        $ids = $counters->keys()->all();
        $latest = PmsCounterReading::whereIn('component_id', $ids)
            ->where('reading_date', '<', now()->addDay()->toDateString())
            ->orderByDesc('reading_date')->get(['component_id', 'reading_date', 'value'])
            ->unique('component_id')->keyBy('component_id');
        $averages = $scheduler->averagesPerDay($ids);
        $locations = PmsLocation::ordered()->get(['id', 'name']);
        $locationOrder = $locations->pluck('id')->flip();
        $locationNames = $locations->pluck('name', 'id');

        return $counters->map(function ($c) use ($all, $latest, $averages, $locationOrder, $locationNames) {
            $names = [];
            $sortKey = [];
            for ($node = $c, $guard = 0; $node && $guard < 50; $node = $node->parent_id ? $all->get($node->parent_id) : null, $guard++) {
                array_unshift($names, $node->name);
                array_unshift($sortKey, sprintf('%010d', $node->sort_order).mb_strtolower($node->name));
            }
            $last = $latest->get($c->id);

            return [
                'id' => $c->id,
                'name' => $c->name,
                'path' => implode(' › ', array_slice($names, 0, -1)),
                'location' => $locationNames[$c->location_id] ?? '',
                'inactive' => (bool) $c->is_inactive,
                'last_date' => $last?->reading_date->toDateString(),
                'last_value' => $last ? (float) $last->value : null,
                'per_day' => isset($averages[$c->id]) ? round($averages[$c->id], 1) : null,
                'sort' => sprintf('%05d', $locationOrder[$c->location_id] ?? 99999).'|'.implode('|', $sortKey),
            ];
        })->sortBy('sort')->values();
    }
}
