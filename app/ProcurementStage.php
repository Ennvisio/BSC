<?php

namespace App;

/**
 * The SSM procurement workflow: the twelve stages a requisition passes through
 * after DGM (SSM) assigns it to a named officer, in order, with who owns each.
 *
 * One definition, because routing (whose queue is this in), the stage label on
 * every list, and the timeline on the order page all have to agree. Adding a
 * stage is a new entry here plus handling for whatever it captures - not a
 * migration and a sweep through every query, which is what order_approvals'
 * column-per-role shape costs.
 */
class ProcurementStage
{
    const ADMINISTRATIVE_APPROVAL = 'administrative_approval';
    const TENDERING = 'tendering';
    const BID_EVALUATION = 'bid_evaluation';
    const AWARD_APPROVAL = 'award_approval';
    const PURCHASE_ORDER = 'purchase_order';
    const ADVANCE_PAYMENT = 'advance_payment';
    const DELIVERY = 'delivery';
    const RECEIPT_VERIFICATION = 'receipt_verification';
    const INVOICE_VERIFICATION = 'invoice_verification';
    const FINANCE_CLEARANCE = 'finance_clearance';
    const PAYMENT = 'payment';
    const CLOSED = 'closed';

    /** Owner types. Anything not SHIP is worked by the assigned SSM officer. */
    const OWNER_SSM = 'ssm';
    const OWNER_SHIP = 'ship';

    /**
     * In order. `closed` is the terminal marker rather than a stage anyone
     * performs - procurement_stage takes that value once Payment completes.
     */
    const SEQUENCE = [
        self::ADMINISTRATIVE_APPROVAL,
        self::TENDERING,
        self::BID_EVALUATION,
        self::AWARD_APPROVAL,
        self::PURCHASE_ORDER,
        self::ADVANCE_PAYMENT,
        self::DELIVERY,
        self::RECEIPT_VERIFICATION,
        self::INVOICE_VERIFICATION,
        self::FINANCE_CLEARANCE,
        self::PAYMENT,
        self::CLOSED,
    ];

    const LABELS = [
        self::ADMINISTRATIVE_APPROVAL => 'Administrative Approval',
        self::TENDERING => 'Tendering',
        self::BID_EVALUATION => 'Bid Evaluation',
        self::AWARD_APPROVAL => 'Award Approval',
        self::PURCHASE_ORDER => 'Purchase Order',
        self::ADVANCE_PAYMENT => 'Advance Payment',
        self::DELIVERY => 'Delivery',
        self::RECEIPT_VERIFICATION => 'Receipt & Verification',
        self::INVOICE_VERIFICATION => 'Invoice Verification',
        self::FINANCE_CLEARANCE => 'Finance Clearance',
        self::PAYMENT => 'Payment',
        self::CLOSED => 'Closed',
    ];

    /**
     * The two stages that are NOT recorded through ProcurementController:
     * they're the existing approve actions, which already set status,
     * deliver/receive quantities and stock. Completing them records a step row
     * as a side effect rather than being driven by one.
     */
    const APPROVAL_FLOW_STAGES = [
        self::DELIVERY,
        self::RECEIPT_VERIFICATION,
    ];

    /**
     * The extra fields each stage captures, stored in the step row's `meta`.
     * Whitelisted here rather than accepting whatever the form posts, and kept
     * beside the sequence so a stage's inputs and its position stay together.
     * Stages absent from this list capture nothing but remarks.
     */
    const META_FIELDS = [
        self::TENDERING => ['tender_ref', 'vendors_invited'],
        self::BID_EVALUATION => ['bids_received', 'lowest_bidder'],
        self::AWARD_APPROVAL => ['awarded_to', 'awarded_amount'],
        self::PURCHASE_ORDER => ['po_number', 'po_date'],
        self::ADVANCE_PAYMENT => ['advance_amount', 'advance_ref'],
        self::PAYMENT => ['paid_amount', 'payment_ref', 'payment_date'],
    ];

    /** Human labels for the meta inputs above. */
    const META_LABELS = [
        'tender_ref' => 'Tender Reference',
        'vendors_invited' => 'Vendors Invited',
        'bids_received' => 'Bids Received',
        'lowest_bidder' => 'Lowest Bidder',
        'awarded_to' => 'Awarded To',
        'awarded_amount' => 'Awarded Amount (BDT)',
        'po_number' => 'PO Number',
        'po_date' => 'PO Date',
        'advance_amount' => 'Advance Amount (BDT)',
        'advance_ref' => 'Advance Reference',
        'paid_amount' => 'Amount Paid (BDT)',
        'payment_ref' => 'Payment Reference',
        'payment_date' => 'Payment Date',
    ];

    public static function metaFields(?string $stage): array
    {
        return self::META_FIELDS[$stage] ?? [];
    }

    public static function metaLabel(string $field): string
    {
        return self::META_LABELS[$field] ?? ucwords(str_replace('_', ' ', $field));
    }

    /** The stage a requisition enters procurement at, when DGM (SSM) assigns it. */
    public static function first(): string
    {
        return self::SEQUENCE[0];
    }

    /** The stage that becomes current once $stage completes. */
    public static function next(string $stage): ?string
    {
        $index = array_search($stage, self::SEQUENCE, true);

        if ($index === false || $index === count(self::SEQUENCE) - 1) {
            return null;
        }

        return self::SEQUENCE[$index + 1];
    }

    public static function label(?string $stage): string
    {
        return self::LABELS[$stage] ?? '';
    }

    /** 1-based position, for display ("Stage 4 of 11"). */
    public static function position(string $stage): int
    {
        $index = array_search($stage, self::SEQUENCE, true);

        return $index === false ? 0 : $index + 1;
    }

    public static function exists(?string $stage): bool
    {
        return $stage !== null && in_array($stage, self::SEQUENCE, true);
    }

    /**
     * Who has the action while a requisition sits at $stage. Null for `closed`
     * (and anything unrecognised) - nobody's queue.
     */
    public static function owner(?string $stage): ?string
    {
        if ($stage === self::RECEIPT_VERIFICATION) {
            return self::OWNER_SHIP;
        }

        if ($stage === self::CLOSED || ! self::exists($stage)) {
            return null;
        }

        return self::OWNER_SSM;
    }

    /** Every stage worked by the assigned SSM officer - their pending queue. */
    public static function ssmStages(): array
    {
        return array_values(array_filter(
            self::SEQUENCE,
            fn ($stage) => self::owner($stage) === self::OWNER_SSM
        ));
    }

    /**
     * Whether this stage is completed through the existing approve flow
     * (RoleController) rather than ProcurementController.
     */
    public static function isApprovalFlowStage(?string $stage): bool
    {
        return in_array($stage, self::APPROVAL_FLOW_STAGES, true);
    }

    /** The stage that captures per-line pricing and the invoice header. */
    public static function capturesInvoice(?string $stage): bool
    {
        return $stage === self::INVOICE_VERIFICATION;
    }

    /** Advance Payment is the only stage that can legitimately be skipped. */
    public static function isSkippable(?string $stage): bool
    {
        return $stage === self::ADVANCE_PAYMENT;
    }

    /**
     * Four broad phases, used purely for colour-coding the stage panel and
     * timeline - grouping "Tendering" and "Purchase Order" under one hue
     * (they're both sourcing decisions) reads better than either one flat
     * colour for all twelve stages or a different one for each.
     */
    const CATEGORY_SOURCING = 'sourcing';
    const CATEGORY_HANDOFF = 'handoff';
    const CATEGORY_FINANCE = 'finance';
    const CATEGORY_CLOSED = 'closed';

    const CATEGORIES = [
        self::ADMINISTRATIVE_APPROVAL => self::CATEGORY_SOURCING,
        self::TENDERING => self::CATEGORY_SOURCING,
        self::BID_EVALUATION => self::CATEGORY_SOURCING,
        self::AWARD_APPROVAL => self::CATEGORY_SOURCING,
        self::PURCHASE_ORDER => self::CATEGORY_SOURCING,
        self::ADVANCE_PAYMENT => self::CATEGORY_SOURCING,
        self::DELIVERY => self::CATEGORY_HANDOFF,
        self::RECEIPT_VERIFICATION => self::CATEGORY_HANDOFF,
        self::INVOICE_VERIFICATION => self::CATEGORY_FINANCE,
        self::FINANCE_CLEARANCE => self::CATEGORY_FINANCE,
        self::PAYMENT => self::CATEGORY_FINANCE,
        self::CLOSED => self::CATEGORY_CLOSED,
    ];

    const CATEGORY_LABELS = [
        self::CATEGORY_SOURCING => 'Sourcing',
        self::CATEGORY_HANDOFF => 'Handover',
        self::CATEGORY_FINANCE => 'Finance',
        self::CATEGORY_CLOSED => 'Closed',
    ];

    public static function category(?string $stage): string
    {
        return self::CATEGORIES[$stage] ?? self::CATEGORY_SOURCING;
    }

    public static function categoryLabel(?string $stage): string
    {
        return self::CATEGORY_LABELS[self::category($stage)];
    }
}
