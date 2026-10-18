<?php

namespace App\Http\Controllers;

use App\Exports\PmsJobsExport;
use App\Http\Controllers\Concerns\PmsAccess;
use App\PmsComponent;
use App\PmsCondition;
use App\PmsCounterReading;
use App\PmsJob;
use App\PmsJobType;
use App\PmsLocation;
use App\Rank;
use App\Services\PmsJobScheduler;
use App\Services\PmsPartsService;
use App\Vessel;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

/**
 * The jobs list for a vessel and the job dialog's actions. Every action is
 * handed straight to PmsJobScheduler - nothing here decides a due date.
 */
class PmsJobController extends Controller
{
    use PmsAccess;

    const FILTERS = ['all', 'overdue', 'duesoon', 'approval', 'critical'];

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $this->authorizePmsView();
        $vessel = $this->pmsVessel($request);

        $components = PmsComponent::where('vessel_id', $vessel->id)
            ->orderBy('sort_order')->orderBy('name')
            ->get(['id', 'name', 'parent_id', 'location_id', 'has_counter', 'is_critical', 'is_class_relevant']);
        $ids = $components->pluck('id')->flip();

        return view('layouts.pms-jobs', [
            'vessel' => $vessel,
            'vessels' => $this->pmsIsShip() ? collect() : Vessel::where('status', true)->orderBy('name')->get(['id', 'name']),
            'canEdit' => $this->canEditPmsVessel($vessel->id),
            'canApprove' => $this->canApprovePmsJobs(),
            'locations' => PmsLocation::ordered()->get(),
            'rootsByLocation' => $components->filter(fn ($c) => ! $c->parent_id || ! isset($ids[$c->parent_id]))->groupBy('location_id'),
            'childrenByParent' => $components->filter(fn ($c) => $c->parent_id && isset($ids[$c->parent_id]))->groupBy('parent_id'),
            'ranks' => Rank::ordered()->get(['id', 'code', 'name']),
            'jobTypes' => PmsJobType::orderBy('name')->get(['id', 'name', 'abbreviation']),
            'conditions' => PmsCondition::orderBy('id')->get(['id', 'name']),
            'manufacturers' => \App\Manufacturer::orderBy('name')->get(['id', 'name']),
            'defaultConditionId' => PmsCondition::defaultId(),
            'selectedComponent' => $ids->has($request->query('component_id')) ? $request->query('component_id') : null,
        ]);
    }

    /** Every open job on the vessel; the page filters, searches and counts them client-side. */
    public function data(Request $request)
    {
        $this->authorizePmsView();
        $vessel = $this->pmsVessel($request);

        return response()->json(['data' => $this->rows($vessel->id)]);
    }

    public function export(Request $request)
    {
        $this->authorizePmsView();
        $vessel = $this->pmsVessel($request);

        $rows = $this->rows($vessel->id);

        $filter = in_array($request->query('filter'), self::FILTERS, true) ? $request->query('filter') : 'all';
        $rows = $rows->filter(fn ($r) => match ($filter) {
            'overdue' => $r['status'] === PmsJob::OVERDUE,
            'duesoon' => $r['status'] === PmsJob::DUE_SOON,
            'approval' => $r['status'] === PmsJob::WAITING_APPROVAL,
            'critical' => $r['critical'],
            default => true,
        });

        if ($request->filled('component_id')) {
            $component = PmsComponent::where('vessel_id', $vessel->id)->find($request->query('component_id'));
            if ($component) {
                $scope = array_flip(array_merge([$component->id], $component->descendantIds()));
                $rows = $rows->filter(fn ($r) => isset($scope[$r['component_id']]));
            }
        }

        $q = mb_strtolower(trim((string) $request->query('q')));
        if ($q !== '') {
            $rows = $rows->filter(fn ($r) => str_contains($r['search'], $q));
        }

        $filename = 'PMS Jobs - '.preg_replace('/[^A-Za-z0-9 _-]/', '', $vessel->name).' - '.now()->format('Y-m-d').'.xlsx';

        return Excel::download(new PmsJobsExport($vessel, $rows->values(), $filter), $filename);
    }

    /** The job dialog: details, checklist, history and what this user may do with it. */
    public function show(PmsJob $job, PmsJobScheduler $scheduler, PmsPartsService $parts)
    {
        $this->authorizePmsVesselView($job->vessel_id);

        $job->load([
            'definition.intervals', 'definition.checklistItems', 'definition.jobType', 'definition.rank',
            'component.location', 'component.parent', 'doneByRank', 'conditionAfter', 'completedBy:id,name', 'approvedBy:id,name',
        ]);
        $definition = $job->definition;
        $meter = $scheduler->meter($job->component);
        $canEdit = $this->canEditPmsVessel($job->vessel_id);
        // Parts whose requisition no longer holds them are free again.
        $parts->releaseStaleLinks([$job->id]);

        $history = PmsJob::where('job_definition_id', $definition->id)
            ->where('id', '!=', $job->id)
            ->whereIn('status', [PmsJob::COMPLETED, PmsJob::WAITING_APPROVAL, PmsJob::CANCELLED])
            ->with(['doneByRank:id,code', 'conditionAfter:id,name', 'completedBy:id,name', 'approvedBy:id,name'])
            ->orderByRaw('completed_at is null')->orderByDesc('completed_at')->orderByDesc('updated_at')
            ->get();

        return response()->json([
            'job' => $this->jobPayload($job),
            'definition' => [
                'id' => $definition->id,
                'name' => $definition->name,
                'type' => $definition->jobType->name ?? null,
                'rank' => $definition->rank ? $definition->rank->code.' - '.$definition->rank->name : null,
                'rank_id' => $definition->rank_id,
                'performed_by' => $definition->performed_by,
                'perform_when' => $definition->perform_when,
                'frequency' => $definition->is_single
                    ? 'Single job'
                    : $definition->intervals->map->label()->implode(' or ').($definition->intervals->count() > 1 ? ', whichever first' : ''),
                'uses_rh' => $definition->usesRunningHours(),
                'requires_approval' => $definition->requires_approval,
                'is_critical' => $definition->is_critical || $job->component->is_critical,
                'is_class_relevant' => $definition->is_class_relevant,
                'is_safety_relevant' => $definition->is_safety_relevant,
                'class_code' => $definition->class_code,
                'internal_code' => $definition->internal_code,
                'staff_needed' => $definition->staff_needed,
                'time_needed' => $this->timeNeeded($definition->hours_needed, $definition->minutes_needed),
                'description' => $definition->description,
                'checklist' => $definition->checklistItems->map->only(['id', 'text'])->values(),
                'is_active' => $definition->is_active && ! $definition->trashed(),
            ],
            'component' => [
                'id' => $job->component->id,
                'name' => $job->component->name,
                'condition_id' => $job->component->condition_id,
                'path' => collect([$job->component->location->name ?? null, $job->component->parent->name ?? null, $job->component->name])->filter()->implode(' › '),
                'current_rh' => $meter['value'] ?? null,
                'reading_date' => isset($meter['date']) ? $meter['date']->format('d M Y') : null,
            ],
            'history' => $history->map(fn ($h) => $this->jobPayload($h)),
            'parts' => $job->requiredParts()->with(['order', 'manufacturer:id,name'])->get()->map(fn ($p) => [
                'id' => $p->id, 'item_id' => $p->item_id, 'part_name' => $p->part_name, 'article_no' => $p->article_no,
                'part_no' => $p->part_no, 'drawing_no' => $p->drawing_no, 'manufacturer_id' => $p->manufacturer_id,
                'manufacturer' => $p->manufacturer->name ?? null, 'quantity' => $p->quantity, 'unit' => $p->unit,
                'est_delivery_weeks' => $p->est_delivery_weeks, 'est_price' => $p->est_price !== null ? (float) $p->est_price : null,
                'order' => $parts->requisitionInfo($p->order),
            ])->values(),
            'used_parts' => $job->usedParts()->with('item:id,name,unit')->get()->map(fn ($u) => [
                'name' => $u->item->name ?? '', 'unit' => $u->item->unit ?? '', 'quantity' => $u->quantity,
            ])->values(),
            'can' => [
                'parts_edit' => $canEdit && $job->isOpen(),
                'requisition' => $this->canRequisitionForVessel($job->vessel_id) && $job->isOpen(),
                'complete' => $canEdit && $job->isActive(),
                'reschedule' => $canEdit && $job->isActive(),
                'approve' => $this->canApprovePmsJobs() && $job->status === PmsJob::WAITING_APPROVAL,
                'edit_definition' => $canEdit && ! $definition->trashed(),
            ],
        ]);
    }

    public function complete(Request $request, PmsJob $job, PmsPartsService $parts)
    {
        $this->authorizePmsEdit($job->vessel_id);

        // Every checklist item has to be ticked off before the job can be
        // signed as done. The ticks themselves aren't stored.
        $items = $job->definition->checklistItems()->pluck('id')->map(fn ($id) => (string) $id)->sort()->values()->all();
        $ticked = collect((array) $request->input('checklist', []))->map(fn ($id) => (string) $id)->unique()->sort()->values()->all();
        if ($items && $items !== $ticked) {
            throw ValidationException::withMessages(['checklist' => 'Tick every checklist item before completing the job.']);
        }

        // Completion and the parts taken from stock stand or fall together.
        $job = $parts->completeWithUsedParts($job, $request->only([
            'completed_at', 'done_by_rank_id', 'condition_after_id', 'remark', 'overdue_reason', 'overdue_remark', 'rh_at_completion',
        ]), (array) $request->input('used_parts', []), auth()->id());

        return response()->json([
            'message' => $job->status === PmsJob::WAITING_APPROVAL ? 'Job completed - waiting for office approval.' : 'Job completed.',
        ]);
    }

    public function reschedule(Request $request, PmsJob $job, PmsJobScheduler $scheduler)
    {
        $this->authorizePmsEdit($job->vessel_id);

        $job = $scheduler->reschedule($job, $request->only(['due_date', 'reason', 'remark', 'due_running_hours']));

        return response()->json(['message' => 'Job rescheduled to '.$job->due_date->format('d M Y').'.']);
    }

    public function approve(PmsJob $job, PmsJobScheduler $scheduler)
    {
        abort_unless($this->canApprovePmsJobs(), 403, 'Only the office can approve PMS jobs.');
        $this->authorizePmsVesselView($job->vessel_id);

        $scheduler->approve($job, auth()->id());

        return response()->json(['message' => 'Job approved.']);
    }

    // ------------------------------------------------------------------

    /** One row per open job, shaped for both the table and the export. */
    private function rows(int $vesselId): Collection
    {
        $jobs = PmsJob::where('pms_jobs.vessel_id', $vesselId)
            ->whereIn('status', PmsJob::OPEN_STATUSES)
            ->with(['definition:id,name,job_type_id,rank_id,is_critical,is_single', 'definition.jobType:id,name,abbreviation',
                'definition.rank:id,code,name', 'component:id,name,parent_id,has_counter,is_critical'])
            ->get();

        $definitionIds = $jobs->pluck('job_definition_id')->unique()->values();
        $lastDone = PmsJob::whereIn('job_definition_id', $definitionIds)
            ->whereIn('status', [PmsJob::COMPLETED, PmsJob::WAITING_APPROVAL])
            ->groupBy('job_definition_id')
            ->pluck(DB::raw('max(completed_at)'), 'job_definition_id');

        $counterIds = $jobs->pluck('component')->filter(fn ($c) => $c && $c->has_counter)->pluck('id')->unique()->values();
        $currentRh = $this->latestReadings($counterIds);

        $parents = PmsComponent::where('vessel_id', $vesselId)->pluck('name', 'id');

        return $jobs->map(function (PmsJob $job) use ($lastDone, $currentRh, $parents) {
            $definition = $job->definition;
            $component = $job->component;
            $rank = $definition->rank;
            $type = $definition->jobType;
            $parentName = $component->parent_id ? ($parents[$component->parent_id] ?? null) : null;
            $last = $lastDone[$job->job_definition_id] ?? null;

            return [
                'id' => $job->id,
                'rank' => $rank->code ?? '',
                'rank_name' => $rank->name ?? '',
                'name' => $definition->name,
                'component_id' => $component->id,
                'component' => $component->name,
                'component_parent' => $parentName,
                'type' => $type ? ($type->abbreviation ?: $type->name) : '',
                'type_name' => $type->name ?? '',
                'due_date' => $job->due_date?->toDateString(),
                'due_running_hours' => $job->due_running_hours !== null ? (float) $job->due_running_hours : null,
                'current_rh' => $currentRh[$component->id] ?? null,
                'last_done' => $last ? substr((string) $last, 0, 10) : null,
                'status' => $job->status,
                'rescheduled' => (bool) $job->reschedule_reason,
                'critical' => (bool) ($definition->is_critical || $component->is_critical),
                'search' => mb_strtolower(implode(' ', array_filter([
                    $definition->name, $component->name, $parentName, $rank->code ?? null, $rank->name ?? null, $type->name ?? null,
                ]))),
            ];
        })->sortBy(fn ($r) => [$r['due_date'] === null ? 1 : 0, $r['due_date'] ?? '', $r['name']])->values();
    }

    /** component id => latest meter value (on or before today). */
    private function latestReadings(Collection $componentIds): array
    {
        if ($componentIds->isEmpty()) {
            return [];
        }

        $tomorrow = now()->addDay()->toDateString();
        $latest = PmsCounterReading::whereIn('component_id', $componentIds)
            ->where('reading_date', '<', $tomorrow)
            ->groupBy('component_id')
            ->pluck(DB::raw('max(reading_date)'), 'component_id');

        $values = [];
        foreach ($latest as $componentId => $date) {
            $values[$componentId] = (float) PmsCounterReading::where('component_id', $componentId)
                ->where('reading_date', $date)->value('value');
        }

        return $values;
    }

    private function jobPayload(PmsJob $job): array
    {
        return [
            'id' => $job->id,
            'status' => $job->status,
            'due_date' => $job->due_date?->toDateString(),
            'original_due_date' => $job->original_due_date?->toDateString(),
            'warning_date' => $job->warning_date?->toDateString(),
            'due_running_hours' => $job->due_running_hours !== null ? (float) $job->due_running_hours : null,
            'completed_at' => $job->completed_at?->toDateString(),
            'done_by' => $job->doneByRank ? $job->doneByRank->code : null,
            'rh_at_completion' => $job->rh_at_completion !== null ? (float) $job->rh_at_completion : null,
            'condition_after' => $job->conditionAfter->name ?? null,
            'remark' => $job->remark,
            'overdue_reason' => $job->overdue_reason,
            'overdue_remark' => $job->overdue_remark,
            'reschedule_reason' => $job->reschedule_reason,
            'reschedule_remark' => $job->reschedule_remark,
            'completed_by' => $job->completedBy->name ?? null,
            'approved_by' => $job->approvedBy->name ?? null,
            'approved_at' => $job->approved_at?->format('d M Y H:i'),
        ];
    }

    private function timeNeeded(?int $hours, ?int $minutes): ?string
    {
        if (! $hours && ! $minutes) {
            return null;
        }

        return trim(($hours ? $hours.' h ' : '').($minutes ? $minutes.' min' : ''));
    }
}
