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
     */
    private function validateInvoice(Request $request, Order $order): ?string
    {
        $prices = (array) $request->input('unit_price', []);
        $quantities = (array) $request->input('invoice_qty', []);

        if ($prices === []) {
            return 'Enter the unit price and invoiced quantity for each item.';
        }

        $subtotal = 0;

        foreach ($order->orderItems as $line) {
            $price = $prices[$line->id] ?? null;
            $qty = $quantities[$line->id] ?? null;

            if ($price === null || $price === '' || $qty === null || $qty === '') {
                return 'Every item needs a unit price and an invoiced quantity.';
            }

            if (! is_numeric($price) || ! is_numeric($qty) || $price < 0 || $qty < 0) {
                return 'Unit prices and quantities must be zero or more.';
            }

            $subtotal += round($price * $qty, 2);
        }

        $discount = (float) $request->input('discount', 0);

        if ($discount < 0) {
            return 'Discount cannot be negative.';
        }

        if (round($discount, 2) > round($subtotal, 2)) {
            return 'Discount cannot be more than the invoice subtotal ('.number_format($subtotal, 2).').';
        }

        return null;
    }

    /**
     * Writes the priced lines and the invoice header.
     *
     * Subtotal is deliberately not stored - it's SUM(line_total). Payable IS
     * stored, as the frozen as-billed figure that was approved for payment.
     */
    private function saveInvoice(Request $request, Order $order): void
    {
        $prices = (array) $request->input('unit_price', []);
        $quantities = (array) $request->input('invoice_qty', []);
        $subtotal = 0;

        foreach ($order->orderItems as $line) {
            $price = round((float) $prices[$line->id], 2);
            $qty = (int) $quantities[$line->id];
            $lineTotal = round($price * $qty, 2);
            $subtotal += $lineTotal;

            OrderItem::where('id', $line->id)->where('order_id', $order->id)->update([
                'unit_price' => $price,
                'invoice_qty' => $qty,
                'line_total' => $lineTotal,
            ]);
        }

        $discount = round((float) $request->input('discount', 0), 2);

        OrderInvoice::updateOrCreate(
            ['order_id' => $order->id],
            [
                'invoice_no' => $request->filled('invoice_no') ? trim($request->invoice_no) : null,
                'invoice_date' => $request->filled('invoice_date') ? $request->invoice_date : null,
                'discount' => $discount,
                'payable' => round($subtotal - $discount, 2),
                'verified_by' => auth()->user()->id,
                'verified_at' => Carbon::now(),
            ]
        );
    }
}
