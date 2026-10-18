<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PmsAccess;
use App\PmsComponent;
use App\PmsJobDefinition;
use App\PmsJobInterval;
use App\PmsRequiredPart;
use App\Services\PmsJobScheduler;
use App\Services\PmsPartsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Add / edit a component's job definitions. Saving only stores what the
 * officer entered; which job that produces, and when it's due, is entirely
 * PmsJobScheduler::definitionSaved()'s call.
 */
class PmsJobDefinitionController extends Controller
{
    use PmsAccess;

    public function __construct()
    {
        $this->middleware('auth');
    }

    /** JSON for the edit modal, with the jobs this definition has generated so far. */
    public function show(PmsJobDefinition $definition)
    {
        $this->authorizePmsVesselView($definition->vessel_id);

        $definition->load(['intervals', 'checklistItems', 'requiredParts', 'component:id,name,has_counter,is_critical,is_class_relevant']);
        $jobs = $definition->jobs()->with('doneByRank:id,code')
            ->orderByRaw('completed_at is null desc')->orderByDesc('completed_at')->orderByDesc('created_at')
            ->get();

        return response()->json([
            'definition' => array_merge($definition->only($definition->getFillable()), [
                'id' => $definition->id,
                'due_date' => $definition->due_date?->toDateString(),
                'start_date' => $definition->start_date?->toDateString(),
                'due_running_hours' => $definition->due_running_hours !== null ? (float) $definition->due_running_hours : null,
                'intervals' => $definition->intervals->map->only(['every', 'period'])->values(),
                'checklist' => $definition->checklistItems->pluck('text')->values(),
                'parts' => $definition->requiredParts->map->only([
                    'item_id', 'part_name', 'article_no', 'part_no', 'drawing_no', 'manufacturer_id', 'quantity', 'unit', 'est_delivery_weeks', 'est_price',
                ])->values(),
            ]),
            'component' => $definition->component,
            'jobs' => $jobs->map(fn ($job) => [
                'id' => $job->id,
                'status' => $job->status,
                'due_date' => $job->due_date?->format('d M Y'),
                'original_due_date' => $job->original_due_date?->format('d M Y'),
                'due_running_hours' => $job->due_running_hours !== null ? (float) $job->due_running_hours : null,
                'completed_at' => $job->completed_at?->format('d M Y'),
                'done_by' => $job->doneByRank->code ?? null,
            ]),
            'can_edit' => $this->canEditPmsVessel($definition->vessel_id),
        ]);
    }

    public function store(Request $request, PmsComponent $component, PmsJobScheduler $scheduler)
    {
        $this->authorizePmsEdit($component->vessel_id);

        $definition = new PmsJobDefinition(['vessel_id' => $component->vessel_id, 'component_id' => $component->id]);
        $job = $this->save($definition, $request, $scheduler);

        return response()->json([
            'message' => 'Job added.'.($job?->due_date ? ' First due '.$job->due_date->format('d M Y').'.' : ''),
            'id' => $definition->id,
        ]);
    }

    public function update(Request $request, PmsJobDefinition $definition, PmsJobScheduler $scheduler)
    {
        $this->authorizePmsEdit($definition->vessel_id);

        $this->save($definition, $request, $scheduler);

        return response()->json(['message' => $definition->is_active ? 'Job updated.' : 'Job deactivated - its open job was cancelled.']);
    }

    private function save(PmsJobDefinition $definition, Request $request, PmsJobScheduler $scheduler)
    {
        $data = $this->validated($request);

        return DB::transaction(function () use ($definition, $data, $scheduler) {
            $definition->fill(collect($data)->except(['intervals', 'checklist'])->all())->save();

            $definition->intervals()->delete();
            foreach ($data['is_single'] ? [] : $data['intervals'] as $interval) {
                $definition->intervals()->create($interval);
            }

            app(PmsPartsService::class)->syncTemplate($definition, $data['parts']);

            $definition->checklistItems()->delete();
            foreach ($data['checklist'] as $i => $text) {
                $definition->checklistItems()->create(['position' => $i + 1, 'text' => $text]);
            }

            // Throws a ValidationException (rolling all of the above back)
            // if the definition can't be scheduled as entered.
            return $scheduler->definitionSaved($definition->fresh());
        });
    }

    private function validated(Request $request): array
    {
        foreach (['is_single', 'requires_approval', 'is_critical', 'is_class_relevant', 'is_safety_relevant', 'is_active'] as $flag) {
            $request->merge([$flag => $request->boolean($flag)]);
        }
        // A part row nobody filled in is just an unused input.
        $request->merge(['parts' => array_values(array_filter(
            (array) $request->input('parts', []),
            fn ($p) => ! empty($p['item_id']) || trim((string) ($p['part_name'] ?? '')) !== ''
        ))]);
        // Blank checklist rows are just unused inputs, not items.
        $request->merge(['checklist' => array_values(array_filter(
            array_map(fn ($t) => trim((string) $t), (array) $request->input('checklist', [])),
            fn ($t) => $t !== ''
        ))]);

        $single = $request->boolean('is_single');
        // A single job has no intervals; ignore whatever the hidden half of
        // the form posted instead of validating it.
        if ($single) {
            $request->request->remove('intervals');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'job_type_id' => ['required', 'integer', 'exists:pms_job_types,id'],
            'rank_id' => ['required', 'integer', 'exists:ranks,id'],
            'performed_by' => ['required', Rule::in(PmsJobDefinition::PERFORMED_BY)],
            'perform_when' => ['required', Rule::in(PmsJobDefinition::PERFORM_WHEN)],
            'is_single' => ['boolean'],
            'due_type' => [Rule::requiredIf($single), 'nullable', Rule::in(PmsJobDefinition::DUE_TYPES)],
            'due_date' => [Rule::requiredIf($single && $request->input('due_type') === 'Date'), 'nullable', 'date'],
            'due_running_hours' => [Rule::requiredIf($single && $request->input('due_type') === 'RunningHours'), 'nullable', 'numeric', 'min:0', 'max:99999999999.9'],
            'start_date' => ['nullable', 'date'],
            'warning_period' => ['required', 'integer', 'min:0', 'max:65535'],
            'warning_unit' => ['required', Rule::in(PmsJobDefinition::WARNING_UNITS)],
            'internal_code' => ['nullable', 'string', 'max:255'],
            'class_code' => ['nullable', 'string', 'max:255'],
            'staff_needed' => ['nullable', 'integer', 'min:0', 'max:999'],
            'hours_needed' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'minutes_needed' => ['nullable', 'integer', 'min:0', 'max:59'],
            'description' => ['nullable', 'string', 'max:10000'],
            'requires_approval' => ['boolean'],
            'is_critical' => ['boolean'],
            'is_class_relevant' => ['boolean'],
            'is_safety_relevant' => ['boolean'],
            'is_active' => ['boolean'],
            'intervals' => [Rule::requiredIf(! $single), 'nullable', 'array'],
            'intervals.*.every' => ['required', 'integer', 'min:1', 'max:1000000'],
            'intervals.*.period' => ['required', 'distinct', Rule::in(PmsJobInterval::PERIODS)],
            'checklist' => ['array', 'max:100'],
            'checklist.*' => ['string', 'max:255'],
            'parts' => ['array', 'max:200'],
        ] + PmsRequiredPart::rules('parts.*.'), [
            'parts.*.part_name.required_without' => 'Enter the part name, or pick a catalogue item.',
            'parts.*.unit.required_without' => 'Enter the unit of every part.',
            'intervals.required' => 'Add at least one interval.',
            'intervals.*.period.distinct' => 'Each interval needs a different period.',
            'due_date.required' => 'Enter the due date.',
            'due_running_hours.required' => 'Enter the due running hours.',
        ]);

        foreach (['name', 'internal_code', 'class_code', 'description'] as $field) {
            $data[$field] = isset($data[$field]) && trim($data[$field]) !== '' ? trim($data[$field]) : null;
        }

        // Keep only the side of the form that applies.
        if ($single) {
            $data['start_date'] = null;
            $data['intervals'] = [];
            if ($data['due_type'] === 'Date') {
                $data['due_running_hours'] = null;
            } else {
                $data['due_date'] = null;
            }
        } else {
            $data['due_type'] = $data['due_date'] = $data['due_running_hours'] = null;
        }

        return $data;
    }
}
