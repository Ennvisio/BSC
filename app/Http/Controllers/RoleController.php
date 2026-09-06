<?php

namespace App\Http\Controllers;

use App\Category;
use App\Item;
use App\Order;
use App\OrderItem;
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
			->orderBy('created_at', 'desc')
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
			->orderBy('created_at', 'desc')
			->get();
	}
	public function pendingRequisition()
	{
		$items = Item::orderBy('created_at', 'desc')->where('status', true)->get();
		$categories = Category::orderBy('created_at', 'desc')->where('status', true)->get();
		$vessels = Vessel::orderBy('created_at', 'desc')->where('status', true)->get();
		$drafts = collect();
		if (auth()->user()->role->user_type == 'ship') {
			// The multi-page requisition wizard (RequisitionController) saves
			// a draft (status='draft', ord_status=false) after step 1, deliberately
			// invisible everywhere else so it doesn't pollute the real approval
			// queues - but the person who started it still needs to find and
			// resume it.
			$drafts = Order::where('status', 'draft')
				->where('created_by', auth()->user()->id)
				->orderBy('created_at', 'desc')
				->get();
			if (auth()->user()->role->role == 'chief-officer' || auth()->user()->role->role == 'second-engineer') {
				$orders = Order::
					// where('status',$this->approved_by_cfiefOfcr)
					// where('master_app',null)
					// ->where('chief_eng_app',null)
					where('ord_status', true)
					->where('created_by_role', auth()->user()->role->role)
					->whereHas('orderApproval', function ($q) {
						$q->where('master_app', null)
							->where('chief_eng_app', null)
							->where('second_eng_app', null)
							->where('cheif_ofcr_app', null);
					})
					// ->where('cheif_ofcr_app','=',null)
					->where('vessel_id', auth()->user()->role->vessel->id)
					->orderBy('created_at', 'desc')
					->get();
				// return $orders;
			}
			if (auth()->user()->role->role == 'master') {
				// Master's pending queue is two different things at once: their
				// own initial-approval step for chief-officer-originated orders,
				// and - per the target workflow - receipt confirmation for
				// EVERY delivered order on this vessel regardless of who
				// originated it (deck or engine-room), since Master always
				// closes the loop, not just second-engineer for their own.
				$orders = Order::where('ord_status', true)
					->where('vessel_id', auth()->user()->role->vessel->id)
					->where(function ($query) {
						$query->where('status', 'delivered')
							->orWhereHas('orderApproval', function ($q) {
								$q->where('cheif_ofcr_app', '!=', null)
									->where('master_app', null);
							});
					})
					->orderBy('created_at', 'desc')
					->get();
			}
			if (auth()->user()->role->role == 'chief-engineer') {
				$orders = Order::where('ord_status', true)
					->whereHas('orderApproval', function ($q) {
						$q->where('second_eng_app', '!=', null)
							->where('chief_eng_app', null);
					})
					->where('vessel_id', auth()->user()->role->vessel->id)
					->orderBy('created_at', 'desc')
					->get();
			}
			// if(auth()->user()->role->role=='second-engineer'){
			// 	$orders=Order::where('status',$this->approved_by_ssm_a_m)
			// 	->where('ord_status',true)
			// 	->whereHas('orderApproval', function($q){
			// 		$q->where(function($query){
			// 			$query->where('master_app','!=',null)
			// 			->orWhere('chief_eng_app','!=',null);
			// 		})
			// 		->where('cheif_ofcr_app','!=',null)
			// 		->where('ord_status',true)
			// 		->where('ast_m_app','!=',null)
			// 		->where('agm_app','!=',null)
			// 		->where('gm_app','!=',null)
			// 		->where('dgm_app_ssm','!=',null)
			// 		->where('agm_app_ssm','!=',null)
			// 		->where('am_app_ssm','!=',null);
			// 	})
			// 	->where('vessel_id', auth()->user()->role->vessel->id)
			// 	->orderBy('created_at','desc')
			// 	->get();
			// }
			return view('layouts.ship-home', compact('orders', 'drafts'));
		} else {
			if (auth()->user()->role->role == 'am-srd') {
				$orders = Order::where('ord_status', true)
					->whereHas('orderApproval', function ($q) {
						$q->where(function ($query) {
							$query->where('master_app', '!=', null)
								->orWhere('chief_eng_app', '!=', null);
						})
							->where('forwarded_to_am_by_agm_srd', '!=', null)
							->where('ast_m_app', '=', null);
					})
					->orderBy('created_at', 'desc')
					->get();
			} elseif (auth()->user()->role->role == 'agm-srd') {
				// Same shape as every other SRD delegate queue: only orders GM
				// actually delegated to AGM, that AGM hasn't reviewed yet, and
				// that GM hasn't already approved past. (This previously
				// carried an orWhere() for the old "AM sends it back up to
				// AGM" hop - AGM no longer forwards to AM at all, and because
				// AND binds tighter than OR in SQL that stray clause collapsed
				// the whole filter down to just "master or chief engineer
				// approved it", so AGM was seeing effectively every order.)
				$orders = Order::where('ord_status', true)
					->whereHas('orderApproval', function ($q) {
						$q->where(function ($query) {
							$query->where('master_app', '!=', null)
								->orWhere('chief_eng_app', '!=', null);
						})
							->where('forwarded_to_agm_by_gm_srd', '!=', null)
							->where('gm_app', '=', null)
							->where('agm_app', '=', null);
					})
					->orderBy('created_at', 'desc')
					->get();
			} elseif (auth()->user()->role->role == 'dgm-srd') {
				$orders = Order::where('ord_status', true)
					->whereHas('orderApproval', function ($q) {
						$q->where(function ($query) {
							$query->where('master_app', '!=', null)
								->orWhere('chief_eng_app', '!=', null);
						})
							->where('forwarded_to_dgm_srd', '!=', null)
							->where('dgm_srd_app', '=', null);
					})
					->orderBy('created_at', 'desc')
					->get();
			} elseif (auth()->user()->role->role == 'superintendent-srd') {
				$orders = Order::where('ord_status', true)
					->whereHas('orderApproval', function ($q) {
						$q->where(function ($query) {
							$query->where('master_app', '!=', null)
								->orWhere('chief_eng_app', '!=', null);
						})
							->where('forwarded_to_superintendent_srd', '!=', null)
							->where('superintendent_srd_app', '=', null);
					})
					->orderBy('created_at', 'desc')
					->get();
			} elseif (auth()->user()->role->role == 'gm-srd') {
				$orders = Order::where('ord_status', true)
					->whereHas('orderApproval', function ($q) {
						$q->where(function ($query) {
							$query->where('master_app', '!=', null)
								->orWhere('chief_eng_app', '!=', null);
						})
							->where('gm_app', '=', null);
					})
					->orderBy('created_at', 'desc')
					->get();
			} elseif (auth()->user()->role->role == 'dgm-ssm') {
				$orders = Order::where('ord_status', true)
					->whereHas('orderApproval', function ($q) {
						$q->where(function ($query) {
							$query->where('master_app', '!=', null)
								->orWhere('chief_eng_app', '!=', null);
						})
							->where('gm_app', '!=', null)
							->where('dgm_app_ssm', '=', null);
					})
					->orderBy('created_at', 'desc')
					->get();
			}
			// SSM final action. DGM (SSM) assigns the requisition to one named
			// officer, so all three roles share the same queue definition:
			// "assigned to me, and nobody has taken the final action yet".
			// assigned_to_ssm being null means it predates named assignment -
			// those fall back to the old behaviour of all three seeing it.
			elseif (in_array(auth()->user()->role->role, ['agm-ssm', 'am-ssm', 'superintendent-ssm'], true)) {
				$userId = auth()->user()->id;
				$orders = Order::where('ord_status', true)
					->whereHas('orderApproval', function ($q) use ($userId) {
						$q->where(function ($query) {
							$query->where('master_app', '!=', null)
								->orWhere('chief_eng_app', '!=', null);
						})
							->where('gm_app', '!=', null)
							->where('dgm_app_ssm', '!=', null)
							->where(function ($query) use ($userId) {
								$query->where('assigned_to_ssm', $userId)
									->orWhereNull('assigned_to_ssm');
							})
							->where('agm_app_ssm', '=', null)
							->where('am_app_ssm', '=', null)
							->where('superintendent_ssm_app', '=', null);
					})
					->orderBy('created_at', 'desc')
					->get();
			}
			// Technical/Marine Superintendent: standing, cross-vessel visibility
			// at any stage - every active requisition not yet fully closed,
			// regardless of which role currently holds it. Purely informational
			// (see approveRequisition()) - never gates the normal chain.
			elseif (auth()->user()->role->role == 'technical-superintendent' || auth()->user()->role->role == 'marine-superintendent') {
				$orders = Order::where('ord_status', true)
					->where('status', '!=', 'received')
					->orderBy('created_at', 'desc')
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
		if (auth()->user()->role->user_type == 'ship') {
			$drafts = Order::where('status', 'draft')
				->where('created_by', auth()->user()->id)
				->orderBy('created_at', 'desc')
				->get();
		}

		if (auth()->user()->role->role == 'second-engineer') {
			$orders = Order::where('ord_status', true)
				->where('created_by_role', auth()->user()->role->role)
				->where('vessel_id', auth()->user()->role->vessel->id)
				->whereHas('orderApproval', function ($q) {
					$q->where('second_eng_app', '!=', null);
				})
				->orderBy('created_at', 'desc')
				->get();
		}
		elseif (auth()->user()->role->role == 'chief-officer') {
			$orders = Order::where('ord_status', true)
				->whereHas('orderApproval', function ($q) {
					$q->where('cheif_ofcr_app', '!=', null);
				})
				->where('created_by_role', auth()->user()->role->role)
				->where('vessel_id', auth()->user()->role->vessel->id)
				->orderBy('created_at', 'desc')
				->get();
		}
		elseif (auth()->user()->role->role == 'chief-engineer') {
			$orders = Order::where('ord_status', true)
				->whereHas('orderApproval', function ($q) {
					$q->where(function ($query) {
						$query->where('chief_eng_app', auth()->user()->id);
					})
						->where('second_eng_app', '!=', null);
				})
				->where('vessel_id', auth()->user()->role->vessel->id)
				->where('ord_status', true)
				->orderBy('created_at', 'desc')
				->get();
		}
		elseif (auth()->user()->role->role == 'master') {
			$orders = Order::where('ord_status', true)
				->where('vessel_id', auth()->user()->role->vessel->id)
				->where(function ($query) {
					$query->where('status', 'received')
						->orWhereHas('orderApproval', function ($q) {
							$q->where('master_app', auth()->user()->id)
								->where('cheif_ofcr_app', '!=', null);
						});
				})
				->orderBy('created_at', 'desc')
				->get();
		}
		elseif (auth()->user()->role->role == 'am-srd') {
			$orders = Order::where('ord_status', true)
				->whereHas('orderApproval', function ($q) {
					$q->where(function ($query) {
						$query->where('master_app', '!=', null)
							->orWhere('chief_eng_app', '!=', null);
					})
						->where('forwarded_to_am_by_agm_srd', '!=', null)
						->where('ast_m_app', '!=', null);
				})
				->orderBy('created_at', 'desc')
				->get();
		}
		elseif (auth()->user()->role->role == 'agm-srd') {
			$orders = Order::where('ord_status', true)
				->whereHas('orderApproval', function ($q) {
					$q->where(function ($query) {
						$query->where('master_app', '!=', null)
							->orWhere('chief_eng_app', '!=', null);
					})
						->where('forwarded_to_agm_by_gm_srd', '!=', null)
						->where('agm_app', '!=', null);
				})
				->orderBy('created_at', 'desc')
				->get();
		}
		elseif (auth()->user()->role->role == 'dgm-srd') {
			$orders = Order::where('ord_status', true)
				->whereHas('orderApproval', function ($q) {
					$q->where(function ($query) {
						$query->where('master_app', '!=', null)
							->orWhere('chief_eng_app', '!=', null);
					})
						->where('forwarded_to_dgm_srd', '!=', null)
						->where('dgm_srd_app', '!=', null);
				})
				->orderBy('created_at', 'desc')
				->get();
		}
		elseif (auth()->user()->role->role == 'superintendent-srd') {
			$orders = Order::where('ord_status', true)
				->whereHas('orderApproval', function ($q) {
					$q->where(function ($query) {
						$query->where('master_app', '!=', null)
							->orWhere('chief_eng_app', '!=', null);
					})
						->where('forwarded_to_superintendent_srd', '!=', null)
						->where('superintendent_srd_app', '!=', null);
				})
				->orderBy('created_at', 'desc')
				->get();
		}
		elseif (auth()->user()->role->role == 'gm-srd') {
			$orders = Order::where('ord_status', true)
				->whereHas('orderApproval', function ($q) {
					$q->where(function ($query) {
						$query->where('master_app', '!=', null)
							->orWhere('chief_eng_app', '!=', null);
					})
						->where('gm_app', '!=', null);
				})
				->orderBy('created_at', 'desc')
				->get();
		}
		elseif (auth()->user()->role->role == 'dgm-ssm') {
			$orders = Order::where('ord_status', true)
				->whereHas('orderApproval', function ($q) {
					$q->where(function ($query) {
						$query->where('master_app', '!=', null)
							->orWhere('chief_eng_app', '!=', null);
					})
						->where('gm_app', '!=', null)
						->where('dgm_app_ssm', '!=', null);
				})
				->orderBy('created_at', 'desc')
				->get();
		}
		elseif (auth()->user()->role->role == 'agm-ssm') {
			$orders = Order::where('ord_status', true)
				->whereHas('orderApproval', function ($q) {
					$q->where(function ($query) {
						$query->where('master_app', '!=', null)
							->orWhere('chief_eng_app', '!=', null);
					})
						->where('gm_app', '!=', null)
						->where('dgm_app_ssm', '!=', null)
						->where('agm_app_ssm', '!=', null);
				})
				->orderBy('created_at', 'desc')
				->get();
		}
		elseif (auth()->user()->role->role == 'am-ssm') {
			$orders = Order::where('ord_status', true)
				->whereHas('orderApproval', function ($q) {
					$q->where(function ($query) {
						$query->where('master_app', '!=', null)
							->orWhere('chief_eng_app', '!=', null);
					})
						->where('gm_app', '!=', null)
						->where('dgm_app_ssm', '!=', null)
						->where('am_app_ssm', '!=', null);
				})
				->orderBy('created_at', 'desc')
				->get();
		}
		elseif (auth()->user()->role->role == 'superintendent-ssm') {
			$orders = Order::where('ord_status', true)
				->whereHas('orderApproval', function ($q) {
					$q->where(function ($query) {
						$query->where('master_app', '!=', null)
							->orWhere('chief_eng_app', '!=', null);
					})
						->where('gm_app', '!=', null)
						->where('dgm_app_ssm', '!=', null)
						->where('superintendent_ssm_app', '!=', null);
				})
				->orderBy('created_at', 'desc')
				->get();
		}
		elseif (auth()->user()->role->role == 'technical-superintendent') {
			$orders = Order::where('ord_status', true)
				->whereHas('orderApproval', function ($q) {
					$q->where('tech_superintendent_app', '!=', null);
				})
				->orderBy('created_at', 'desc')
				->get();
		}
		elseif (auth()->user()->role->role == 'marine-superintendent') {
			$orders = Order::where('ord_status', true)
				->whereHas('orderApproval', function ($q) {
					$q->where('marine_superintendent_app', '!=', null);
				})
				->orderBy('created_at', 'desc')
				->get();
		}
		if (auth()->user()->role->user_type != 'ship') {
			return view('layouts.order', compact('orders', 'items', 'categories', 'vessels'));
		} else {
			return view('layouts.ship-home', compact('orders', 'drafts'));
		}
	}
	public function approveRequisition(Request $req)
	{
		$order = Order::findOrFail($req->id);
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
			if (auth()->user()->role->role == 'second-engineer') {
				if ($order_approval->second_eng_app != null) {
					$already_approved = true;
				} else {
					$order->status = $this->approved_by_second_eng;
					$order->update();
					$order_approval->second_eng_app = auth()->user()->id;
					$order_approval->update();
				}
			} else {
				if ($order_approval->cheif_ofcr_app != null) {
					$already_approved = true;
				} else {
					$order->status = $this->approved_by_cfiefOfcr;
					$order->update();
					$order_approval->cheif_ofcr_app = auth()->user()->id;
					$order_approval->update();
				}
			}
		} elseif (auth()->user()->role->role == 'chief-engineer' || auth()->user()->role->role == 'master') {
			// Every revised requisition form has a "Reason of Requisition
			// (filled by Master/Chief Engineer)" field - required before they
			// can forward it ashore, matching the paper process.
			$reason = trim((string) $req->reason);
			if ($reason === '') {
				// A plain array() response here would come back as a 200 OK,
				// which the JS treats as success and redirects away without
				// ever actually saving an approval - a real error status is
				// what lets the client tell the difference and show it as
				// an actual error instead of a fake "Congratulation!".
				return response()->json([
					'message' => 'Please fill in the Reason of Requisition before forwarding.',
				], 422);
			}

			if (auth()->user()->role->role == 'chief-engineer') {
				if ($order_approval->chief_eng_app != null) {
					$already_approved = true;
				} else {
					$order->status = $this->approved_by_chief_eng;
					$order->reason = $reason;
					$order_approval->chief_eng_app = auth()->user()->id;
					$order->update();
					$order_approval->update();
				}
			} else {
				if ($order_approval->master_app != null) {
					$already_approved = true;
				} else {
					$order->status = $this->approved_by_master;
					$order->reason = $reason;
					$order_approval->master_app = auth()->user()->id;
					$order->update();
					$order_approval->update();
				}
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
		if ($already_approved == true) {
			$data = "Requested Requisition already approved!";
			return array($data);
		} else {
			$data = "Requested Requisition has been approved successfully!";
			return array($data);
		}
	}
	public function forwardToAgm(Request $req)
	{
		$order=Order::findOrFail($req->id);
		$order_approval=OrderApproval::where('order_id', $order->id)->firstOrFail();

		// Delegation is GM (SRD)'s alone: they pick any ONE of DGM/AGM/AM/
		// Superintendent (SRD), and that delegate reviews and returns it to
		// GM. Delegates never hand it sideways to another delegate - AGM used
		// to be able to forward on to AM here, a leftover from the old fixed
		// GM -> AGM -> AM chain, which the target workflow doesn't have.
		if (auth()->user()->role->role == 'gm-srd') {
			$column = match ($req->target_role) {
				'dgm-srd' => 'forwarded_to_dgm_srd',
				'am-srd' => 'forwarded_to_am_by_agm_srd',
				'superintendent-srd' => 'forwarded_to_superintendent_srd',
				default => 'forwarded_to_agm_by_gm_srd',
			};
			$order_approval->{$column} = auth()->user()->id;
			$order_approval->update();
			$order->status = $this->forwarded_by_srd_gm;
			$order->update();
			$data = "Requested Requisition has been forwarded successfully!";
			return array($data);
		}

		$data = "Only GM (SRD) can delegate a requisition for review.";
		return array($data);
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
		$order->update();

		$data = 'Requisition assigned to '.$assignee->name.' ('.$roleLabel.') successfully!';

		return array($data);
	}
}
