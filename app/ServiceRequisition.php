<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * A service requisition - Shore Repair or Renewal. Its approval chain is the
 * item requisition's, minus the SSM leg: raised by chief-officer/second-
 * engineer, reviewed by Master/Chief Engineer, then GM (SRD), who may
 * delegate it to one of DGM/AGM/AM/Superintendent (SRD) for review before
 * approving it themselves.
 */
class ServiceRequisition extends Model
{
    protected $fillable = [
        'vessel_id', 'req_no', 'req_date', 'service_type', 'budget_group_id', 'due_date',
        'description', 'status', 'is_submitted', 'created_by', 'created_by_role',
    ];

    protected $casts = [
        'req_date' => 'date',
        'due_date' => 'date',
        'is_submitted' => 'boolean',
        'rejected_at' => 'datetime',
    ];

    /** GM (SRD)'s four delegates, as [forwarded-to column, reviewed column]. */
    private const SRD_DELEGATE_COLUMNS = [
        'DGM (SRD)' => ['forwarded_to_dgm_srd', 'dgm_srd_app'],
        'AGM (SRD)' => ['forwarded_to_agm_by_gm_srd', 'agm_app'],
        'AM (SRD)' => ['forwarded_to_am_by_agm_srd', 'ast_m_app'],
        'Superintendent (SRD)' => ['forwarded_to_superintendent_srd', 'superintendent_srd_app'],
    ];

    /**
     * The same four, keyed by role string instead of display name. Two maps
     * rather than one because the callers genuinely differ: the labels above
     * are what currentStageLabel() prints, these are what routing matches a
     * logged-in user's role against.
     */
    public const SRD_DELEGATE_ROLES = [
        'dgm-srd' => ['forwarded_to_dgm_srd', 'dgm_srd_app', 'DGM (SRD)'],
        'agm-srd' => ['forwarded_to_agm_by_gm_srd', 'agm_app', 'AGM (SRD)'],
        'am-srd' => ['forwarded_to_am_by_agm_srd', 'ast_m_app', 'AM (SRD)'],
        'superintendent-srd' => ['forwarded_to_superintendent_srd', 'superintendent_srd_app', 'Superintendent (SRD)'],
    ];

    public function vessel()
    {
        return $this->belongsTo(Vessel::class);
    }

    public function items()
    {
        return $this->hasMany(ServiceRequisitionItem::class);
    }

    public function approval()
    {
        return $this->hasOne(ServiceRequisitionApproval::class);
    }

    public function budgetGroup()
    {
        return $this->belongsTo(BudgetGroup::class);
    }

    /** The invoice header captured at Invoice Verification, if it's been reached. */
    public function invoice()
    {
        return $this->hasOne(ServiceRequisitionInvoice::class);
    }

    /** Completed procurement stages, oldest first - the timeline. */
    public function procurementSteps()
    {
        return $this->hasMany(ServiceProcurementStep::class)->orderBy('completed_at');
    }

    /** Has this requisition entered the procurement workflow at all? */
    public function inProcurement(): bool
    {
        return ServiceProcurementStage::exists($this->procurement_stage);
    }

    public function procurementClosed(): bool
    {
        return $this->procurement_stage === ServiceProcurementStage::CLOSED;
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function rejectedBy()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function typeLabel(): string
    {
        return \App\Http\Controllers\ServiceRequisitionController::TYPES[$this->service_type] ?? $this->service_type;
    }

    /** Where it sits right now, in words - computed, never stored. */
    public function currentStageLabel(): string
    {
        if ($this->isRejected()) {
            return 'Rejected';
        }

        if (! $this->is_submitted) {
            return 'Draft';
        }

        // Once GM has delegated it, the procurement sub-stage IS the answer -
        // otherwise this would report a static "With AGM (SRD) for Review" for
        // the weeks a tender actually takes.
        if ($this->inProcurement()) {
            if ($this->procurementClosed()) {
                return 'Closed';
            }

            return ServiceProcurementStage::owner($this->procurement_stage) === ServiceProcurementStage::OWNER_SHIP
                ? 'Work Done — Awaiting Vessel Confirmation'
                : ServiceProcurementStage::label($this->procurement_stage);
        }

        $approval = $this->approval;
        if (! $approval) {
            return 'Awaiting Officer Approval';
        }

        if ($approval->gm_app !== null) {
            return 'Approved by GM (SRD)';
        }

        // A delegate's *_app being set means they've finished and it's back
        // with GM; a forwarded_to_* with no *_app means it's still with them.
        foreach (self::SRD_DELEGATE_COLUMNS as $name => [$forwarded, $reviewed]) {
            if ($approval->{$reviewed} !== null) {
                return 'Reviewed by '.$name.' — Awaiting GM (SRD)';
            }
        }

        foreach (self::SRD_DELEGATE_COLUMNS as $name => [$forwarded, $reviewed]) {
            if ($approval->{$forwarded} !== null) {
                return 'With '.$name.' for Review';
            }
        }

        if ($approval->master_app !== null || $approval->chief_eng_app !== null) {
            return 'Awaiting GM (SRD)';
        }

        if ($approval->cheif_ofcr_app !== null) {
            return 'Awaiting Master Review';
        }

        if ($approval->second_eng_app !== null) {
            return 'Awaiting Chief Engineer Review';
        }

        return 'Awaiting Officer Approval';
    }

    /**
     * Whether this role has something to do with it right now - what every
     * Approve/Delegate/Reject button is gated on. Mirrors
     * Order::hasPendingActionFor, minus the SSM branches.
     */
    public function hasPendingActionFor(?string $role, ?int $userId = null): bool
    {
        if ($this->isRejected() || ! $this->is_submitted || $role === null) {
            return false;
        }

        $approval = $this->approval;
        if (! $approval || $approval->gm_app !== null) {
            return false;   // approved by GM = the end of this chain
        }

        $forwardedAshore = $approval->master_app !== null || $approval->chief_eng_app !== null;
        $sameVessel = fn () => $this->vessel_id == (auth()->user()->role->vessel_id ?? null);

        // In procurement the current stage decides everything, not the
        // approval columns: the delegate's own sign-off (Administrative
        // Approval) sets their *_app column, which would otherwise hand the
        // requisition straight back to GM mid-tender.
        if ($this->inProcurement()) {
            if ($this->procurementClosed()) {
                return false;
            }

            $owner = ServiceProcurementStage::owner($this->procurement_stage);

            if ($owner === ServiceProcurementStage::OWNER_SHIP) {
                return in_array($role, ['master', 'chief-engineer'], true) && $sameVessel();
            }

            // A null assignee means nobody was named, so any of the four SRD
            // delegate roles can still work it - the same fallback the item
            // workflow uses.
            return array_key_exists($role, self::SRD_DELEGATE_ROLES)
                && ($approval->assigned_to_srd === null || $approval->assigned_to_srd === $userId);
        }

        return match ($role) {
            'master' => $approval->cheif_ofcr_app !== null && $approval->master_app === null && $sameVessel(),
            'chief-engineer' => $approval->second_eng_app !== null && $approval->chief_eng_app === null && $sameVessel(),

            // GM holds it unless it's currently out with a delegate.
            'gm-srd' => $forwardedAshore && ! $this->srdDelegationPending($approval),

            'dgm-srd' => $this->delegateHasIt($approval, 'forwarded_to_dgm_srd', 'dgm_srd_app', $userId),
            'agm-srd' => $this->delegateHasIt($approval, 'forwarded_to_agm_by_gm_srd', 'agm_app', $userId),
            'am-srd' => $this->delegateHasIt($approval, 'forwarded_to_am_by_agm_srd', 'ast_m_app', $userId),
            'superintendent-srd' => $this->delegateHasIt($approval, 'forwarded_to_superintendent_srd', 'superintendent_srd_app', $userId),

            default => false,
        };
    }

    private function delegateHasIt($approval, string $forwarded, string $reviewed, ?int $userId): bool
    {
        return $approval->{$forwarded} !== null
            && $approval->{$reviewed} === null
            && ($approval->assigned_to_srd === null || $approval->assigned_to_srd === $userId);
    }

    /**
     * The role string of whichever delegate GM handed this to, or null if it
     * has not been delegated. Read off the forwarded_to_* columns rather than
     * the assignee's current role, so a later role change can't retarget a
     * sign-off that belongs to the delegation as it was made.
     */
    public function assignedDelegateRole(): ?string
    {
        $approval = $this->approval;
        if (! $approval) {
            return null;
        }

        foreach (self::SRD_DELEGATE_ROLES as $role => [$forwarded, $reviewed, $label]) {
            if ($approval->{$forwarded} !== null) {
                return $role;
            }
        }

        return null;
    }

    /** One of GM's delegates currently has it on their desk. */
    public function srdDelegationPending($approval = null): bool
    {
        $approval = $approval ?: $this->approval;
        if (! $approval) {
            return false;
        }

        foreach (self::SRD_DELEGATE_COLUMNS as [$forwarded, $reviewed]) {
            if ($approval->{$forwarded} !== null && $approval->{$reviewed} === null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Everyone who has signed it, in the order they signed - what the
     * Authorisation block on the detail page prints. Ordered by chain
     * position rather than by role, so the block reads the way the
     * requisition actually travelled.
     *
     * @return array<int,array{role:string,user:?User}>
     */
    public function signatories(): array
    {
        $approval = $this->approval;
        if (! $approval) {
            return [];
        }

        $steps = [
            ['Chief Officer', $approval->cheif_ofcr_app],
            ['Second Engineer', $approval->second_eng_app],
            ['Master', $approval->master_app],
            ['Chief Engineer', $approval->chief_eng_app],
        ];

        foreach (self::SRD_DELEGATE_COLUMNS as $name => [$forwarded, $reviewed]) {
            $steps[] = [$name, $approval->{$reviewed}];
        }

        $steps[] = ['GM (SRD)', $approval->gm_app];

        $signed = [];
        foreach ($steps as [$label, $userId]) {
            if ($userId !== null) {
                $signed[] = ['role' => $label, 'user' => User::find($userId)];
            }
        }

        return $signed;
    }

    /** A delegate has already reviewed it - GM then only approves, never re-delegates. */
    public function srdDelegateReviewed(): bool
    {
        $approval = $this->approval;
        if (! $approval) {
            return false;
        }

        foreach (self::SRD_DELEGATE_COLUMNS as [$forwarded, $reviewed]) {
            if ($approval->{$reviewed} !== null) {
                return true;
            }
        }

        return false;
    }
}
