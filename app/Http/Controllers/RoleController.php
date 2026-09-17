<?php

namespace App\Http\Controllers;

use App\Category;
use App\Item;
use App\Order;
use App\OrderItem;
use App\ProcurementStage;
use App\ProcurementStep;
use App\Services\StockService;
use App\User;
use App\Vessel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\OrderApproval;

class RoleController extends Controller
{
	/**
	 * Same guard HomeController applies. Auth is wired per-controller in this
	 * app rather than on the route groups, so without this every approval
	 * endpoint here - approve, forward, assign - is reachable by a guest and
	 * fatals on auth()->user()->role instead of redirecting to login.
	 */
	public function __construct()
	{
		$this->middleware('auth');
	}

	public $approved_by_cfiefOfcr = 'approved by chief-officer';
	public $approved_by_second_eng = 'approved by second-engineer';
	public $approved_by_master = 'approved by master';
	public $approved_by_chief_eng = 'approved by chief-engineer';
	public $approved_by_srd_ast_m = 'approved by srd-assistant-manager';
	public $approved_by_srd_ag_m = 'approved by srd-assistant-general-manager';
	public $approved_by_srd_g_m = 'approved by srd-general-manager';
	public $approved_by_ssm_dg_m = 'approved by ssm-deputy-general-manager';
	public $approved_by_ssm_ag_m = 'approved by ssm-assistant-general-manager';
	public $forwarded_to_agm_by_srd_gm = " forwarded to Asst. General Manager (srd) by General-manager (srd)";
	public $forwarded_to_ast_m_by_srd_agm = " forwarded to Asst. Manager (srd) by Asst. General-manager (srd)";
	public $approved_by_srd_dgm = 'approved by srd-deputy-general-manager';
	public $approved_by_srd_superintendent = 'approved by srd-superintendent';
	public $forwarded_by_srd_gm = ' forwarded ashore by General-manager (srd)';
	public $approved_by_ssm_a_m = 'delivered';
	public $approved_by_secondEngineer = 'received';

	public function createdOrder()
	{
		return $orders = Order::where('status', 'ready')
			->where('vessel_id', auth()->user()->role->vessel->id)
			->where('created_by_role', auth()->user()->role->role)
			->orderBy('updated_at', 'desc')
			->where('ord_status', true)
			->get();
	}

	public function secondEngineerOrOfficerApproved()
	{
		return $orders = Order::where('status', $this->approved_by_secondEngineer)
			->where('ord_status', true)
			->where('created_by_role', auth()->user()->role->role)
			->whereHas('orderApproval', function ($q) {
				$q->where(function ($query) {
					$query->where('master_app', '!=', null)
						->orWhere('chief_eng_app', '!=', null);
				})
					->where('cheif_ofcr_app', '!=', null)
					->where('ord_status', true)
					->where('ast_m_app', '!=', null)
					->where('agm_app', '!=', null)
					->where('gm_app', '!=', null)
					->where('dgm_app_ssm', '!=', null)
					->where('agm_app_ssm', '!=', null)
					->where('am_app_ssm', '!=', null);
			})
			->where('vessel_id', auth()->user()->role->vessel->id)
			->orderBy('updated_at', 'desc')
			->get();
	}
	/**
	 * Which requisitions a ship user tracks, before any lifecycle filtering.
	 *
	 * Always their own vessel, then narrowed by department: the officer who
	 * raises them sees their own, and the officer who signs them off sees the
	 * ones they're responsible for. Master is the exception - they close the
	 * loop on EVERY delivery regardless of origin (deck or engine room), so
	 * scoping them to deck alone would hide engine-room deliveries they still
	 * have to confirm receipt for.
	 *
	 * Public so HomeController@index can build the dashboard's stat-card
	 * counts from the exact same scope - otherwise a card's number and the
	 * list it links to can disagree, which is worse than not having the
	 * count at all.
	 */
	public function shipTrackingScope()
	{
		$role = auth()->user()->role->role;

		$query = Order::where('ord_status', true)->where('status', '!=', 'rejected')
			->where('vessel_id', auth()->user()->role->vessel_id);

		if ($role === 'chief-officer' || $role === 'second-engineer') {
			// Only what this officer raised themselves.
			return $query->where('created_by_role', $role);
		}

		if ($role === 'chief-engineer') {
			// The engine room's - what they approve and forward.
			return $query->where('created_by_role', 'second-engineer');
		}

		// Master: the whole vessel.
		return $query;
	}

	/**
	 * Delivered/Received bucket for a ship user - the loop is closed.
	 *
	 * Lives here rather than in HomeController (which owns the route) so all
	 * three ship buckets share one definition of who-sees-what.
	 */
	public function shipReceivedRequisitions()
	{
		return $this->shipTrackingScope()
			->where('status', 'received')
			->orderBy('updated_at', 'desc')
			->get();
	}

	/**
	 * Every role's own approval history - every requisition THEY personally
	 * approved or delegated, at whatever stage it's at now. Deliberately
	 * separate from the Pending/Approved/Delivered lifecycle pages and the
	 * shore-side action queues: "did I act on this" and "has the NEXT stage
	 * acted on it" are different questions, and conflating them meant landing
	 * someone on a page where the order they just acted on wasn't there yet
	 * (it hadn't reached whoever comes after them). Not meaningful for Chief
	 * Officer/Second Engineer - they don't approve anyone else's
	 * requisition, so they never reach this page. DGM (SSM)'s "action" is
	 * assigning to a named officer (see assignToSsm()) rather than approving
	 * through approveRequisition(), but it still counts here the same way.
	 */
	public function myApprovals()
	{
		$role = auth()->user()->role->role;
		$userId = auth()->user()->id;

		$roleLabels = [
			'master' => 'Master',
			'chief-engineer' => 'Chief Engineer',
			'gm-srd' => 'GM (SRD)',
			'dgm-srd' => 'DGM (SRD)',
			'agm-srd' => 'AGM (SRD)',
			'am-srd' => 'AM (SRD)',
			'superintendent-srd' => 'Superintendent (SRD)',
			'dgm-ssm' => 'DGM (SSM)',
			'agm-ssm' => 'AGM (SSM)',
			'am-ssm' => 'AM (SSM)',
			'superintendent-ssm' => 'Superintendent (SSM)',
		];
		$listTitle = ($roleLabels[$role] ?? ucfirst(str_replace('-', ' ', (string) $role))).' Approvals';

		// Ship side: Master/Chief Engineer, scoped to their own vessel only,
		// same as every other ship-side list.
		if (in_array($role, ['master', 'chief-engineer'], true)) {
			$column = $role === 'master' ? 'master_app' : 'chief_eng_app';

			$orders = Order::where('ord_status', true)
				->where('vessel_id', auth()->user()->role->vessel_id)
				->whereHas('orderApproval', fn ($q) => $q->where($column, $userId))
				->orderBy('updated_at', 'desc')
				->get();

			$drafts = collect();

			return view('layouts.ship-home', compact('orders', 'drafts', 'listTitle'));
		}

		// Shore side: not vessel-scoped, one column per role except GM (SRD),
		// who can act two different ways - approving directly (gm_app) or
		// delegating to one of the four SRD reviewers (forwarded_to_*) - so
		// either counts as "GM acted on this".
		$shoreColumns = [
			'dgm-srd' => 'dgm_srd_app',
			'agm-srd' => 'agm_app',
			'am-srd' => 'ast_m_app',
			'superintendent-srd' => 'superintendent_srd_app',
			'dgm-ssm' => 'dgm_app_ssm',
			'agm-ssm' => 'agm_app_ssm',
			'am-ssm' => 'am_app_ssm',
			'superintendent-ssm' => 'superintendent_ssm_app',
		];

		if ($role === 'gm-srd') {
			$orders = Order::where('ord_status', true)
				->whereHas('orderApproval', function ($q) use ($userId) {
					$q->where('gm_app', $userId)
						->orWhere('forwarded_to_dgm_srd', $userId)
						->orWhere('forwarded_to_agm_by_gm_srd', $userId)
						->orWhere('forwarded_to_am_by_agm_srd', $userId)
						->orWhere('forwarded_to_superintendent_srd', $userId);
				})
				->orderBy('updated_at', 'desc')
				->get();
		} elseif (array_key_exists($role, $shoreColumns)) {
			$column = $shoreColumns[$role];
			$orders = Order::where('ord_status', true)
				->whereHas('orderApproval', fn ($q) => $q->where($column, $userId))
				->orderBy('updated_at', 'desc')
				->get();
		} else {
			$orders = collect();
		}

		$items = Item::orderBy('created_at', 'desc')->where('status', true)->get();
		$categories = Category::orderBy('created_at', 'desc')->where('status', true)->get();
		$vessels = Vessel::orderBy('created_at', 'desc')->where('status', true)->get();

		return view('layouts.order', compact('orders', 'items', 'categories', 'vessels', 'listTitle'));
	}

	public function pendingRequisition()
	{
		$items = Item::orderBy('created_at', 'desc')->where('status', true)->get();
		$categories = Category::orderBy('created_at', 'desc')->where('status', true)->get();
		$vessels = Vessel::orderBy('created_at', 'desc')->where('status', true)->get();
		$drafts = collect();
		if (auth()->user()->role->user_type == 'ship') {
			// Ship side tracks a requisition's LIFECYCLE rather than running an
			// approval queue: Pending -> Approved -> Delivered, with every
			// requisition in exactly one of the three at any moment. See
			// shipTrackingScope() for who sees which requisitions.
			//
			// Drafts (status='draft', ord_status=false) live here too, in their
			// own table - they're deliberately invisible in the approval
			// queries, but whoever started one still needs to find and resume it.
			$drafts = Order::where('status', 'draft')
				->where('created_by', auth()->user()->id)
				->orderBy('updated_at', 'desc')
				->get();

			// Pending: submitted and still moving through the chain - anything
			// that hasn't reached SSM's final action yet.
			$orders = $this->shipTrackingScope()
				->whereNotIn('status', ['draft', 'delivered', 'received'])
				->orderBy('updated_at', 'desc')
				->get();

			$listTitle = 'Pending Requisitions';

			return view('layouts.ship-home', compact('orders', 'drafts', 'listTitle'));
		} else {
			// The four SRD delegate roles share one shape: only orders GM
			// actually delegated to that role, that this specific person hasn't
			// reviewed yet, and that GM hasn't already approved past. Scoped to
			// whoever GM actually named (assigned_to_srd) - null means it was
			// delegated before named assignment existed, so it still falls back
			// to visible-to-everyone-in-that-role rather than stranding it.
			if (auth()->user()->role->role == 'am-srd') {
				$userId = auth()->user()->id;
				$orders = Order::where('ord_status', true)->where('status', '!=', 'rejected')
					->whereHas('orderApproval', function ($q) use ($userId) {
						$q->where(function ($query) {
							$query->where('master_app', '!=', null)
								->orWhere('chief_eng_app', '!=', null);
						})
							->where('forwarded_to_am_by_agm_srd', '!=', null)
							->where(function ($query) use ($userId) {
								$query->where('assigned_to_srd', $userId)
									->orWhereNull('assigned_to_srd');
							})
							->where('ast_m_app', '=', null);
					})
					->orderBy('updated_at', 'desc')
					->get();
			} elseif (auth()->user()->role->role == 'agm-srd') {
				// (This previously carried an orWhere() for the old "AM sends it
				// back up to AGM" hop - AGM no longer forwards to AM at all, and
				// because AND binds tighter than OR in SQL that stray clause
				// collapsed the whole filter down to just "master or chief
				// engineer approved it", so AGM was seeing effectively every
				// order.)
				$userId = auth()->user()->id;
				$orders = Order::where('ord_status', true)->where('status', '!=', 'rejected')
					->whereHas('orderApproval', function ($q) use ($userId) {
						$q->where(function ($query) {
							$query->where('master_app', '!=', null)
								->orWhere('chief_eng_app', '!=', null);
						})
							->where('forwarded_to_agm_by_gm_srd', '!=', null)
							->where(function ($query) use ($userId) {
								$query->where('assigned_to_srd', $userId)
									->orWhereNull('assigned_to_srd');
							})
							->where('gm_app', '=', null)
							->where('agm_app', '=', null);
					})
					->orderBy('updated_at', 'desc')
					->get();
			} elseif (auth()->user()->role->role == 'dgm-srd') {
				$userId = auth()->user()->id;
				$orders = Order::where('ord_status', true)->where('status', '!=', 'rejected')
					->whereHas('orderApproval', function ($q) use ($userId) {
						$q->where(function ($query) {
							$query->where('master_app', '!=', null)
								->orWhere('chief_eng_app', '!=', null);
						})
							->where('forwarded_to_dgm_srd', '!=', null)
							->where(function ($query) use ($userId) {
								$query->where('assigned_to_srd', $userId)
									->orWhereNull('assigned_to_srd');
							})
							->where('dgm_srd_app', '=', null);
					})
					->orderBy('updated_at', 'desc')
					->get();
			} elseif (auth()->user()->role->role == 'superintendent-srd') {
				$userId = auth()->user()->id;
				$orders = Order::where('ord_status', true)->where('status', '!=', 'rejected')
					->whereHas('orderApproval', function ($q) use ($userId) {
						$q->where(function ($query) {
							$query->where('master_app', '!=', null)
								->orWhere('chief_eng_app', '!=', null);
						})
							->where('forwarded_to_superintendent_srd', '!=', null)
							->where(function ($query) use ($userId) {
								$query->where('assigned_to_srd', $userId)
									->orWhereNull('assigned_to_srd');
							})
							->where('superintendent_srd_app', '=', null);
					})
					->orderBy('updated_at', 'desc')
					->get();
			} elseif (auth()->user()->role->role == 'gm-srd') {
				$orders = Order::where('ord_status', true)->where('status', '!=', 'rejected')
					->whereHas('orderApproval', function ($q) {
						$q->where(function ($query) {
							$query->where('master_app', '!=', null)
								->orWhere('chief_eng_app', '!=', null);
						})
							->where('gm_app', '=', null)
							// Not while it's out with a delegate for review -
							// see Order::srdDelegationPending(). Mirrors
							// hasPendingActionFor()'s 'gm-srd' case so this list
							// and the detail page's buttons never disagree.
							->where(function ($query) {
								$query->where(function ($q2) {
									$q2->whereNull('forwarded_to_agm_by_gm_srd')->orWhereNotNull('agm_app');
								})->where(function ($q2) {
									$q2->whereNull('forwarded_to_am_by_agm_srd')->orWhereNotNull('ast_m_app');
								})->where(function ($q2) {
									$q2->whereNull('forwarded_to_dgm_srd')->orWhereNotNull('dgm_srd_app');
								})->where(function ($q2) {
									$q2->whereNull('forwarded_to_superintendent_srd')->orWhereNotNull('superintendent_srd_app');
								});
							});
					})
					->orderBy('updated_at', 'desc')
					->get();
			} elseif (auth()->user()->role->role == 'dgm-ssm') {
				$orders = Order::where('ord_status', true)->where('status', '!=', 'rejected')
					->whereHas('orderApproval', function ($q) {
						$q->where(function ($query) {
							$query->where('master_app', '!=', null)
								->orWhere('chief_eng_app', '!=', null);
						})
							->where('gm_app', '!=', null)
							->where('dgm_app_ssm', '=', null);
					})
					->orderBy('updated_at', 'desc')
					->get();
			}
			// SSM final action. DGM (SSM) assigns the requisition to one named
			// officer, so all three roles share the same queue definition:
			// "assigned to me, and nobody has taken the final action yet".
			// assigned_to_ssm being null means it predates named assignment -
			// those fall back to the old behaviour of all three seeing it.
			elseif (in_array(auth()->user()->role->role, ['agm-ssm', 'am-ssm', 'superintendent-ssm'], true)) {
				$userId = auth()->user()->id;
				$assignedToMe = function ($query) use ($userId) {
					$query->where('assigned_to_ssm', $userId)->orWhereNull('assigned_to_ssm');
				};

				$orders = Order::where('ord_status', true)->where('status', '!=', 'rejected')
					->where(function ($outer) use ($assignedToMe) {
						// In procurement, the stage alone says whose turn it
						// is - and it comes BACK to this queue after the ship
						// confirms receipt, for Invoice Verification onwards.
						$outer->where(function ($q) use ($assignedToMe) {
							$q->whereIn('procurement_stage', ProcurementStage::ssmStages())
								->whereHas('orderApproval', fn ($a) => $a->where($assignedToMe));
						})
							// Predates the procurement workflow: fall back to
							// the old "assigned, and nobody has taken the final
							// action yet" condition so nothing in flight is
							// stranded.
							->orWhere(function ($q) use ($assignedToMe) {
								$q->whereNull('procurement_stage')
									->whereHas('orderApproval', function ($a) use ($assignedToMe) {
										$a->where(function ($query) {
											$query->where('master_app', '!=', null)
												->orWhere('chief_eng_app', '!=', null);
										})
											->where('gm_app', '!=', null)
											->where('dgm_app_ssm', '!=', null)
											->where($assignedToMe)
											->where('agm_app_ssm', '=', null)
											->where('am_app_ssm', '=', null)
											->where('superintendent_ssm_app', '=', null);
									});
							});
					})
					->orderBy('updated_at', 'desc')
					->get();
			}
			// Technical/Marine Superintendent: standing, cross-vessel visibility
			// at any stage - every active requisition not yet fully closed,
			// regardless of which role currently holds it. Purely informational
			// (see approveRequisition()) - never gates the normal chain.
			elseif (auth()->user()->role->role == 'technical-superintendent' || auth()->user()->role->role == 'marine-superintendent') {
				$orders = Order::where('ord_status', true)->where('status', '!=', 'rejected')
					// "Not yet closed" used to be status != 'received'. Once a
					// requisition is in procurement that's no longer the end of
					// it: 'received' means the goods are on board, with Invoice
					// Verification, Finance Clearance and Payment still to run.
					->where(function ($q) {
						$q->where('status', '!=', 'received')
							->orWhere(function ($inner) {
								$inner->whereNotNull('procurement_stage')
									->where('procurement_stage', '!=', ProcurementStage::CLOSED);
							});
					})
					->orderBy('updated_at', 'desc')
					->get();
			}
			return view('layouts.order', compact('orders', 'items', 'categories', 'vessels'));
		}
	}
	public function approvedRequisition()
	{
		$items = Item::orderBy('created_at', 'desc')->where('status', true)->get();
		$categories = Category::orderBy('created_at', 'desc')->where('status', true)->get();
		$vessels = Vessel::orderBy('created_at', 'desc')->where('status', true)->get();
		$drafts = collect();

		// Ship side: "Approved" means SSM has taken the final action. That's
		// the same moment the order becomes status='delivered' - SSM approving
		// and the goods being supplied are one event in this workflow, so the
		// bucket keys off that. Drafts belong on Pending only, so none are
		// loaded here.
		if (auth()->user()->role->user_type == 'ship') {
			$orders = $this->shipTrackingScope()
				->where('status', 'delivered')
				->orderBy('updated_at', 'desc')
				->get();

			$listTitle = 'Approved Requisitions';

			return view('layouts.ship-home', compact('orders', 'drafts', 'listTitle'));
		}

		if (auth()->user()->role->role == 'am-srd') {
			$orders = Order::where('ord_status', true)->where('status', '!=', 'rejected')
				->whereHas('orderApproval', function ($q) {
					$q->where(function ($query) {
						$query->where('master_app', '!=', null)
							->orWhere('chief_eng_app', '!=', null);
					})
						->where('forwarded_to_am_by_agm_srd', '!=', null)
						->where('ast_m_app', '!=', null);
				})
				->orderBy('updated_at', 'desc')
				->get();
		}
		elseif (auth()->user()->role->role == 'agm-srd') {
			$orders = Order::where('ord_status', true)->where('status', '!=', 'rejected')
				->whereHas('orderApproval', function ($q) {
					$q->where(function ($query) {
						$query->where('master_app', '!=', null)
							->orWhere('chief_eng_app', '!=', null);
					})
						->where('forwarded_to_agm_by_gm_srd', '!=', null)
						->where('agm_app', '!=', null);
				})
				->orderBy('updated_at', 'desc')
				->get();
		}
		elseif (auth()->user()->role->role == 'dgm-srd') {
			$orders = Order::where('ord_status', true)->where('status', '!=', 'rejected')
				->whereHas('orderApproval', function ($q) {
					$q->where(function ($query) {
						$query->where('master_app', '!=', null)
							->orWhere('chief_eng_app', '!=', null);
					})
						->where('forwarded_to_dgm_srd', '!=', null)
						->where('dgm_srd_app', '!=', null);
				})
				->orderBy('updated_at', 'desc')
				->get();
		}
		elseif (auth()->user()->role->role == 'superintendent-srd') {
			$orders = Order::where('ord_status', true)->where('status', '!=', 'rejected')
				->whereHas('orderApproval', function ($q) {
					$q->where(function ($query) {
						$query->where('master_app', '!=', null)
							->orWhere('chief_eng_app', '!=', null);
					})
						->where('forwarded_to_superintendent_srd', '!=', null)
						->where('superintendent_srd_app', '!=', null);
				})
				->orderBy('updated_at', 'desc')
				->get();
		}
		elseif (auth()->user()->role->role == 'gm-srd') {
			$orders = Order::where('ord_status', true)->where('status', '!=', 'rejected')
				->whereHas('orderApproval', function ($q) {
					$q->where(function ($query) {
						$query->where('master_app', '!=', null)
							->orWhere('chief_eng_app', '!=', null);
					})
						->where('gm_app', '!=', null);
				})
				->orderBy('updated_at', 'desc')
				->get();
		}
		elseif (auth()->user()->role->role == 'dgm-ssm') {
			$orders = Order::where('ord_status', true)->where('status', '!=', 'rejected')
				->whereHas('orderApproval', function ($q) {
					$q->where(function ($query) {
						$query->where('master_app', '!=', null)
							->orWhere('chief_eng_app', '!=', null);
					})
						->where('gm_app', '!=', null)
						->where('dgm_app_ssm', '!=', null);
				})
				->orderBy('updated_at', 'desc')
				->get();
		}
		elseif (auth()->user()->role->role == 'agm-ssm') {
			$orders = Order::where('ord_status', true)->where('status', '!=', 'rejected')
				->whereHas('orderApproval', function ($q) {
					$q->where(function ($query) {
						$query->where('master_app', '!=', null)
							->orWhere('chief_eng_app', '!=', null);
					})
						->where('gm_app', '!=', null)
						->where('dgm_app_ssm', '!=', null)
						->where('agm_app_ssm', '!=', null);
				})
				->orderBy('updated_at', 'desc')
				->get();
		}
		elseif (auth()->user()->role->role == 'am-ssm') {
			$orders = Order::where('ord_status', true)->where('status', '!=', 'rejected')
				->whereHas('orderApproval', function ($q) {
					$q->where(function ($query) {
						$query->where('master_app', '!=', null)
							->orWhere('chief_eng_app', '!=', null);
					})
						->where('gm_app', '!=', null)
						->where('dgm_app_ssm', '!=', null)
						->where('am_app_ssm', '!=', null);
				})
				->orderBy('updated_at', 'desc')
				->get();
		}
		elseif (auth()->user()->role->role == 'superintendent-ssm') {
			$orders = Order::where('ord_status', true)->where('status', '!=', 'rejected')
				->whereHas('orderApproval', function ($q) {
					$q->where(function ($query) {
						$query->where('master_app', '!=', null)
							->orWhere('chief_eng_app', '!=', null);
					})
						->where('gm_app', '!=', null)
						->where('dgm_app_ssm', '!=', null)
						->where('superintendent_ssm_app', '!=', null);
				})
				->orderBy('updated_at', 'desc')
				->get();
		}
		elseif (auth()->user()->role->role == 'technical-superintendent') {
			$orders = Order::where('ord_status', true)->where('status', '!=', 'rejected')
				->whereHas('orderApproval', function ($q) {
					$q->where('tech_superintendent_app', '!=', null);
				})
				->orderBy('updated_at', 'desc')
				->get();
		}
		elseif (auth()->user()->role->role == 'marine-superintendent') {
			$orders = Order::where('ord_status', true)->where('status', '!=', 'rejected')
				->whereHas('orderApproval', function ($q) {
					$q->where('marine_superintendent_app', '!=', null);
				})
				->orderBy('updated_at', 'desc')
				->get();
		}
		// Ship users returned above; everything reaching here is shore side.
		return view('layouts.order', compact('orders', 'items', 'categories', 'vessels'));
	}
	public function approveRequisition(Request $req)
	{
		$order = Order::findOrFail($req->id);

		// A rejected requisition is finished. The button is already hidden
		// (hasPendingActionFor returns false), but this endpoint has never
		// checked whose turn it is - so without this guard a stale tab, a
		// double-click, or a hand-rolled POST could approve one and bring it
		// back to life with a fresh status.
		if ($order->isRejected()) {
			return response()->json([
				'message' => 'This requisition was rejected and can no longer be acted on.',
			], 422);
		}
		// Looking this up by order.id as if it were the approval row's own
		// primary key (rather than matching on order_id) silently breaks the
		// moment the two tables' auto-increment counters drift apart, which
		// they already have for a couple of real orders - order_id is the
		// actual relationship, so that's what this has to match on.
		$order_approval = OrderApproval::where('order_id', $order->id)->firstOrFail();
		$already_approved = false;

		// Receipt confirmation is checked first and independently - Master
		// always closes the loop once an order is 'delivered', regardless of
		// origin. This has to come before the ordinary master_app branch
		// below: master_app is already set by the time an order reaches
		// 'delivered' (it happened at the origin-approval stage), so folding
		// this check in down there would make it unreachable dead code - the
		// same bug that silently broke second-engineer's old receipt path.
		if (auth()->user()->role->role == 'master' && $order->status == 'delivered') {
			// All of this is one transaction on purpose. Marking the order
			// received is what stops this branch ever running again, so if the
			// stock credit failed halfway through afterwards, those quantities
			// would be lost with no way to replay them.
			DB::transaction(function () use ($order, $req) {
				$order->status = 'received';
				$order->rcv_date = Carbon::now();
				// Receipt & Verification in procurement terms - the goods are
				// confirmed on board and it goes back to the SSM officer for
				// Invoice Verification. 'received' therefore no longer means
				// closed for anything in procurement; see Order::currentStageLabel().
				if ($order->procurement_stage === ProcurementStage::RECEIPT_VERIFICATION) {
					$this->recordProcurementStep(
						$order,
						ProcurementStage::RECEIPT_VERIFICATION,
						$req->rcv_remarks,
						(array) $req->input('attachment_ids', [])
					);
				}
				$order->update();

				// Master can adjust each line's Rcv Qty (defaults to Deliver Qty
				// client-side) right here when confirming receipt.
				foreach ((array) $req->rcv_qty as $orderItemId => $qty) {
					if ($qty === '' || $qty === null) {
						continue;
					}
					OrderItem::where('id', $orderItemId)->where('order_id', $order->id)->update([
						'rcv_item_qty' => $qty,
					]);
				}

				// What was actually received goes onto the vessel's stock. This
				// is what keeps ROB honest between the Master's manual
				// corrections - without it, hand-entered figures drift from
				// reality and the number stops being worth showing. Driven off
				// the persisted rcv_item_qty rather than the request, so lines
				// the Master left untouched still credit their delivered qty.
				$stock = app(StockService::class);
				foreach ($order->orderItems()->get() as $line) {
					$receivedQty = (int) ($line->rcv_item_qty ?? $line->del_item_qty ?? 0);
					$stock->addFromReceipt($order->vessel_id, $line->item_id, $receivedQty, auth()->user()->id, Carbon::now());
				}
			});

			$data = "Requested Requisition has been received successfully!";
			return array($data);
		}

		if (auth()->user()->role->role == 'second-engineer' || auth()->user()->role->role == 'chief-officer') {
			// Reason of Requisition belongs to whoever is raising the
			// requisition, not to Master/Chief Engineer reviewing it
			// afterwards. The wizard (RequisitionController::storeStep1)
			// already requires it and sets it before this is ever reached, so
			// this only matters for a pre-wizard order still being manually
			// approved through here.
			$reason = trim((string) $req->reason);
			if ($reason === '' && empty($order->reason)) {
				// A plain array() response here would come back as a 200 OK,
				// which the JS treats as success and redirects away without
				// ever actually saving an approval - a real error status is
				// what lets the client tell the difference and show it as
				// an actual error instead of a fake "Congratulation!".
				return response()->json([
					'message' => 'Please fill in the Reason of Requisition before approving.',
				], 422);
			}

			if (auth()->user()->role->role == 'second-engineer') {
				if ($order_approval->second_eng_app != null) {
					$already_approved = true;
				} else {
					$order->status = $this->approved_by_second_eng;
					if ($reason !== '') {
						$order->reason = $reason;
					}
					$order->update();
					$order_approval->second_eng_app = auth()->user()->id;
					$order_approval->update();
				}
			} else {
				if ($order_approval->cheif_ofcr_app != null) {
					$already_approved = true;
				} else {
					$order->status = $this->approved_by_cfiefOfcr;
					if ($reason !== '') {
						$order->reason = $reason;
					}
					$order->update();
					$order_approval->cheif_ofcr_app = auth()->user()->id;
					$order_approval->update();
				}
			}
		} elseif (auth()->user()->role->role == 'chief-engineer' || auth()->user()->role->role == 'master') {
			// Master/Chief Engineer don't have to state the reason - the
			// originator already did - but they CAN refine what's there
			// (correcting wording before it goes ashore) once one actually
			// exists. Never required, and never overwrites a reason with
			// blank: the textarea for them is only ever shown once
			// $order->reason is already set (see view-order-detail's
			// $canEditReason), so an empty submit here means the field wasn't
			// touched, not that they're trying to erase it.
			$reason = trim((string) $req->reason);

			if (auth()->user()->role->role == 'chief-engineer') {
				if ($order_approval->chief_eng_app != null) {
					$already_approved = true;
				} else {
					$order->status = $this->approved_by_chief_eng;
					if ($reason !== '') {
						$order->reason = $reason;
					}
					$order_approval->chief_eng_app = auth()->user()->id;
					$order->update();
					$order_approval->update();
				}
			} else {
				if ($order_approval->master_app != null) {
					$already_approved = true;
				} else {
					$order->status = $this->approved_by_master;
					if ($reason !== '') {
						$order->reason = $reason;
					}
					$order_approval->master_app = auth()->user()->id;
					$order->update();
					$order_approval->update();
				}
			}

			// Master/Chief Engineer can correct the deck/engine officer's
			// requested quantities before forwarding ashore - the same
			// pattern Deliver Qty and Rcv Qty already use elsewhere in this
			// same approve click.
			foreach ((array) $req->req_qty as $orderItemId => $qty) {
				if ($qty === '' || $qty === null) {
					continue;
				}
				OrderItem::where('id', $orderItemId)->where('order_id', $order->id)->update([
					'item_qty' => $qty,
				]);
			}
		} elseif (auth()->user()->role->role == 'am-srd') {
			$order->status = $this->approved_by_srd_ast_m;
			$order->update();
			$order_approval->ast_m_app = auth()->user()->id;
			$order_approval->update();
		} elseif (auth()->user()->role->role == 'agm-srd') {
			$order->status = $this->approved_by_srd_ag_m;
			$order->update();
			$order_approval->agm_app = auth()->user()->id;
			$order_approval->update();
		} elseif (auth()->user()->role->role == 'dgm-srd') {
			$order->status = $this->approved_by_srd_dgm;
			$order->update();
			$order_approval->dgm_srd_app = auth()->user()->id;
			$order_approval->update();
		} elseif (auth()->user()->role->role == 'superintendent-srd') {
			$order->status = $this->approved_by_srd_superintendent;
			$order->update();
			$order_approval->superintendent_srd_app = auth()->user()->id;
			$order_approval->update();
		} elseif (auth()->user()->role->role == 'gm-srd') {
			$order->status = $this->approved_by_srd_g_m;
			$order->update();
			$order_approval->gm_app = auth()->user()->id;
			$order_approval->update();
		}
		// DGM (SSM) has no plain "approve" - their action is assigning the
		// requisition to a named SSM officer (see assignToSsm). Approving
		// here would set dgm_app_ssm with no assignee, leaving the order
		// visible to nobody.
		// SSM final action is a 3-way race (AGM/AM/Superintendent SSM) -
		// whichever of the three acts first sets the same 'delivered' status;
		// the pending queries above already hide it from the other two the
		// moment any one of them acts. Whoever it is can also adjust each
		// line's Deliver Qty (defaults to Req Qty client-side) right here.
		elseif (in_array(auth()->user()->role->role, ['agm-ssm', 'am-ssm', 'superintendent-ssm'], true)) {
			$column = [
				'agm-ssm' => 'agm_app_ssm',
				'am-ssm' => 'am_app_ssm',
				'superintendent-ssm' => 'superintendent_ssm_app',
			][auth()->user()->role->role];

			$order->status = $this->approved_by_ssm_a_m;
			$order->deliver_date = Carbon::now();
			// This IS the Delivery stage of the procurement workflow - taking
			// it hands the requisition to the Master for Receipt &
			// Verification. Guarded on actually being at that stage so a
			// legacy order mid-flight (no procurement_stage) still behaves
			// exactly as it did before.
			if ($order->procurement_stage === ProcurementStage::DELIVERY) {
				$this->recordProcurementStep($order, ProcurementStage::DELIVERY);
			}
			$order->update();
			$order_approval->{$column} = auth()->user()->id;
			$order_approval->update();

			foreach ((array) $req->deliver_qty as $orderItemId => $qty) {
				if ($qty === '' || $qty === null) {
					continue;
				}
				OrderItem::where('id', $orderItemId)->where('order_id', $order->id)->update([
					'del_item_qty' => $qty,
				]);
			}
		}
		// Technical/Marine Superintendent: normally a purely informational
		// sign-off that never touches order->status and can never block or
		// gate the chain. The one exception: if GM (SRD) has delegated the
		// requisition to one of DGM/AGM/AM/Superintendent (SRD) and that
		// delegate hasn't reviewed it yet, a Superintendent can stand in and
		// perform the delegate's own review action for them - same effect
		// as the delegate acting themselves, sending it back up to GM (SRD).
		// This never lets them skip GM's own approval, and only ever fills
		// in for whichever single delegate GM actually chose.
		elseif (auth()->user()->role->role == 'technical-superintendent' || auth()->user()->role->role == 'marine-superintendent') {
			$isTechnical = auth()->user()->role->role == 'technical-superintendent';
			$ackColumn = $isTechnical ? 'tech_superintendent_app' : 'marine_superintendent_app';
			$roleLabel = $isTechnical ? 'Technical Superintendent' : 'Marine Superintendent';

			$srdDelegateColumns = [
				'forwarded_to_dgm_srd' => 'dgm_srd_app',
				'forwarded_to_agm_by_gm_srd' => 'agm_app',
				'forwarded_to_am_by_agm_srd' => 'ast_m_app',
				'forwarded_to_superintendent_srd' => 'superintendent_srd_app',
			];

			$stoodIn = false;
			if ($order_approval->gm_app === null) {
				foreach ($srdDelegateColumns as $forwardedColumn => $reviewedColumn) {
					if ($order_approval->{$forwardedColumn} !== null && $order_approval->{$reviewedColumn} === null) {
						$order_approval->{$reviewedColumn} = auth()->user()->id;
						$stoodIn = true;
						break;
					}
				}
			}

			$order_approval->{$ackColumn} = auth()->user()->id;
			$order_approval->update();

			$data = $stoodIn
				? 'Reviewed on behalf of the assigned SRD reviewer by '.$roleLabel.' - sent back to GM (SRD).'
				: 'Acknowledged by '.$roleLabel.'.';

			return array($data);
		}

		// GM (SRD) and its four delegates (DGM/AGM/AM/Superintendent SRD) can
		// also correct the requested quantity while it's on their desk, same
		// as Master/Chief Engineer above - the input is only ever rendered
		// for them at their own review turn (see view-order-detail's req_qty
		// column), so this only ever touches a line they were actually shown.
		if (in_array(auth()->user()->role->role, ['gm-srd', 'dgm-srd', 'agm-srd', 'am-srd', 'superintendent-srd'], true)) {
			foreach ((array) $req->req_qty as $orderItemId => $qty) {
				if ($qty === '' || $qty === null) {
					continue;
				}
				OrderItem::where('id', $orderItemId)->where('order_id', $order->id)->update([
					'item_qty' => $qty,
				]);
			}
		}

		$data = $already_approved
			? "Requested Requisition already approved!"
			: "Requested Requisition has been approved successfully!";

		// Everyone who takes a personal approve/review action here lands on
		// their own approval history next, not the generic Approved
		// Requisition page - the order they just acted on may not even be
		// there yet (e.g. it's still with GM, not SSM). DGM (SSM) is the one
		// exception in this chain: they never approve here at all (see
		// assignToSsm() instead), so they're deliberately left out below. A
		// plain array() JSON-encodes as a list and response[0] in the JS
		// still works once 'redirect' is added, since PHP encodes a
		// mixed-key array as an object rather than dropping the string key.
		$landsOnMyApprovals = [
			'master', 'chief-engineer',
			'gm-srd', 'dgm-srd', 'agm-srd', 'am-srd', 'superintendent-srd',
			'agm-ssm', 'am-ssm', 'superintendent-ssm',
		];

		if (in_array(auth()->user()->role->role, $landsOnMyApprovals, true)) {
			return [$data, 'redirect' => route('my.approvals')];
		}

		return array($data);
	}

	/**
	 * Reject a requisition, with a reason, and stop it there.
	 *
	 * Terminal by design: the requisition never returns to anyone's queue and
	 * the originator raises a fresh one instead. Order::isRejected() is what
	 * enforces that everywhere else - hasPendingActionFor() returns false for
	 * a rejected requisition, which removes every action button at once, and
	 * the queue queries below exclude it from the live lists.
	 *
	 * Who may reject is exactly who may approve: whoever currently HOLDS it.
	 * Reusing hasPendingActionFor() rather than a second, parallel rule means
	 * the two can never disagree about whose turn it is - including the roles
	 * whose "turn" isn't an approval at all (DGM (SSM) assigns, GM (SRD) may
	 * be delegating), who can still reject while it sits with them.
	 */
	public function rejectRequisition(Request $req)
	{
		$reason = trim((string) $req->reason);

		if ($reason === '') {
			return response()->json([
				'message' => 'Please give a reason for rejecting this requisition.',
			], 422);
		}

		$order = Order::findOrFail($req->id);
		$role = auth()->user()->role->role ?? null;

		// A draft has not been submitted yet - it belongs to the officer who
		// is still writing it, and they delete rather than reject it.
		if (! $order->ord_status) {
			return response()->json([
				'message' => 'This requisition has not been submitted yet.',
			], 422);
		}

		if ($order->isRejected()) {
			return response()->json([
				'message' => 'This requisition has already been rejected.',
			], 422);
		}

		// From Invoice Verification onwards the goods are already on board -
		// there's nothing left to turn away, only money left to account for.
		// The button is already hidden at this point (see view-order-detail's
		// $showRejectButton), but this endpoint has never checked the stage
		// itself, so a stale tab or a hand-rolled POST could still reject a
		// requisition whose items the ship has already received.
		if (ProcurementStage::isPostReceipt($order->procurement_stage)) {
			return response()->json([
				'message' => 'This requisition has already been received and can no longer be rejected.',
			], 422);
		}

		if (! $order->hasPendingActionFor($role, auth()->user()->id)) {
			return response()->json([
				'message' => 'This requisition is not with you right now, so you cannot reject it.',
			], 403);
		}

		// Captured BEFORE the status changes: once status is 'rejected',
		// currentStageLabel() reports exactly that and can no longer say
		// where in the chain it actually died.
		$stageAtRejection = $order->currentStageLabel();

		$order->status = 'rejected';
		$order->rejected_by = auth()->user()->id;
		$order->rejected_by_role = $role;
		$order->rejected_at_stage = $stageAtRejection;
		$order->rejected_at = Carbon::now();
		$order->rejection_reason = $reason;
		$order->save();

		$data = 'Requisition '.($order->req_no ?: '').' has been rejected.';

		// Back to the pending queue, where it is now conspicuously gone.
		return [$data, 'redirect' => url('/pending/requisition')];
	}

	/**
	 * Records a procurement step for the two stages that are taken through the
	 * approve flow rather than ProcurementController - Delivery and Receipt &
	 * Verification - and advances the stage pointer.
	 *
	 * Sets procurement_stage on the passed model without saving: both callers
	 * are mid-update and save it themselves, and saving twice here would just
	 * burn an extra write.
	 */
	private function recordProcurementStep(Order $order, string $stage, ?string $remarks = null, array $attachmentIds = []): void
	{
		if ($order->hasCompletedStage($stage)) {
			return;
		}

		$step = ProcurementStep::create([
			'order_id' => $order->id,
			'step' => $stage,
			'outcome' => ProcurementStep::OUTCOME_DONE,
			'completed_by' => auth()->user()->id,
			'completed_at' => Carbon::now(),
			'remarks' => $remarks !== null && trim($remarks) !== '' ? trim($remarks) : null,
		]);

		// The ship's acknowledgement receipt - uploaded to the Master's own
		// library while confirming receipt, linked here once the step exists.
		$step->syncOwnedAttachments($attachmentIds, auth()->user()->id);

		$order->procurement_stage = ProcurementStage::next($stage);
	}

	public function forwardToAgm(Request $req)
	{
		// Delegation is GM (SRD)'s alone: they pick any ONE of DGM/AGM/AM/
		// Superintendent (SRD), and that delegate reviews and returns it to
		// GM. Delegates never hand it sideways to another delegate - AGM used
		// to be able to forward on to AM here, a leftover from the old fixed
		// GM -> AGM -> AM chain, which the target workflow doesn't have.
		if (auth()->user()->role->role !== 'gm-srd') {
			return response()->json(['message' => 'Only GM (SRD) can delegate a requisition for review.'], 422);
		}

		$order = Order::findOrFail($req->id);

		if ($order->isRejected()) {
			return response()->json([
				'message' => 'This requisition was rejected and can no longer be acted on.',
			], 422);
		}
		$order_approval = OrderApproval::where('order_id', $order->id)->firstOrFail();

		// Each of DGM/AGM/AM/Superintendent (SRD) can be more than one real
		// person (confirmed: 2 AGM-SRD, 4 AM-SRD in this fleet), so GM picks a
		// named person, not just a role - same pattern as assignToSsm() below.
		// The chosen person's own role is what decides which forwarded_to_*
		// column gets set, so there's no separate role field to keep in sync.
		$assignee = User::find($req->assigned_to);
		$srdColumns = [
			'dgm-srd' => 'forwarded_to_dgm_srd',
			'agm-srd' => 'forwarded_to_agm_by_gm_srd',
			'am-srd' => 'forwarded_to_am_by_agm_srd',
			'superintendent-srd' => 'forwarded_to_superintendent_srd',
		];
		$assigneeRole = $assignee->role->role ?? null;

		if (! $assignee || ! array_key_exists($assigneeRole, $srdColumns)) {
			return response()->json([
				'message' => 'Please choose a reviewer to delegate this requisition to.',
			], 422);
		}

		// Only one delegate can hold this at a time. Without clearing the
		// other three targets' forwarded_to_* columns first, re-delegating
		// (e.g. GM picks AGM, then changes their mind and picks AM instead)
		// leaves the old forwarded_to_agm_by_gm_srd sitting there alongside
		// the new forwarded_to_am_by_agm_srd - both columns end up "set", so
		// the AGM-SRD delegate keeps seeing it as pending action (and the
		// stage label picks whichever column happens to be checked first)
		// even though assigned_to_srd has already moved on to the AM.
		foreach ($srdColumns as $column) {
			$order_approval->{$column} = null;
		}
		$order_approval->agm_app = null;
		$order_approval->ast_m_app = null;
		$order_approval->dgm_srd_app = null;
		$order_approval->superintendent_srd_app = null;

		$order_approval->assigned_to_srd = $assignee->id;
		$order_approval->{$srdColumns[$assigneeRole]} = auth()->user()->id;
		$order_approval->update();

		$order->status = $this->forwarded_by_srd_gm;
		$order->update();

		$data = 'Requisition delegated to '.$assignee->name.' successfully!';

		return [$data, 'redirect' => route('my.approvals')];
	}

	/**
	 * DGM (SSM) assigns the requisition to one named SSM officer (AGM / AM /
	 * Superintendent SSM). Only that person then sees it in their panel and
	 * takes the final action - it isn't opened up to all three at once.
	 */
	public function assignToSsm(Request $req)
	{
		if (auth()->user()->role->role !== 'dgm-ssm') {
			return response()->json(['message' => 'Only DGM (SSM) can assign a requisition.'], 422);
		}

		$order = Order::findOrFail($req->id);

		if ($order->isRejected()) {
			return response()->json([
				'message' => 'This requisition was rejected and can no longer be acted on.',
			], 422);
		}
		$order_approval = OrderApproval::where('order_id', $order->id)->firstOrFail();

		$assignee = User::find($req->assigned_to);
		$ssmRoles = ['agm-ssm', 'am-ssm', 'superintendent-ssm'];

		if (! $assignee || ! in_array($assignee->role->role ?? '', $ssmRoles, true)) {
			return response()->json([
				'message' => 'Please choose an SSM officer to assign this requisition to.',
			], 422);
		}

		$order_approval->assigned_to_ssm = $assignee->id;
		$order_approval->dgm_app_ssm = auth()->user()->id;
		$order_approval->update();

		$roleLabel = [
			'agm-ssm' => 'AGM (SSM)',
			'am-ssm' => 'AM (SSM)',
			'superintendent-ssm' => 'Superintendent (SSM)',
		][$assignee->role->role];

		$order->status = 'assigned to '.$roleLabel.' by DGM (ssm)';
		// Assignment is what starts the procurement workflow - from here the
		// stage, not the status, decides whose queue it sits in.
		$order->procurement_stage = ProcurementStage::first();
		$order->update();

		$data = 'Requisition assigned to '.$assignee->name.' ('.$roleLabel.') successfully!';

		return [$data, 'redirect' => route('my.approvals')];
	}
}
