<?php

namespace App;

use App\Category;
use App\OrderItem;
use App\Vessel;
use App\OrderApproval;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
	/**
	 * GM (SRD)'s four possible delegates, as [forwarded-to column, reviewed
	 * column]. A forwarded_to_* column set with no matching reviewed column
	 * means it's currently sitting on that delegate's desk. Single source of
	 * truth for both currentStageLabel() and hasPendingActionFor() - they used
	 * to each keep their own copy of this list, which is exactly how GM kept
	 * seeing Approve/Forward for an order they'd just delegated away.
	 */
	private const SRD_DELEGATE_COLUMNS = [
		'AGM (SRD)' => ['forwarded_to_agm_by_gm_srd', 'agm_app'],
		'AM (SRD)' => ['forwarded_to_am_by_agm_srd', 'ast_m_app'],
		'DGM (SRD)' => ['forwarded_to_dgm_srd', 'dgm_srd_app'],
		'Superintendent (SRD)' => ['forwarded_to_superintendent_srd', 'superintendent_srd_app'],
	];


	public function vessel(){
		return $this->belongsTo(Vessel::class);
	}
	public function orderItems(){
		return $this->hasMany(OrderItem::class);
	}
	public function category(){
		return $this->belongsTo(Category::class);
	}
	public function budgetGroup(){
		return $this->belongsTo(BudgetGroup::class);
	}
	public function orderApproval()
    {
        return $this->hasOne(OrderApproval::class);
    }

	/** created_by stores the creator's user id, not a name. */
	public function creator()
	{
		return $this->belongsTo(User::class, 'created_by');
	}

	/** Whoever rejected it, if anyone did. */
	public function rejectedBy()
	{
		return $this->belongsTo(User::class, 'rejected_by');
	}

	/**
	 * Rejection is terminal - nobody acts on it again, it leaves every queue,
	 * and the originator raises a fresh requisition instead. Checked first by
	 * both hasPendingActionFor() and currentStageLabel().
	 */
	public function isRejected(): bool
	{
		return $this->status === 'rejected';
	}

	/** Completed procurement stages, oldest first - the timeline. */
	public function procurementSteps()
	{
		return $this->hasMany(ProcurementStep::class)->orderBy('completed_at');
	}

	public function invoice()
	{
		return $this->hasOne(OrderInvoice::class);
	}

	/** The saved parts (A/B/C) of this requisition's approval form. */
	/**
	 * Everyone who has signed this requisition, in the order they signed -
	 * what the Authorisation block and the printed form list.
	 *
	 * Ordered by chain position rather than by role, so it reads the way the
	 * requisition actually travelled. Reads the approval columns directly
	 * instead of walking every Role in the system and testing each one's user
	 * id against them, which is what the order detail page does.
	 *
	 * @return array<int,array{role:string,user:?User}>
	 */
	public function signatories(): array
	{
		$approval = $this->orderApproval;
		if (! $approval) {
			return [];
		}

		$steps = [
			['Chief Officer', $approval->cheif_ofcr_app],
			['Second Engineer', $approval->second_eng_app],
			['Master', $approval->master_app],
			['Chief Engineer', $approval->chief_eng_app],
			['DGM (SRD)', $approval->dgm_srd_app],
			['AGM (SRD)', $approval->agm_app],
			['AM (SRD)', $approval->ast_m_app],
			['Superintendent (SRD)', $approval->superintendent_srd_app],
			['GM (SRD)', $approval->gm_app],
			['DGM (SSM)', $approval->dgm_app_ssm],
			['AGM (SSM)', $approval->agm_app_ssm],
			['AM (SSM)', $approval->am_app_ssm],
			['Superintendent (SSM)', $approval->superintendent_ssm_app],
			// Cross-cutting sign-offs - they never gate the chain, but if one
			// was given it belongs on the printed form like any other.
			['Technical Superintendent', $approval->tech_superintendent_app],
			['Marine Superintendent', $approval->marine_superintendent_app],
		];

		$signed = [];
		foreach ($steps as [$label, $userId]) {
			if ($userId !== null) {
				$signed[] = ['role' => $label, 'user' => User::find($userId)];
			}
		}

		return $signed;
	}

	public function formParts()
	{
		return $this->hasMany(OrderFormPart::class);
	}

	/** One part of the form by letter, or null if it hasn't been started. */
	public function formPart(string $part): ?OrderFormPart
	{
		return $this->formParts->firstWhere('part', $part);
	}

	/** Has this requisition entered the SSM procurement workflow at all? */
	public function inProcurement(): bool
	{
		return ProcurementStage::exists($this->procurement_stage);
	}

	public function procurementClosed(): bool
	{
		return $this->procurement_stage === ProcurementStage::CLOSED;
	}

	/**
	 * Whether a procurement stage has already been completed - the guard
	 * against completing the same stage twice from a stale page.
	 */
	public function hasCompletedStage(string $stage): bool
	{
		return $this->procurementSteps()->where('step', $stage)->exists();
	}

	/**
	 * A human-readable label for where this requisition currently sits in
	 * the approval chain - computed from orderApproval's columns, never
	 * stored, and never touches the free-text `status` column (other code
	 * already compares that against literal strings like 'delivered', so
	 * repurposing it would be risky). Checked in order from latest stage
	 * to earliest, so the most specific true condition wins.
	 */
	public function currentStageLabel(): string
	{
		// Terminal, and checked before everything else - a rejected
		// requisition has no live stage, whatever the chain columns or the
		// procurement stage it was frozen at still say.
		if ($this->isRejected()) {
			return 'Rejected';
		}

		// Once it's in procurement, the sub-stage IS the answer - otherwise
		// this reported a static "With SSM Officers for Final Action" for the
		// weeks a tender actually takes.
		if ($this->inProcurement()) {
			if ($this->procurementClosed()) {
				return 'Closed';
			}

			return ProcurementStage::owner($this->procurement_stage) === ProcurementStage::OWNER_SHIP
				? 'Delivered — Awaiting Master Confirmation'
				: ProcurementStage::label($this->procurement_stage);
		}

		// Requisitions that never entered procurement (they predate it, or
		// haven't reached DGM SSM yet) keep the original status-based labels.
		if ($this->status === 'received') {
			return 'Closed';
		}

		if ($this->status === 'delivered') {
			return 'Delivered — Awaiting Master Confirmation';
		}

		$approval = $this->orderApproval;
		if (! $approval) {
			return 'Awaiting Officer Approval';
		}

		if ($approval->dgm_app_ssm !== null) {
			if ($approval->assigned_to_ssm !== null) {
				$assignee = User::find($approval->assigned_to_ssm);

				return $assignee
					? 'Assigned to '.$assignee->name.' for Final Action'
					: 'With SSM Officers for Final Action';
			}

			return 'With SSM Officers for Final Action';
		}

		if ($approval->gm_app !== null) {
			return 'Awaiting DGM (SSM)';
		}

		// SRD delegate stage. A delegate's *_app column being set means they
		// have FINISHED reviewing (it's back with GM for the actual approval);
		// a forwarded_to_* column set with no matching *_app means it's still
		// sitting on their desk. Checked reviewed-first, since once reviewed
		// both columns are set.
		foreach (self::SRD_DELEGATE_COLUMNS as $name => [$forwardedColumn, $reviewedColumn]) {
			if ($approval->{$reviewedColumn} !== null) {
				// The column just stores whoever's user id reviewed it - normally
				// that's the delegate themselves, but a Technical/Marine
				// Superintendent can stand in for a stuck delegate (see
				// RoleController::approveRequisition()), so check who it really
				// was rather than assuming it matches the delegate's own role.
				$reviewer = User::find($approval->{$reviewedColumn});
				$reviewerRole = $reviewer->role->role ?? null;
				if (in_array($reviewerRole, ['technical-superintendent', 'marine-superintendent'], true)) {
					$standInLabel = $reviewerRole === 'technical-superintendent' ? 'Technical Superintendent' : 'Marine Superintendent';

					return $standInLabel.' reviewed on behalf of '.$name.' — Awaiting GM (SRD)';
				}

				return 'Reviewed by '.$name.' — Awaiting GM (SRD)';
			}
		}

		foreach (self::SRD_DELEGATE_COLUMNS as $name => [$forwardedColumn, $reviewedColumn]) {
			if ($approval->{$forwardedColumn} !== null) {
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
	 * Whether this role has an action to take on this requisition *right now* -
	 * used to decide whether to show them the Approve/Forward buttons. This
	 * deliberately mirrors the per-role pending queues in RoleController, so a
	 * button only appears when the order would also be sitting in that role's
	 * pending list: not before their turn, and not once they've signed off.
	 */
	public function hasPendingActionFor(?string $role, ?int $userId = null): bool
	{
		$approval = $this->orderApproval;

		// Terminal. This single guard is what removes Approve, Forward,
		// Assign, Confirm Receipt, the procurement stage panel AND the
		// Reject button itself from a rejected requisition, for every role
		// at once - they are all gated on this method.
		if ($this->isRejected()) {
			return false;
		}

		if (! $approval || $role === null) {
			return false;
		}

		// 'received' only means finished for requisitions that never entered
		// procurement. Once they do, it means goods are confirmed on board and
		// there are still four stages to run (Invoice Verification onwards) -
		// returning false here was what would have left the SSM officer with
		// no button at all and no visible reason why.
		if (! $this->inProcurement() && $this->status === 'received') {
			return false;
		}

		// Technical/Marine Superintendent sit alongside the chain rather than
		// in it - they can acknowledge any live requisition, once each.
		if ($role === 'technical-superintendent') {
			return $approval->tech_superintendent_app === null;
		}
		if ($role === 'marine-superintendent') {
			return $approval->marine_superintendent_app === null;
		}

		// In procurement the current stage decides everything: Receipt &
		// Verification belongs to the Master, every other stage to the officer
		// DGM assigned it to, and 'closed' to nobody.
		if ($this->inProcurement()) {
			$owner = ProcurementStage::owner($this->procurement_stage);

			if ($owner === ProcurementStage::OWNER_SHIP) {
				return $role === 'master';
			}

			if ($owner === ProcurementStage::OWNER_SSM) {
				return in_array($role, ['agm-ssm', 'am-ssm', 'superintendent-ssm'], true)
					&& ($approval->assigned_to_ssm === null || $approval->assigned_to_ssm === $userId);
			}

			return false;
		}

		// Master always closes the loop once it's delivered, whoever raised
		// it - and that's the only action anyone has left at that point.
		if ($this->status === 'delivered') {
			return $role === 'master';
		}

		$forwardedAshore = $approval->master_app !== null || $approval->chief_eng_app !== null;

		return match ($role) {
			// Origin: whoever raised it signs off first (the wizard now does
			// this on submit, so this mainly covers older requisitions).
			'chief-officer' => $this->created_by_role === 'chief-officer' && $approval->cheif_ofcr_app === null,
			'second-engineer' => $this->created_by_role === 'second-engineer' && $approval->second_eng_app === null,

			// Deck goes via Master, engine room via Chief Engineer.
			'master' => $approval->cheif_ofcr_app !== null && $approval->master_app === null,
			'chief-engineer' => $approval->second_eng_app !== null && $approval->chief_eng_app === null,

			// GM (SRD) holds the approval - but only while it's actually on
			// their desk. Once they've delegated it out, it's on the
			// delegate's desk instead and GM has nothing to do until that
			// delegate reviews it (at which point the *_app column is set,
			// srdDelegationPending() goes false, and Approve reappears).
			'gm-srd' => $forwardedAshore && $approval->gm_app === null && ! $this->srdDelegationPending($approval),

			// SRD delegates only act on what GM actually delegated to THEM
			// specifically - assigned_to_srd null means it predates named
			// assignment (falls back to visible-to-the-whole-role), otherwise
			// it must match this exact person. Without this check, a second
			// person sharing the same role (there are 2 AGM-SRD, 4 AM-SRD in
			// this fleet) would see Approve/Reject for a requisition GM
			// delegated to their colleague, not to them - disagreeing with
			// RoleController::pendingRequisition(), which already scopes this way.
			'agm-srd' => $forwardedAshore && $approval->gm_app === null
				&& $approval->forwarded_to_agm_by_gm_srd !== null && $approval->agm_app === null
				&& ($approval->assigned_to_srd === null || $approval->assigned_to_srd === $userId),
			'am-srd' => $forwardedAshore && $approval->gm_app === null
				&& $approval->forwarded_to_am_by_agm_srd !== null && $approval->ast_m_app === null
				&& ($approval->assigned_to_srd === null || $approval->assigned_to_srd === $userId),
			'dgm-srd' => $forwardedAshore && $approval->gm_app === null
				&& $approval->forwarded_to_dgm_srd !== null && $approval->dgm_srd_app === null
				&& ($approval->assigned_to_srd === null || $approval->assigned_to_srd === $userId),
			'superintendent-srd' => $forwardedAshore && $approval->gm_app === null
				&& $approval->forwarded_to_superintendent_srd !== null && $approval->superintendent_srd_app === null
				&& ($approval->assigned_to_srd === null || $approval->assigned_to_srd === $userId),

			// DGM (SSM)'s action is assigning it to a named SSM officer.
			'dgm-ssm' => $approval->gm_app !== null && $approval->dgm_app_ssm === null,

			// Final action belongs to whoever DGM assigned it to. A null
			// assignee means it predates named assignment, so any of the
			// three can still pick it up.
			'agm-ssm', 'am-ssm', 'superintendent-ssm' => $approval->gm_app !== null
				&& $approval->dgm_app_ssm !== null
				&& ($approval->assigned_to_ssm === null || $approval->assigned_to_ssm === $userId)
				&& $approval->agm_app_ssm === null
				&& $approval->am_app_ssm === null
				&& $approval->superintendent_ssm_app === null,

			default => false,
		};
	}

	/**
	 * Whether one of GM (SRD)'s four delegates currently has this requisition
	 * on their desk - forwarded to them, not yet reviewed. Used to hide GM's
	 * own Approve/Delegate/Forward buttons while it's out of their hands.
	 */
	private function srdDelegationPending(OrderApproval $approval): bool
	{
		foreach (self::SRD_DELEGATE_COLUMNS as [$forwardedColumn, $reviewedColumn]) {
			if ($approval->{$forwardedColumn} !== null && $approval->{$reviewedColumn} === null) {
				return true;
			}
		}

		return false;
	}
}
