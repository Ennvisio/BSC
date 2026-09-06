<?php

namespace App;

use App\Category;
use App\OrderItem;
use App\Vessel;
use App\OrderApproval;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
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
		$srdDelegates = [
			'AGM (SRD)' => ['forwarded_to_agm_by_gm_srd', 'agm_app'],
			'AM (SRD)' => ['forwarded_to_am_by_agm_srd', 'ast_m_app'],
			'DGM (SRD)' => ['forwarded_to_dgm_srd', 'dgm_srd_app'],
			'Superintendent (SRD)' => ['forwarded_to_superintendent_srd', 'superintendent_srd_app'],
		];

		foreach ($srdDelegates as $name => [$forwardedColumn, $reviewedColumn]) {
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

		foreach ($srdDelegates as $name => [$forwardedColumn, $reviewedColumn]) {
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

		if (! $approval || $role === null || $this->status === 'received') {
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

			// GM (SRD) holds the approval regardless of any delegate review.
			'gm-srd' => $forwardedAshore && $approval->gm_app === null,

			// SRD delegates only act on what GM actually delegated to them.
			'agm-srd' => $forwardedAshore && $approval->gm_app === null
				&& $approval->forwarded_to_agm_by_gm_srd !== null && $approval->agm_app === null,
			'am-srd' => $forwardedAshore && $approval->gm_app === null
				&& $approval->forwarded_to_am_by_agm_srd !== null && $approval->ast_m_app === null,
			'dgm-srd' => $forwardedAshore && $approval->gm_app === null
				&& $approval->forwarded_to_dgm_srd !== null && $approval->dgm_srd_app === null,
			'superintendent-srd' => $forwardedAshore && $approval->gm_app === null
				&& $approval->forwarded_to_superintendent_srd !== null && $approval->superintendent_srd_app === null,

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
}
