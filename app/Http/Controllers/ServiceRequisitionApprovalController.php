<?php

namespace App\Http\Controllers;

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
