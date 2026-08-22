<?php

namespace App\Http\Controllers;

use App\Category;
use App\Item;
use App\Order;
use App\Vessel;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\OrderApproval;

class RoleController extends Controller
{
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
		if (auth()->user()->role->user_type == 'ship') {
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
				$orders = Order::where('ord_status', true)
					->whereHas('orderApproval', function ($q) {
						$q->where('cheif_ofcr_app', '!=', null)
							->where('master_app', null);
					})
					->where('vessel_id', auth()->user()->role->vessel->id)
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
			return view('layouts.ship-home', compact('orders'));
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
				$orders = Order::where('ord_status', true)
					->whereHas('orderApproval', function ($q) {
						$q->where(function ($query) {
							$query->where('master_app', '!=', null)
								->orWhere('chief_eng_app', '!=', null);
						})
						->orWhere(function($q1){
							$q1->where('forwarded_to_am_by_agm_srd', '!=', null)
							->where('ast_m_app', '!=', null);
						})
							
							->where('forwarded_to_agm_by_gm_srd', '!=', null)
							->where('gm_app', '=', null)
							->where('agm_app', '=', null);
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
			//  elseif (auth()->user()->role->role == 'agm-ssm') {
			// 	$orders = Order::where('ord_status', true)
			// 		->whereHas('orderApproval', function ($q) {
			// 			$q->where(function ($query) {
			// 				$query->where('master_app', '!=', null)
			// 					->orWhere('chief_eng_app', '!=', null);
			// 			})
			// 				->where('cheif_ofcr_app', '!=', null)
			// 				->where('ast_m_app', '!=', null)
			// 				->where('agm_app', '!=', null)
			// 				->where('gm_app', '!=', null)
			// 				->where('dgm_app_ssm', '!=', null)
			// 				->where('agm_app_ssm', '=', null);
			// 		})
			// 		->orderBy('created_at', 'desc')
			// 		->get();
			// } 
			elseif (auth()->user()->role->role == 'am-ssm') {
				$orders = Order::where('ord_status', true)
					->whereHas('orderApproval', function ($q) {
						$q->where(function ($query) {
							$query->where('master_app', '!=', null)
								->orWhere('chief_eng_app', '!=', null);
						})
							->where('gm_app', '!=', null)
							->where('dgm_app_ssm', '!=', null)
							->where('am_app_ssm', '=', null);
					})
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
				->whereHas('orderApproval', function ($q) {
					$q->where(function ($query) {
						$query->where('master_app', auth()->user()->id);
					})
						->where('cheif_ofcr_app', '!=', null);
				})
				->where('vessel_id', auth()->user()->role->vessel->id)
				->where('ord_status', true)
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
		// if (auth()->user()->role->role == 'agm-ssm') {
		// 	$orders = Order::where('ord_status', true)
		// 		->whereHas('orderApproval', function ($q) {
		// 			$q->where(function ($query) {
		// 				$query->where('master_app', '!=', null)
		// 					->orWhere('chief_eng_app', '!=', null);
		// 			})
		// 				->where('gm_app', '!=', null)
		// 				->where('dgm_app_ssm', '!=', null)
		// 				->where('agm_app_ssm', '!=', null);
		// 		})
		// 		->orderBy('created_at', 'desc')
		// 		->get();
		// }
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
		// if(auth()->user()->role->role=='second-engineer'){
		// 	$orders=$this->secondEngineerOrOfficerApproved();
		// }
		if (auth()->user()->role->user_type != 'ship') {
			return view('layouts.order', compact('orders', 'items', 'categories', 'vessels'));
		} else {
			return view('layouts.ship-home', compact('orders'));
		}
	}
	public function approveRequisition(Request $req)
	{
		$order = Order::findOrFail($req->id);
		$order_approval = OrderApproval::findOrFail($order->id);
		$already_approved = false;
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
			if (auth()->user()->role->role == 'chief-engineer') {
				if ($order_approval->chief_eng_app != null) {
					$already_approved = true;
				} else {
					$order->status = $this->approved_by_chief_eng;
					$order_approval->chief_eng_app = auth()->user()->id;
					$order->update();
					$order_approval->update();
				}
			} else {
				if ($order_approval->master_app != null) {
					$already_approved = true;
				} else {
					$order->status = $this->approved_by_master;
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
		} elseif (auth()->user()->role->role == 'gm-srd') {
			$order->status = $this->approved_by_srd_g_m;
			$order->update();
			$order_approval->gm_app = auth()->user()->id;
			$order_approval->update();
		} elseif (auth()->user()->role->role == 'dgm-ssm') {
			$order->status = $this->approved_by_ssm_dg_m;
			$order->update();
			$order_approval->dgm_app_ssm = auth()->user()->id;
			$order_approval->update();
		} 
		// elseif (auth()->user()->role->role == 'agm-ssm') {
		// 	$order->status = $this->approved_by_ssm_ag_m;
		// 	$order->update();
		// 	$order_approval->agm_app_ssm = auth()->user()->id;
		// 	$order_approval->update();
		// } 
		elseif (auth()->user()->role->role == 'am-ssm') {
			$order->status = $this->approved_by_ssm_a_m;
			$order->deliver_date = Carbon::now();
			$order->update();
			$order_approval->am_app_ssm = auth()->user()->id;
			$order_approval->update();
		} elseif (auth()->user()->role->role == 'second-engineer' && $order->status == 'delivered') {
			$order->status = 'received';
			$order->rcv_date = Carbon::now();
			$order->update();
			$data = "Requested Requisition has been received successfully!";
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
		$order_approval=OrderApproval::findOrFail($order->id);
		if (auth()->user()->role->role == 'gm-srd') {
			$order->status = $this->forwarded_to_agm_by_srd_gm;
			$order->update();
			$order_approval->forwarded_to_agm_by_gm_srd	 = auth()->user()->id;
			$order_approval->update();
			$data = "Requested Requisition has been forwarded successfully!";
			return array($data);
		}
		elseif (auth()->user()->role->role == 'agm-srd') {
			$order->status = $this->forwarded_to_ast_m_by_srd_agm;
			$order->update();
			$order_approval->forwarded_to_am_by_agm_srd	 = auth()->user()->id;
			$order_approval->update();
			$data = "Requested Requisition has been forwarded successfully!";
			return array($data);
		}
	}
}
