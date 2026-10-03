<?php

namespace App\Http\Controllers;

use App\Order;
use App\OrderFormPart;
use App\ProcurementStage;
use App\RequisitionForm;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Parts of the approval form that are filled in AFTER the requisition is
 * submitted: Part B, SRD's cross-verification of what the ship claimed in
 * Part A, and Part C, the assigned SSM officer's review before procurement starts.
 *
 * Part A is not handled here: it belongs to the wizard, is filled before the
 * requisition exists, and RequisitionController owns it along with the draft
 * it is attached to.
 *
 * Its own controller rather than more methods on RoleController, which is
 * already carrying every approval action in the app.
 */
class OrderFormPartController extends Controller
{
    /**
     * Who owns each part, and therefore who is blocked until it's done.
     *
     * Part B is the whole SRD stage - GM (SRD) and the four officers GM can
     * delegate to - since whoever is holding the requisition there does the
     * cross-check. Part C belongs to the SSM officer DGM (SSM) assigned the
     * requisition to (AGM / AM / Superintendent): it's their review before
     * procurement starts, so it gates the first stage, Administrative
     * Approval. DGM (SSM) only assigns.
     */
    private const PART_OWNERS = [
        RequisitionForm::PART_B => ['gm-srd', 'dgm-srd', 'agm-srd', 'am-srd', 'superintendent-srd'],
        RequisitionForm::PART_C => ['agm-ssm', 'am-ssm', 'superintendent-ssm'],
    ];

    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Whether $order still needs $part before this role can act on it. Used
     * both to gate the buttons in the view and to refuse the actions
     * themselves, so the two can't disagree.
     */
    public static function outstanding(Order $order, ?string $role, string $part): bool
    {
        if (! in_array($role, self::PART_OWNERS[$part] ?? [], true)) {
            return false;
        }

        // Part C is only ever outstanding at the start of procurement. A
        // requisition already past Administrative Approval when this rule
        // arrived has no Part C and mustn't suddenly be blocked on one.
        if ($part === RequisitionForm::PART_C && $order->procurement_stage !== ProcurementStage::first()) {
            return false;
        }

        $saved = $order->formPart($part);

        return $saved === null || ! $saved->isComplete();
    }

    public function store(Request $request, Order $order, string $part)
    {
        if (! isset(self::PART_OWNERS[$part])) {
            return response()->json(['message' => 'Unknown form part.'], 404);
        }

        $role = auth()->user()->role->role ?? null;

        if (! in_array($role, self::PART_OWNERS[$part], true)) {
            return response()->json([
                'message' => 'Part '.$part.' is not yours to complete.',
            ], 403);
        }

        // Only whoever currently holds it - the same gate every other action
        // on this requisition uses, so a stale tab can't fill in a part for a
        // requisition that has already moved on.
        if (! $order->hasPendingActionFor($role, auth()->id())) {
            return response()->json([
                'message' => 'This requisition is not with you right now.',
            ], 403);
        }

        $result = RequisitionForm::clean($part, (array) $request->input('q', []));

        if ($result['errors'] !== []) {
            return response()->json([
                'message' => 'Answer every question before submitting.',
                'errors' => $result['errors'],
            ], 422);
        }

        $user = auth()->user();

        OrderFormPart::updateOrCreate(
            ['order_id' => $order->id, 'part' => $part],
            [
                'form_version' => RequisitionForm::VERSION,
                'answers' => $result['answers'],
                // Stamped as it stood at the time - name and rank as they were
                // when this was signed off, not a live lookup of the user later.
                'declaration' => [
                    'declared_by' => $user->name,
                    'declared_by_role' => $user->role->role ?? null,
                    'declared_at' => Carbon::now()->toDateTimeString(),
                ],
                'completed_at' => Carbon::now(),
                'filled_by' => $user->id,
            ]
        );

        return response()->json([
            'message' => 'Part '.$part.' completed.'.($part === RequisitionForm::PART_C
                ? ' You can now start procurement.'
                : ' You can now approve or forward this requisition.'),
            'redirect' => route('view.order.detail', $order->id),
        ]);
    }
}
