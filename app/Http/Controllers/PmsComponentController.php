<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PmsAccess;
use App\Manufacturer;
use App\PmsComponent;
use App\PmsComponentCategory;
use App\PmsCondition;
use App\PmsJob;
use App\PmsJobType;
use App\PmsLocation;
use App\Rank;
use App\Vessel;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * A vessel's PMS component tree: grouped by location, then parent -> child.
 * See PmsAccess for who can see and change which vessel's tree.
 */
class PmsComponentController extends Controller
{
    use PmsAccess;

    private const DUPLICATE_NAME = 'A component with this name already exists here.';

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $this->authorizePmsView();
        $vessel = $this->pmsVessel($request);

        $components = PmsComponent::where('vessel_id', $vessel->id)
            ->with(['category:id,name', 'manufacturer:id,name', 'condition:id,name', 'latestReading'])
            ->withCount([
                'jobDefinitions as jobs_count' => fn ($q) => $q->where('is_active', true),
                'jobs as overdue_jobs_count' => fn ($q) => $q->where('status', PmsJob::OVERDUE),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        // A component whose parent isn't in the set (shouldn't happen - a
        // parent can't be deleted while it has children - but cheap to
        // guard) is shown as a root rather than silently disappearing.
        $ids = $components->pluck('id')->flip();
        $childrenByParent = $components
            ->filter(fn ($c) => $c->parent_id && isset($ids[$c->parent_id]))
            ->groupBy('parent_id');
        $rootsByLocation = $components
            ->filter(fn ($c) => ! $c->parent_id || ! isset($ids[$c->parent_id]))
            ->groupBy('location_id');

        $locations = PmsLocation::ordered()->get();

        return view('layouts.pms-components', [
            'vessel' => $vessel,
            'vessels' => $this->pmsIsShip() ? collect() : Vessel::where('status', true)->orderBy('name')->get(['id', 'name']),
            'canEdit' => $this->canEditPmsVessel($vessel->id),
            'locations' => $locations,
            'rootsByLocation' => $rootsByLocation,
            'childrenByParent' => $childrenByParent,
            'total' => $components->count(),
            // Flat list for the Parent picker, in tree order with depth.
            'parentOptions' => $this->flattenTree($locations, $rootsByLocation, $childrenByParent),
            'categories' => PmsComponentCategory::orderBy('name')->get(),
            'manufacturers' => Manufacturer::orderBy('name')->get(['id', 'name']),
            'conditions' => PmsCondition::orderBy('id')->get(),
            'defaultConditionId' => PmsCondition::defaultId(),
            'ranks' => Rank::ordered()->get(['id', 'code', 'name']),
            'jobTypes' => PmsJobType::orderBy('name')->get(['id', 'name', 'abbreviation']),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizePmsView();
        $vessel = $this->pmsVessel($request);
        $this->authorizePmsEdit($vessel->id);

        $data = $this->validated($request, $vessel->id);

        try {
            $component = PmsComponent::create($data + ['vessel_id' => $vessel->id]);
        } catch (QueryException $e) {
            return $this->duplicateNameResponse($e);
        }

        return response()->json(['message' => 'Component added.', 'id' => $component->id]);
    }

    public function update(Request $request, PmsComponent $component)
    {
        $this->authorizePmsEdit($component->vessel_id);

        $data = $this->validated($request, $component->vessel_id, $component);

        try {
            $this->saveUpdate($component, $data);
        } catch (QueryException $e) {
            return $this->duplicateNameResponse($e);
        }

        return response()->json(['message' => 'Component updated.']);
    }

    private function saveUpdate(PmsComponent $component, array $data): void
    {
        DB::transaction(function () use ($component, $data) {
            $locationChanged = (int) $component->location_id !== (int) $data['location_id'];
            $component->update($data);

            // Children always sit in their parent's location - moving a
            // system moves everything under it, or the tree would split
            // across two location groups.
            if ($locationChanged) {
                $descendants = $component->descendantIds();
                if ($descendants) {
                    PmsComponent::whereIn('id', $descendants)->update(['location_id' => $data['location_id']]);
                }
            }
        });
    }

    /** The unique index fired (two saves raced past the check) - same message as the check. */
    private function duplicateNameResponse(QueryException $e)
    {
        if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'pms_components_unique_name')) {
            return response()->json([
                'message' => self::DUPLICATE_NAME,
                'errors' => ['name' => [self::DUPLICATE_NAME]],
            ], 422);
        }

        throw $e;
    }

    public function destroy(PmsComponent $component)
    {
        $this->authorizePmsEdit($component->vessel_id);

        if ($component->children()->exists()) {
            return response()->json([
                'message' => 'Remove or move the components under "'.$component->name.'" first.',
            ], 422);
        }

        if ($component->jobDefinitions()->where('is_active', true)->exists()) {
            return response()->json([
                'message' => 'Deactivate the jobs on "'.$component->name.'" first.',
            ], 422);
        }

        // Soft delete: running-hour readings (and, later, job history) stay
        // attached to it rather than being orphaned.
        $component->delete();

        return response()->json(['message' => 'Component deleted.']);
    }

    private function validated(Request $request, int $vesselId, ?PmsComponent $component = null): array
    {
        foreach (['has_counter', 'is_critical', 'is_class_relevant', 'is_inactive'] as $flag) {
            $request->merge([$flag => $request->boolean($flag)]);
        }

        $data = $request->validate([
            'location_id' => ['required', 'integer', 'exists:pms_locations,id'],
            'parent_id' => [
                'nullable', 'uuid',
                Rule::exists('pms_components', 'id')->where('vessel_id', $vesselId)->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:255'],
            'category_id' => ['required', 'integer', 'exists:pms_component_categories,id'],
            'manufacturer_id' => ['nullable', 'integer', 'exists:manufacturers,id'],
            'serial_no' => ['nullable', 'string', 'max:255'],
            'part_no' => ['nullable', 'string', 'max:255'],
            'class_code' => ['nullable', 'string', 'max:255'],
            'internal_code' => ['nullable', 'string', 'max:255'],
            'installation_date' => ['nullable', 'date', 'before_or_equal:'.Carbon::today()->toDateString()],
            'condition_id' => ['required', 'integer', 'exists:pms_conditions,id'],
            'has_counter' => ['boolean'],
            'is_critical' => ['boolean'],
            'is_class_relevant' => ['boolean'],
            'is_inactive' => ['boolean'],
            'remark' => ['nullable', 'string', 'max:5000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
        ], [
            'parent_id.exists' => 'The parent must be another component on this vessel.',
            'category_id.required' => 'Select a component category.',
            'installation_date.before_or_equal' => 'The installation date can\'t be in the future.',
        ]);

        if (! empty($data['parent_id'])) {
            if ($component) {
                // A component can't sit under itself or anything beneath it.
                if ($data['parent_id'] === $component->id || in_array($data['parent_id'], $component->descendantIds(), true)) {
                    abort(response()->json([
                        'message' => 'A component can\'t be placed under itself or one of its own sub-components.',
                        'errors' => ['parent_id' => ['Choose a different parent.']],
                    ], 422));
                }
            }
            $data['location_id'] = PmsComponent::whereKey($data['parent_id'])->value('location_id');
        }

        foreach (['name', 'type', 'serial_no', 'part_no', 'class_code', 'internal_code', 'remark'] as $field) {
            $data[$field] = isset($data[$field]) && trim($data[$field]) !== '' ? trim($data[$field]) : null;
        }
        $data['parent_id'] = ($data['parent_id'] ?? null) ?: null;
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        // Same rule as the pms_components_unique_name index: unique among
        // live siblings (same vessel, same parent, or both top-level).
        // Checked here so the user gets a proper message; the index is the
        // backstop for two saves racing each other.
        $duplicate = PmsComponent::where('vessel_id', $vesselId)
            ->where('name', $data['name'])
            ->when($data['parent_id'], fn ($q, $parent) => $q->where('parent_id', $parent), fn ($q) => $q->whereNull('parent_id'))
            ->when($component, fn ($q) => $q->where('id', '!=', $component->id))
            ->exists();

        if ($duplicate) {
            abort(response()->json([
                'message' => self::DUPLICATE_NAME,
                'errors' => ['name' => [self::DUPLICATE_NAME]],
            ], 422));
        }

        return $data;
    }

    /** The tree, depth-first, as [id, name, depth, location_id] - for the Parent dropdown. */
    private function flattenTree($locations, $rootsByLocation, $childrenByParent): array
    {
        $out = [];
        $walk = function ($node, int $depth) use (&$walk, &$out, $childrenByParent) {
            $out[] = ['id' => $node->id, 'name' => $node->name, 'depth' => $depth, 'location_id' => $node->location_id];
            foreach ($childrenByParent->get($node->id, []) as $child) {
                $walk($child, $depth + 1);
            }
        };
        foreach ($locations as $location) {
            foreach ($rootsByLocation->get($location->id, []) as $root) {
                $walk($root, 0);
            }
        }

        return $out;
    }
}
