<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PmsAccess;
use App\Item;
use App\Manufacturer;
use App\Order;
use App\PmsJob;
use App\PmsRequiredPart;
use App\Services\PmsPartsService;
use App\Vessel;
use App\VesselItem;
use Illuminate\Http\Request;

/**
 * A job's required parts (add / edit / delete, create a requisition from
 * them) and the vessel-wide list of what its open jobs still need. The
 * requisition it makes is an ordinary draft in the existing wizard; nothing
 * here changes how requisitions are approved or procured.
 */
class PmsRequiredPartController extends Controller
{
    use PmsAccess;

    public function __construct()
    {
        $this->middleware('auth');
    }

    /** All required parts of the vessel's open jobs, grouped by job. */
    public function index(Request $request)
    {
        $this->authorizePmsView();
        $vessel = $this->pmsVessel($request);
        $pending = $request->query('filter') === 'pending';

        $openJobIds = PmsJob::where('vessel_id', $vessel->id)->whereIn('status', PmsJob::OPEN_STATUSES)->pluck('id')->all();
        app(PmsPartsService::class)->releaseStaleLinks($openJobIds);

        $parts = PmsRequiredPart::whereNotNull('pms_required_parts.job_id')
            ->whereIn('job_id', $openJobIds)
            ->when($pending, fn ($q) => $q->whereNull('order_id'))
            ->with(['order:id,req_no,status', 'manufacturer:id,name', 'job.definition:id,name', 'job.component:id,name'])
            ->orderBy('id')
            ->get();

        $groups = $parts->groupBy('job_id')->map(fn ($rows) => ['job' => $rows->first()->job, 'parts' => $rows])
            ->sortBy(fn ($g) => [$g['job']->due_date === null ? 1 : 0, (string) $g['job']->due_date?->toDateString()])
            ->values();

        return view('layouts.pms-parts', [
            'vessel' => $vessel,
            'vessels' => $this->pmsIsShip() ? collect() : Vessel::where('status', true)->orderBy('name')->get(['id', 'name']),
            'groups' => $groups,
            'pending' => $pending,
            'totalParts' => $parts->count(),
            'unrequisitioned' => $parts->whereNull('order_id')->count(),
            'orderLabel' => fn (Order $order) => app(PmsPartsService::class)->requisitionInfo($order),
        ]);
    }

    /** AJAX: search the items catalogue to pick a part from. */
    public function searchItems(Request $request)
    {
        $this->authorizePmsView();

        $term = trim((string) $request->query('q'));
        abort_if(mb_strlen($term) < 2, 422, 'Type at least 2 characters.');

        $items = Item::where('status', true)
            ->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('article_number', 'like', "%{$term}%")
                    ->orWhere('part_number', 'like', "%{$term}%")
                    ->orWhere('drawing_number', 'like', "%{$term}%");
            })
            ->orderBy('name')->limit(25)->get();

        $service = app(PmsPartsService::class);

        return response()->json($items->map(fn ($item) => $service->catalogueFields($item)));
    }

    /** AJAX: this job's vessel's own stock that can be used on the job (stock above zero). */
    public function stockItems(Request $request, PmsJob $job)
    {
        $this->authorizePmsEdit($job->vessel_id);

        $term = trim((string) $request->query('q'));

        $rows = VesselItem::where('vessel_items.vessel_id', $job->vessel_id)
            ->where('vessel_items.stock_qty', '>', 0)
            ->join('items', 'items.id', '=', 'vessel_items.item_id')
            ->when($term !== '', fn ($q) => $q->where(fn ($q2) => $q2->where('items.name', 'like', "%{$term}%")->orWhere('items.article_number', 'like', "%{$term}%")->orWhere('items.part_number', 'like', "%{$term}%")))
            ->orderBy('items.name')->limit(25)
            ->get(['vessel_items.item_id', 'items.name', 'items.article_number', 'items.unit', 'vessel_items.stock_qty']);

        return response()->json($rows->map(fn ($r) => [
            'id' => $r->item_id, 'name' => $r->name, 'article_no' => $r->article_number, 'unit' => $r->unit, 'stock_qty' => (int) $r->stock_qty,
        ]));
    }

    public function store(Request $request, PmsJob $job, PmsPartsService $service)
    {
        $this->authorizePmsEdit($job->vessel_id);
        abort_unless($job->isOpen(), 422, 'This job is already done.');

        $request->validate(PmsRequiredPart::rules(), $this->messages());
        $part = PmsRequiredPart::create($service->attributes($request->all()) + ['job_id' => $job->id]);

        return response()->json(['message' => 'Part added.', 'id' => $part->id]);
    }

    public function update(Request $request, PmsRequiredPart $part, PmsPartsService $service)
    {
        $this->authorizeJobPart($part);
        $this->refuseIfRequisitioned($part, $service);

        $request->validate(PmsRequiredPart::rules(), $this->messages());
        $part->update($service->attributes($request->all()));

        return response()->json(['message' => 'Part updated.']);
    }

    public function destroy(PmsRequiredPart $part, PmsPartsService $service)
    {
        $this->authorizeJobPart($part);
        $this->refuseIfRequisitioned($part, $service);

        $part->delete();

        return response()->json(['message' => 'Part removed.']);
    }

    /** Make a requisition draft of the selected parts and hand over to the requisition wizard. */
    public function requisition(Request $request, PmsJob $job, PmsPartsService $service)
    {
        abort_unless($this->canRequisitionForVessel($job->vessel_id), 403, 'Only this vessel\'s chief officer or second engineer can raise a requisition.');

        $request->validate(['part_ids' => ['required', 'array', 'min:1'], 'part_ids.*' => ['integer']], ['part_ids.required' => 'Select at least one part to requisition.']);

        $order = $service->createRequisition($job, $request->input('part_ids'), auth()->user());

        return response()->json([
            'message' => 'Requisition draft created - complete the justification form to carry on.',
            'redirect' => route('requisition.form', $order),
        ]);
    }

    private function authorizeJobPart(PmsRequiredPart $part): void
    {
        // Template rows belong to a definition and are edited with it.
        abort_unless($part->job_id, 404);
        $this->authorizePmsEdit(PmsJob::findOrFail($part->job_id)->vessel_id);
    }

    /** Take a part off a draft requisition (the draft's line for it is deleted too). */
    public function removeFromRequisition(PmsRequiredPart $part, PmsPartsService $service)
    {
        abort_unless($part->job_id, 404);
        $job = PmsJob::findOrFail($part->job_id);
        abort_unless($this->canRequisitionForVessel($job->vessel_id), 403, 'Only this vessel\'s chief officer or second engineer can change a requisition.');

        $service->removeFromRequisition($part);

        return response()->json(['message' => 'Part taken off the requisition.']);
    }

    private function refuseIfRequisitioned(PmsRequiredPart $part, PmsPartsService $service): void
    {
        // A link to a requisition that no longer holds this part doesn't count.
        $service->releaseStaleLinks([(string) $part->job_id]);
        $part->refresh();

        abort_if($part->order_id, 422, 'This part is already on a requisition, so it can\'t be changed here.');
    }

    private function messages(): array
    {
        return [
            'part_name.required_without' => 'Enter the part name, or pick a catalogue item.',
            'unit.required_without' => 'Enter the unit.',
        ];
    }
}
