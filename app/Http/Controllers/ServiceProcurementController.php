<?php

namespace App\Http\Controllers;

use App\ServiceProcurementStage;
use App\ServiceProcurementStep;
use App\ServiceRequisition;
use App\ServiceRequisitionInvoice;
use App\ServiceRequisitionItem;
use App\VesselCertificate;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The service requisition's procurement workflow - everything between GM (SRD)
 * delegating a requisition and the vessel confirming the work was done. See
 * App\ServiceProcurementStage for the sequence.
 *
 * Unlike the item workflow, every stage runs through completeStep() here:
 * there are no quantities to deliver or stock to credit, so Delivery and
 * Receipt & Verification are ordinary stages rather than side effects of an
 * approve action. They differ only in who owns them - the assigned SRD officer
 * up to Delivery, the vessel's Master/Chief Engineer for Receipt.
 */
class ServiceProcurementController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Complete the stage a requisition is currently sitting at and advance it
     * to the next one.
     */
    public function completeStep(Request $request, ServiceRequisition $serviceRequisition)
    {
        $stage = $serviceRequisition->procurement_stage;

        // Rejection is terminal and can land mid-procurement (whoever holds a
        // requisition can reject it), leaving a stage pointer still set - so
        // inProcurement() alone would let a stale page carry a rejected
        // requisition on to the next stage.
        if ($serviceRequisition->isRejected()) {
            return response()->json([
                'message' => 'This requisition was rejected and can no longer be acted on.',
            ], 422);
        }

        if (! $serviceRequisition->inProcurement() || $serviceRequisition->procurementClosed()) {
            return response()->json([
                'message' => 'This requisition is not in the procurement workflow.',
            ], 422);
        }

        $role = auth()->user()->role->role ?? null;

        if (! $serviceRequisition->hasPendingActionFor($role, auth()->id())) {
            return response()->json([
                'message' => 'This stage is not yours to complete.',
            ], 403);
        }

        // The posted stage has to match what's actually current - otherwise a
        // page left open while someone else advanced it would complete the
        // wrong stage. The unique(service_requisition_id, step) index is the
        // backstop; this gives a sensible message instead of an integrity error.
        if ($request->filled('stage') && $request->input('stage') !== $stage) {
            return response()->json([
                'message' => 'This requisition has moved on since this page was opened. Reload and try again.',
            ], 409);
        }

        $skipped = ServiceProcurementStage::isSkippable($stage) && ! $request->boolean('proceed', true);

        if (ServiceProcurementStage::capturesInvoice($stage) && ! $skipped) {
            $validation = $this->validateInvoice($request, $serviceRequisition);

            if ($validation !== null) {
                return response()->json(['message' => $validation], 422);
            }
        }

        // One transaction: the step row, the stage pointer, the delegate's own
        // sign-off and (at Invoice Verification) the priced lines all describe
        // the same event - and a stage that advanced without its figures saved
        // would be unrecoverable, since the flow is forward-only.
        DB::transaction(function () use ($request, $serviceRequisition, $stage, $skipped, $role) {
            if (ServiceProcurementStage::capturesInvoice($stage) && ! $skipped) {
                $this->saveInvoice($request, $serviceRequisition);
            }

            $meta = $this->metaFor($request, $stage, $skipped);

            // Confirming receipt of a Renewal IS the renewal: the vessel now
            // holds the new certificates, so their validity runs from today.
            // In the same transaction as the stage, since the stage advancing
            // is the only record that it happened.
            if ($stage === ServiceProcurementStage::RECEIPT_VERIFICATION) {
                $renewed = $this->rollCertificatesForward($serviceRequisition);

                if ($renewed !== []) {
                    $meta = ($meta ?? []) + ['certificates_renewed' => $renewed];
                }
            }

            $step = ServiceProcurementStep::create([
                'service_requisition_id' => $serviceRequisition->id,
                'step' => $stage,
                'outcome' => $skipped ? ServiceProcurementStep::OUTCOME_SKIPPED : ServiceProcurementStep::OUTCOME_DONE,
                'completed_by' => auth()->id(),
                'completed_at' => Carbon::now(),
                'remarks' => $request->filled('remarks') ? trim($request->remarks) : null,
                'meta' => $meta,
            ]);

            $step->syncOwnedAttachments((array) $request->input('attachment_ids', []), auth()->id());

            // Administrative Approval IS the delegate's sign-off, so it writes
            // their approval column - that is what puts their name and
            // signature on the requisition's Authorisation block.
            if ($stage === ServiceProcurementStage::ADMINISTRATIVE_APPROVAL) {
                $this->recordDelegateSignature($serviceRequisition, $role);
            }

            $serviceRequisition->procurement_stage = ServiceProcurementStage::next($stage);
            $serviceRequisition->status = $serviceRequisition->procurementClosed()
                ? 'completed'
                : 'in procurement';
            $serviceRequisition->save();
        });

        $serviceRequisition->refresh();
        $label = ServiceProcurementStage::label($stage);

        // Stays on the same requisition rather than jumping to the list -
        // procurement is several stages one after another for the same
        // officer, so bouncing out to a list and back in for every one would
        // be tedious.
        return response()->json([
            'message' => $skipped ? $label.' marked as not required.' : $label.' completed successfully!',
            'redirect' => route('service-requisition.show', $serviceRequisition->id),
        ]);
    }

    /**
     * Pushes each renewed certificate's validity forward - re-issued today,
     * expiring its own validity_years later. A five-year certificate lands
     * five years out, a one-year certificate a year out.
     *
     * Only Renewal requisitions carry certificate lines, so a Shore Repair one
     * simply finds nothing to do. Certificates that can't be rolled (permanent,
     * or with no validity recorded) are left untouched and reported, so the
     * trail says what did NOT move rather than quietly skipping it.
     *
     * @return array<int,string> one line per certificate, for the step's meta
     */
    private function rollCertificatesForward(ServiceRequisition $requisition): array
    {
        $today = Carbon::now()->toDateString();
        $by = auth()->user()->name ?? null;
        $renewed = [];

        foreach ($requisition->items as $line) {
            if (! $line->vessel_certificate_id) {
                continue;
            }

            $certificate = VesselCertificate::find($line->vessel_certificate_id);

            if (! $certificate) {
                continue;   // deleted since the requisition was raised
            }

            $expiry = $certificate->renewFrom($today, $by);

            $renewed[] = $expiry === null
                ? $certificate->title.' — not dated ('.($certificate->is_permanent
                    ? 'permanent' : 'no renewal period recorded').')'
                : $certificate->title.' — valid to '.Carbon::parse($expiry)->format('d M Y');
        }

        return $renewed;
    }

    /**
     * Writes the delegate's approval column - whichever of the four GM handed
     * it to. Read off the delegation itself rather than the signer's current
     * role, so the column that gets set is the one the delegation was made to.
     */
    private function recordDelegateSignature(ServiceRequisition $requisition, ?string $role): void
    {
        $delegateRole = $requisition->assignedDelegateRole() ?: $role;
        $columns = ServiceRequisition::SRD_DELEGATE_ROLES[$delegateRole] ?? null;

        if ($columns === null) {
            return;
        }

        $approval = $requisition->approval;
        $approval->{$columns[1]} = auth()->id();
        $approval->save();
    }

    /**
     * Invoice Verification's figures, checked before anything is written.
     * Returns an error message, or null when it's good.
     */
    private function validateInvoice(Request $request, ServiceRequisition $requisition): ?string
    {
        $prices = (array) $request->input('unit_price', []);
        $quantities = (array) $request->input('invoice_qty', []);

        if ($prices === []) {
            return 'Enter the unit price and invoiced quantity for each line.';
        }

        $subtotal = 0;

        foreach ($requisition->items as $line) {
            $price = $prices[$line->id] ?? null;
            $qty = $quantities[$line->id] ?? null;

            if ($price === null || $price === '' || $qty === null || $qty === '') {
                return 'Every line needs a unit price and an invoiced quantity.';
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
     * stored, as the frozen as-billed figure approved for payment.
     */
    private function saveInvoice(Request $request, ServiceRequisition $requisition): void
    {
        $prices = (array) $request->input('unit_price', []);
        $quantities = (array) $request->input('invoice_qty', []);
        $subtotal = 0;

        foreach ($requisition->items as $line) {
            $price = round((float) $prices[$line->id], 2);
            $qty = (int) $quantities[$line->id];
            $lineTotal = round($price * $qty, 2);
            $subtotal += $lineTotal;

            ServiceRequisitionItem::where('id', $line->id)
                ->where('service_requisition_id', $requisition->id)
                ->update([
                    'unit_price' => $price,
                    'invoice_qty' => $qty,
                    'line_total' => $lineTotal,
                ]);
        }

        $discount = round((float) $request->input('discount', 0), 2);

        ServiceRequisitionInvoice::updateOrCreate(
            ['service_requisition_id' => $requisition->id],
            [
                'invoice_no' => $request->filled('invoice_no') ? trim($request->invoice_no) : null,
                'invoice_date' => $request->filled('invoice_date') ? $request->invoice_date : null,
                'discount' => $discount,
                'payable' => round($subtotal - $discount, 2),
                'verified_by' => auth()->id(),
                'verified_at' => Carbon::now(),
            ]
        );
    }

    /** Whitelisted per-stage extras - see ServiceProcurementStage::META_FIELDS. */
    private function metaFor(Request $request, string $stage, bool $skipped): ?array
    {
        if ($skipped) {
            return ['required' => false];
        }

        $meta = [];

        foreach (ServiceProcurementStage::metaFields($stage) as $field) {
            if ($request->filled($field)) {
                $meta[$field] = is_string($request->input($field))
                    ? trim($request->input($field))
                    : $request->input($field);
            }
        }

        return $meta === [] ? null : $meta;
    }
}
