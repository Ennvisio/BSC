<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PaginatesRequisitionLists;
use App\Role;
use App\ServiceProcurementStage;
use App\ServiceRequisition;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The service requisition's approval chain, which is the item requisition's
 * minus the SSM leg: Master/Chief Engineer review what their vessel raised
 * and forward it to GM (SRD), who either approves it, rejects it, or
 * delegates it to one named DGM/AGM/AM/Superintendent (SRD) for review - and
 * that delegate reviews it straight back to GM, who then approves.
 *
 * Every action re-checks whose turn it is through
 * ServiceRequisition::hasPendingActionFor(), the same method the buttons are
 * drawn from, so a stale tab or a hand-rolled POST can't act out of turn.
 */
class ServiceRequisitionApprovalController extends Controller
{
    use PaginatesRequisitionLists;

    /** Which delegate role maps to which pair of columns. */
    private const SRD_COLUMNS = [
        'dgm-srd' => ['forwarded_to_dgm_srd', 'dgm_srd_app'],
        'agm-srd' => ['forwarded_to_agm_by_gm_srd', 'agm_app'],
        'am-srd' => ['forwarded_to_am_by_agm_srd', 'ast_m_app'],
        'superintendent-srd' => ['forwarded_to_superintendent_srd', 'superintendent_srd_app'],
    ];

    public function __construct()
    {
        $this->middleware('auth');
    }

    /** Everything this role can currently act on, plus their vessel's own history. */
    public function index()
    {
        $role = auth()->user()->role->role ?? null;
        $vesselId = auth()->user()->role->vessel_id;

        $query = ServiceRequisition::with(['vessel', 'approval', 'items', 'creator'])
            ->where('is_submitted', true)
            ->orderBy('updated_at', 'desc');

        // Ship roles see their own vessel's requisitions; shore roles see the
        // whole fleet's, since they act on any vessel's.
        if (! empty($vesselId) && (auth()->user()->role->user_type ?? null) === 'ship') {
            $query->where('vessel_id', $vesselId);
        }

        $requisitions = $query->get();

        return view('layouts.service-requisition-index', [
            'requisitions' => $requisitions,
            'pendingIds' => $requisitions->filter(
                fn ($r) => $r->hasPendingActionFor($role, auth()->id())
            )->pluck('id')->all(),
        ]);
    }

    /** Service requisitions not yet received on board. */
    public function pending()
    {
        return $this->bucket('Pending Service Requisitions', fn ($q) => $q->pendingList(), 'No pending service requisitions.');
    }

    /** Service requisitions the vessel has confirmed as received. */
    public function approved()
    {
        // Paged and searched on the server (15 a page by default, like the
        // Delivered item requisitions list): once received they pile up for good.
        return $this->bucket('Approved Service Requisitions', fn ($q) => $q->approvedList(), 'No service has been received yet.', true);
    }

    public function rejected()
    {
        return $this->bucket('Rejected Service Requisitions', fn ($q) => $q->rejectedList(), 'No rejected service requisitions.');
    }

    /**
     * What this SRD-level officer personally approved and is still in
     * progress: GM (SRD) approving or delegating it, or one of GM's delegates
     * signing it off. Once the service has been received it moves to Approved
     * (or Rejected if it is called off), so the three lists never overlap.
     * (A delegation stores the delegator's id in the forwarded_to_* column, so
     * for GM that is where "I delegated this" lives.)
     */
    public function myApprovals()
    {
        $role = auth()->user()->role->role ?? null;
        abort_unless($role === 'gm-srd' || isset(self::SRD_COLUMNS[$role]), 403, 'Only SRD officers have service approvals.');

        $userId = auth()->id();

        return $this->bucket('My Service Approvals', fn ($q) => $this->approvedByMe($q, $role, $userId), 'Nothing you approved is still in progress.');
    }

    /** The My Approvals filter, shared with the dashboard card's count. */
    private function approvedByMe($query, string $role, int $userId)
    {
        return $query->whereHas('approval', function ($a) use ($role, $userId) {
            if ($role === 'gm-srd') {
                $a->where('gm_app', $userId);
                foreach (self::SRD_COLUMNS as [$forwarded]) {
                    $a->orWhere($forwarded, $userId);
                }
            } else {
                $a->where(self::SRD_COLUMNS[$role][1], $userId);
            }
        })->pendingList();
    }

    /**
     * The dashboard's service requisition cards. Built from the very same
     * filters as the four lists they link to, so a card's number can't drift
     * from its list. 'my_approvals' is null for roles with no My Approvals
     * list (the SSM roles), which is how the cards know to leave it out.
     */
    public function stats(): array
    {
        $role = auth()->user()->role->role ?? null;
        $userId = auth()->id();
        $isSrd = $role === 'gm-srd' || isset(self::SRD_COLUMNS[$role]);

        // Ship roles count only their own vessel's, shore roles the whole fleet's -
        // the same scoping the lists themselves apply.
        $vesselId = auth()->user()->role->vessel_id;
        $scope = fn ($q) => (! empty($vesselId) && (auth()->user()->role->user_type ?? null) === 'ship')
            ? $q->where('vessel_id', $vesselId)
            : $q;

        $pending = $scope(ServiceRequisition::with('approval')->pendingList())->get();

        return [
            'pending' => $pending->count(),
            'pending_action' => $pending->filter(fn ($r) => $r->hasPendingActionFor($role, $userId))->count(),
            'my_approvals' => $isSrd ? $this->approvedByMe(ServiceRequisition::query(), $role, $userId)->count() : null,
            'approved' => $scope(ServiceRequisition::approvedList())->count(),
            'rejected' => $scope(ServiceRequisition::rejectedList())->count(),
        ];
    }

    /** One filtered list, same table and vessel scoping as the full index. */
    private function bucket(string $title, \Closure $filter, string $emptyText, bool $paged = false)
    {
        $role = auth()->user()->role->role ?? null;
        $vesselId = auth()->user()->role->vessel_id;

        $query = ServiceRequisition::with(['vessel', 'approval', 'items', 'creator', 'rejectedBy'])
            ->orderBy('updated_at', 'desc');
        $filter($query);

        // Ship roles see their own vessel's; shore roles the whole fleet's.
        if (! empty($vesselId) && (auth()->user()->role->user_type ?? null) === 'ship') {
            $query->where('vessel_id', $vesselId);
        }

        $extra = [];
        if ($paged) {
            $q = trim((string) request()->query('q'));
            $perPageChoice = $this->requisitionPerPage(request());
            $requisitions = $this->paginateRequisitions($this->searchServiceRequisitions($query, $q), $perPageChoice);

            // A stale ?page= past the end lands on the last page, not an empty one.
            if ($requisitions->isEmpty() && $requisitions->currentPage() > 1) {
                return redirect($requisitions->url($requisitions->lastPage()));
            }
            $extra = compact('q', 'perPageChoice');
        } else {
            $requisitions = $query->get();
        }

        return view('layouts.service-requisition-index', [
            'requisitions' => $requisitions,
            'pendingIds' => $requisitions->filter(
                fn ($r) => $r->hasPendingActionFor($role, auth()->id())
            )->pluck('id')->all(),
            'listTitle' => $title,
            'emptyText' => $emptyText,
            'showRejection' => $title === 'Rejected Service Requisitions',
        ] + $extra);
    }

    /**
     * Free-text search across all pages: req. no, type, vessel, raised by,
     * budget group and the titles of the lines on it.
     */
    private function searchServiceRequisitions($query, string $q)
    {
        if ($q === '') {
            return $query;
        }

        $like = $this->likeTerm($q);
        // "Renewal" / "Shore Repair" are labels; the column holds the type key.
        $typeKeys = array_keys(array_filter(
            ServiceRequisitionController::TYPES,
            fn ($label) => stripos($label, $q) !== false
        ));

        return $query->where(function ($w) use ($like, $typeKeys) {
            $w->where('req_no', 'like', $like)
                ->orWhereIn('service_type', $typeKeys)
                ->orWhereHas('vessel', fn ($v) => $v->where('name', 'like', $like))
                ->orWhereHas('creator', fn ($u) => $u->where('name', 'like', $like))
                ->orWhereHas('budgetGroup', fn ($b) => $b->where('name', 'like', $like))
                ->orWhereHas('items', fn ($i) => $i->where('title', 'like', $like));
        });
    }

    public function show($id)
    {
        $requisition = ServiceRequisition::with([
            'vessel', 'approval', 'items', 'creator', 'rejectedBy', 'invoice', 'budgetGroup',
            'procurementSteps.completedBy', 'procurementSteps.attachments',
        ])->findOrFail($id);

        $this->authorizeView($requisition);

        $role = auth()->user()->role->role ?? null;

        return view('layouts.service-requisition-detail', [
            'requisition' => $requisition,
            'canAct' => $requisition->hasPendingActionFor($role, auth()->id()),
            'currentRole' => $role,
            'signatories' => $requisition->signatories(),
            // Each SRD delegate role can be several real people, so GM picks a
            // person, not just a role - same as the item chain's delegation.
            'srdOfficers' => Role::whereIn('role', array_keys(self::SRD_COLUMNS))
                ->with('user')->get()->groupBy('role'),
        ]);
    }

    /** The printable service requisition form - same access as the detail page. */
    public function print($id)
    {
        $requisition = ServiceRequisition::with(['vessel', 'approval', 'items', 'budgetGroup'])->findOrFail($id);

        $this->authorizeView($requisition);

        return view('layouts.service-requisition-print', [
            'requisition' => $requisition,
            'isRenewal' => $requisition->service_type === ServiceRequisitionController::TYPE_RENEWAL,
            'signatories' => $requisition->signatories(),
            // Blank rows padded out to, so a short requisition still looks like the form.
            'minRows' => 8,
        ]);
    }

    public function approve(Request $request)
    {
        $requisition = ServiceRequisition::with('approval')->findOrFail($request->id);
        $role = auth()->user()->role->role ?? null;

        if (! $requisition->hasPendingActionFor($role, auth()->id())) {
            return response()->json(['message' => 'This requisition is not with you right now.'], 403);
        }

        // Once it's in procurement there is no single "approve" left - each
        // stage is completed from its own panel, through
        // ServiceProcurementController.
        if ($requisition->inProcurement()) {
            return response()->json([
                'message' => 'This requisition is in procurement. Complete the current stage from the panel instead.',
            ], 422);
        }

        $approval = $requisition->approval;

        $column = match ($role) {
            'master' => 'master_app',
            'chief-engineer' => 'chief_eng_app',
            'gm-srd' => 'gm_app',
            default => self::SRD_COLUMNS[$role][1] ?? null,
        };

        if ($column === null) {
            return response()->json(['message' => 'Your role has no approval step here.'], 403);
        }

        $approval->{$column} = auth()->id();
        $approval->save();

        $requisition->status = 'approved by '.$role;
        $requisition->save();

        $message = $role === 'gm-srd'
            ? 'Service requisition approved.'
            : ($column === 'master_app' || $column === 'chief_eng_app'
                ? 'Approved and forwarded to GM (SRD).'
                : 'Reviewed and sent back to GM (SRD).');

        return response()->json(['message' => $message, 'redirect' => route('service-requisition.index')]);
    }

    /** GM (SRD) delegates it to one named officer for review. */
    public function delegate(Request $request)
    {
        $requisition = ServiceRequisition::with('approval')->findOrFail($request->id);

        if ((auth()->user()->role->role ?? null) !== 'gm-srd') {
            return response()->json(['message' => 'Only GM (SRD) can delegate a service requisition.'], 403);
        }

        if (! $requisition->hasPendingActionFor('gm-srd', auth()->id())) {
            return response()->json(['message' => 'This requisition is not with you right now.'], 403);
        }

        $assignee = User::find($request->assigned_to);
        $assigneeRole = $assignee->role->role ?? null;

        if (! $assignee || ! isset(self::SRD_COLUMNS[$assigneeRole])) {
            return response()->json(['message' => 'Choose a reviewer to delegate this requisition to.'], 422);
        }

        $approval = $requisition->approval;

        // Only one delegate holds it at a time - clear any earlier delegation
        // so re-delegating doesn't leave two of them looking live at once.
        foreach (self::SRD_COLUMNS as [$forwarded, $reviewed]) {
            $approval->{$forwarded} = null;
            $approval->{$reviewed} = null;
        }

        // Delegating is the handoff INTO procurement, not a review round-trip:
        // the named officer takes the requisition from Administrative Approval
        // all the way to Delivery. Both writes in one transaction - a
        // delegation recorded without its opening stage would leave the
        // requisition in nobody's queue.
        DB::transaction(function () use ($approval, $assignee, $assigneeRole, $requisition) {
            $approval->assigned_to_srd = $assignee->id;
            $approval->{self::SRD_COLUMNS[$assigneeRole][0]} = auth()->id();
            $approval->save();

            $requisition->status = 'forwarded to '.$assigneeRole;
            $requisition->procurement_stage = ServiceProcurementStage::first();
            $requisition->save();
        });

        return response()->json([
            'message' => 'Delegated to '.$assignee->name.'. It now starts at '
                .ServiceProcurementStage::label(ServiceProcurementStage::first()).'.',
            'redirect' => route('service-requisition.index'),
        ]);
    }

    /**
     * Terminal, and open to whoever currently holds it - up until SRD
     * completes Delivery, after which the work has been carried out and there
     * is nothing left to turn away.
     */
    public function reject(Request $request)
    {
        $requisition = ServiceRequisition::with('approval')->findOrFail($request->id);
        $role = auth()->user()->role->role ?? null;
        $reason = trim((string) $request->reason);

        if ($reason === '') {
            return response()->json(['message' => 'Please give a reason for rejecting this requisition.'], 422);
        }

        if ($requisition->isRejected()) {
            return response()->json(['message' => 'This requisition has already been rejected.'], 422);
        }

        // Enforced here as well as hidden in the view: a stale tab or a
        // hand-rolled POST goes straight to this.
        if (ServiceProcurementStage::isPostDelivery($requisition->procurement_stage)) {
            return response()->json([
                'message' => 'The work has already been carried out, so this requisition can no longer be rejected.',
            ], 422);
        }

        if (! $requisition->hasPendingActionFor($role, auth()->id())) {
            return response()->json(['message' => 'This requisition is not with you right now, so you cannot reject it.'], 403);
        }

        // Captured before the status changes - afterwards currentStageLabel()
        // only reports "Rejected" and can't say where it died.
        $stage = $requisition->currentStageLabel();

        $requisition->status = 'rejected';
        $requisition->rejected_by = auth()->id();
        $requisition->rejected_by_role = $role;
        $requisition->rejected_at_stage = $stage;
        $requisition->rejection_reason = $reason;
        $requisition->rejected_at = Carbon::now();
        $requisition->save();

        return response()->json([
            'message' => 'Service requisition '.($requisition->req_no ?: '').' has been rejected.',
            'redirect' => route('service-requisition.index'),
        ]);
    }

    /**
     * Reading one: the raising vessel's own crew, and every shore role in the
     * chain. Another vessel's crew has no business seeing it.
     */
    private function authorizeView(ServiceRequisition $requisition): void
    {
        $roleRow = auth()->user()->role;

        if (($roleRow->user_type ?? null) === 'ship') {
            abort_unless($requisition->vessel_id == $roleRow->vessel_id, 403);
        }
    }
}
