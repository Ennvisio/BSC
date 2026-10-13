<?php

namespace App\Http\Controllers;

use App\Order;
use App\OrderInvoice;
use App\OrderItem;
use App\ProcurementStage;
use App\ProcurementStep;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The SSM procurement workflow - everything between DGM (SSM) assigning a
 * requisition and it being Closed. See App\ProcurementStage for the sequence.
 *
 * Two of the twelve stages are NOT handled here: Delivery and Receipt &
 * Verification are the existing approve actions in RoleController (they set
 * status, deliver/receive quantities and credit stock), and they record their
 * step row as a side effect of that. Everything else runs through
 * completeStep() below.
 */
class ProcurementController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Complete the stage a requisition is currently sitting at and advance it
     * to the next one.
     */
    public function completeStep(Request $request, Order $order)
    {
        $stage = $order->procurement_stage;

        // Rejection is terminal and can land mid-procurement, leaving a stage
        // pointer still set - so inProcurement() alone would let a stale page
        // carry a rejected requisition on to the next stage.
        if ($order->isRejected()) {
            return response()->json([
                'message' => 'This requisition was rejected and can no longer be acted on.',
            ], 422);
        }

        if (! $order->inProcurement() || $order->procurementClosed()) {
            return response()->json([
                'message' => 'This requisition is not in the procurement workflow.',
            ], 422);
        }

        // Delivery and Receipt & Verification are taken through the approve
        // flow, which does considerably more than record a step (quantities,
        // stock, status). Routing them here would skip all of that.
        if (ProcurementStage::isApprovalFlowStage($stage)) {
            return response()->json([
                'message' => 'This stage is completed from the Approve action, not here.',
            ], 422);
        }

        if (! $this->authorizeStage($order, $stage)) {
            return response()->json([
                'message' => 'This requisition is not assigned to you at this stage.',
            ], 403);
        }

        // Part C comes before procurement starts: the assigned SSM officer
        // fills it, and Administrative Approval can't be completed without it.
        if (\App\Http\Controllers\OrderFormPartController::outstanding(
            $order, auth()->user()->role->role ?? null, \App\RequisitionForm::PART_C
        )) {
            return response()->json([
                'message' => 'Complete the approval form Part C before starting procurement.',
            ], 422);
        }

        // The posted stage has to match what's actually current - otherwise a
        // page left open while someone else advanced it would complete the
        // wrong stage. The unique(order_id, step) index is the backstop, but
        // this gives a sensible message instead of an integrity error.
        if ($request->filled('stage') && $request->input('stage') !== $stage) {
            return response()->json([
                'message' => 'This requisition has moved on since this page was opened. Reload and try again.',
            ], 409);
        }

        $skipped = ProcurementStage::isSkippable($stage) && ! $request->boolean('proceed', true);

        if (ProcurementStage::capturesInvoice($stage) && ! $skipped) {
            $validation = $this->validateInvoice($request, $order);

            if ($validation !== null) {
                return response()->json(['message' => $validation], 422);
            }
        }

        // One transaction: the step row, the stage pointer and (at Invoice
        // Verification) the priced lines all describe the same event, and a
        // stage that advanced without its figures saved would be unrecoverable
        // - forward-only means there's no way back to re-enter them.
        DB::transaction(function () use ($request, $order, $stage, $skipped) {
            if (ProcurementStage::capturesInvoice($stage) && ! $skipped) {
                $this->saveInvoice($request, $order);
            }

            $step = ProcurementStep::create([
                'order_id' => $order->id,
                'step' => $stage,
                'outcome' => $skipped ? ProcurementStep::OUTCOME_SKIPPED : ProcurementStep::OUTCOME_DONE,
                'completed_by' => auth()->user()->id,
                'completed_at' => Carbon::now(),
                'remarks' => $request->filled('remarks') ? trim($request->remarks) : null,
                'meta' => $this->metaFor($request, $stage, $skipped),
            ]);

            $step->syncOwnedAttachments(
                (array) $request->input('attachment_ids', []),
                auth()->user()->id
            );

            $order->procurement_stage = ProcurementStage::next($stage);
            $order->save();
        });

        $order->refresh();
        $label = ProcurementStage::label($stage);

        // Stays on the same requisition rather than jumping to My Approvals -
        // procurement is ten stages one after another for the same officer,
        // so bouncing to a list and back in for every single one would be
        // tedious. Master/Chief Engineer and the approval-chain roles still
        // redirect to My Approvals from RoleController@approveRequisition;
        // this is deliberately different only for this multi-stage flow.
        return [
            $skipped ? $label.' marked as not required.' : $label.' completed successfully!',
            'redirect' => route('view.order.detail', $order->id),
        ];
    }

    /**
     * Only the officer DGM actually assigned it to. A null assignee means the
     * requisition predates named assignment, so any of the three SSM roles can
     * still work it - same fallback the approval queues use.
     */
    private function authorizeStage(Order $order, ?string $stage): bool
    {
        if (ProcurementStage::owner($stage) !== ProcurementStage::OWNER_SSM) {
            return false;
        }

        $role = auth()->user()->role->role ?? null;

        if (! in_array($role, ['agm-ssm', 'am-ssm', 'superintendent-ssm'], true)) {
            return false;
        }

        $assignee = $order->orderApproval->assigned_to_ssm ?? null;

        return $assignee === null || $assignee === auth()->user()->id;
    }

    /** Whitelisted per-stage extras - see ProcurementStage::META_FIELDS. */
    private function metaFor(Request $request, string $stage, bool $skipped): ?array
    {
        if ($skipped) {
            return ['required' => false];
        }

        $meta = [];

        foreach (ProcurementStage::metaFields($stage) as $field) {
            if ($request->filled($field)) {
                $meta[$field] = is_string($request->input($field))
                    ? trim($request->input($field))
                    : $request->input($field);
            }
        }

        return $meta === [] ? null : $meta;
    }

    /**
     * Invoice Verification's figures, checked before anything is written.
     * Returns an error message, or null when it's good.
     *
     * Per-item unit price/invoice quantity are optional - some invoices
     * price every line, some just give one lump figure for the whole
     * shipment, and this has to take either. Subtotal is the one thing
     * that's always required, since it's what Payable is actually computed
     * from; the JS pre-fills it from summed line totals when those are
     * entered, but the figure saved is always whatever ends up in that
     * field, not a server-side recalculation of it.
     */
    private function validateInvoice(Request $request, Order $order): ?string
    {
        if (! \App\Currency::isActiveCode($request->input('currency_code'))) {
            return 'Choose the currency the invoice is billed in.';
        }

        $subtotal = $request->input('subtotal');

        if ($subtotal === null || $subtotal === '' || ! is_numeric($subtotal) || $subtotal < 0) {
            return 'Enter the invoice subtotal.';
        }

        $prices = (array) $request->input('unit_price', []);
        $quantities = (array) $request->input('invoice_qty', []);

        foreach ($order->orderItems as $line) {
            $price = $prices[$line->id] ?? null;
            $price = ($price === '' ? null : $price);

            // Whether this line is priced is decided by unit_price alone -
            // invoice_qty always arrives with a default (Rcv Qty/Deliver Qty,
            // see the table above), so it's never actually blank on its own
            // and can't be used to tell "not priced" from "priced". Nothing
            // left in it matters when there's no price to multiply it by.
            if ($price === null) {
                continue;
            }

            $qty = $quantities[$line->id] ?? null;
            $qty = ($qty === '' ? null : $qty);

            if ($qty === null) {
                return 'Give this item an invoiced quantity to go with its unit price.';
            }

            if (! is_numeric($price) || ! is_numeric($qty) || $price < 0 || $qty < 0) {
                return 'Unit prices and quantities must be zero or more.';
            }
        }

        $discount = (float) $request->input('discount', 0);

        if ($discount < 0) {
            return 'Discount cannot be negative.';
        }

        if (round($discount, 2) > round((float) $subtotal, 2)) {
            return 'Discount cannot be more than the invoice subtotal ('.number_format((float) $subtotal, 2).').';
        }

        return null;
    }

    /**
     * Writes whichever lines were priced and the invoice header. Subtotal is
     * the figure that was actually in the field when this was submitted -
     * usually the sum of the priced lines below (the JS pre-fills it that
     * way), but not recomputed from them here, since an invoice with no
     * per-item prices at all still needs somewhere to have come from.
     */
    private function saveInvoice(Request $request, Order $order): void
    {
        $prices = (array) $request->input('unit_price', []);
        $quantities = (array) $request->input('invoice_qty', []);

        foreach ($order->orderItems as $line) {
            $price = $prices[$line->id] ?? null;
            $qty = $quantities[$line->id] ?? null;

            // Left blank for this line - validateInvoice() already confirmed
            // both are blank together, never just one.
            if ($price === null || $price === '') {
                continue;
            }

            $price = round((float) $price, 2);
            $qty = (int) $qty;

            OrderItem::where('id', $line->id)->where('order_id', $order->id)->update([
                'unit_price' => $price,
                'invoice_qty' => $qty,
                'line_total' => round($price * $qty, 2),
            ]);
        }

        $subtotal = round((float) $request->input('subtotal'), 2);
        $discount = round((float) $request->input('discount', 0), 2);

        OrderInvoice::updateOrCreate(
            ['order_id' => $order->id],
            [
                'invoice_no' => $request->filled('invoice_no') ? trim($request->invoice_no) : null,
                'invoice_date' => $request->filled('invoice_date') ? $request->invoice_date : null,
                'currency_code' => $request->input('currency_code'),
                'subtotal' => $subtotal,
                'discount' => $discount,
                'payable' => round($subtotal - $discount, 2),
                'verified_by' => auth()->user()->id,
                'verified_at' => Carbon::now(),
            ]
        );
    }
}
