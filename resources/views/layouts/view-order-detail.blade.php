@extends('layouts.admin-master')
@section('main-content')
<style>
.written-sign img{
	max-height:80px;
	max-width:300px;
}

/* The action row in this card header used class "right-button" (singular),
   which nothing in style.css ever matched - only ".right-buttons" (plural)
   is defined there - so these controls were laid out as bare inline
   elements with no alignment. Lay them out properly here instead. */
.order-section .card-header.first{
	flex-wrap: wrap;
	gap: 10px;
	padding-top: .6rem;
	padding-bottom: .6rem;
}
.order-section .card-header.first .right-button,
.order-section .card-header.first .center-button{
	display: flex;
	align-items: center;
	flex-wrap: wrap;
	gap: 8px;
	margin-left: auto;
}
.order-section .card-header.first .right-button > *,
.order-section .card-header.first .center-button > *{
	margin: 0;
}
.order-section .card-header.first .btn{
	display: inline-flex;
	align-items: center;
	gap: 6px;
	white-space: nowrap;
	height: calc(1.5em + .75rem + 2px);
}
.order-section .card-header.first .form-control{
	height: calc(1.5em + .75rem + 2px);
	width: 235px;
}
</style>
<div class="order-section container">
	<div class="row">
		<div class="col-xl-12">
			<div class="card order-card">
				<div class="card-header first">
					<strong class="pptitle">Requested Order Details &nbsp; 
						<span style="color:red;">{{!empty($order->vessel->name)?$order->vessel->name:''}}</span>
					</strong>
					@if(auth()->user()->role->role =='am-ssm' && $order->status!='Supplied to Ship')
					<div class="center-button">
						<select class="form-control order_status" id="order_status" name="order_status">
							@if($order->status!='')
							<option selected value="{{$order->status}}">{{$order->status}}</option>
							@else
							<option selected value="">-- Choose Order Status --</option>
							@endif
							<option value="Requisition for Approval" >Requisition for Approval</option>
							<option value="Call for Tender" >Call for Tender</option>
							<option value="Placed Work Order" >Placed Work Order</option>
							<option value="Supplied to Ship" >Supplied to Ship</option>
						</select>
						<button type="button" class="btn btn-primary" id="update_order_status" data-id="{{$order->id}}">
							Update Status
						</button>	
					</div>
					@elseif(auth()->user()->role->role=='am-ssm' && $order->status=='Supplied to Ship')
					<button type="button" class="btn btn-primary" id="approve_order" data-id="{{$order->id}}">
						Deliver Confirmation 
					</button>
					@endif
					@php
						$currentRole = auth()->user()->role->role ?? null;
						// Only offer an action to someone who actually still has
						// one here - previously every role saw Approve on every
						// order forever, including ones they'd already signed off.
						$canAct = $order->hasPendingActionFor($currentRole, auth()->id());
					@endphp
					<div class="right-button">
						@if($canAct && $currentRole != 'operator' && $currentRole != 'am-ssm' && $currentRole != 'dgm-ssm')
						<button type="button" class="btn btn-primary" id="approve_order" data-id="{{$order->id}}">
							<i class="fas fa-check-circle"></i>
							{{ $currentRole == 'master' && $order->status == 'delivered' ? 'Confirm Receipt' : 'Approve' }}
						</button>
						@endif

						{{-- DGM (SSM) doesn't approve - they assign it to one named
						     SSM officer, who then takes the final action. --}}
						@if($canAct && $currentRole == 'dgm-ssm')
						<select class="form-control" id="ssm_assignee">
							<option value="">Assign to…</option>
							@foreach($ssmOfficers ?? [] as $roleName => $officers)
							<optgroup label="{{ strtoupper(str_replace('-', ' ', $roleName)) }}">
								@foreach($officers as $officer)
								<option value="{{ $officer->user->id }}">{{ $officer->user->name }}</option>
								@endforeach
							</optgroup>
							@endforeach
						</select>
						<button type="button" class="btn btn-primary" id="assign_ssm" data-id="{{$order->id}}"><i class="fas fa-user-plus"></i> Assign</button>
						@endif

						@if($canAct && $currentRole == 'gm-srd')
						<select class="form-control" id="srd_delegate_target">
							<option value="agm-srd">Delegate to AGM (SRD)</option>
							<option value="am-srd">Delegate to AM (SRD)</option>
							<option value="dgm-srd">Delegate to DGM (SRD)</option>
							<option value="superintendent-srd">Delegate to Superintendent (SRD)</option>
						</select>
						<button type="button" class="btn btn-info" id="forward_toagm" data-id="{{$order->id}}"><i class="fas fa-angle-double-right"></i> Forward</button>
						@endif
						<button type="button" class="btn btn-info btn-bvprint print-order-details"><i class="fa fa-print"></i> Print</button>
					</div>
				</div>
				<div class="card-body">
					@if((auth()->user()->role->role=='am-ssm' && $order->status=='Supplied to Ship') || (auth()->user()->role->role=='operator' && $order->status=='delivered')|| (auth()->user()->role->role=='am-srd' && $order->ast_m_app==null))
					<form class="form mb-3" id="deliveredQtyForm">
						@csrf
						@endif
						<table id="example" class="table table-striped table-bordered orderedItemTable OrderDetailsTable" style="width:100%">
							<div class="row mb-3 justify-content-between" id="order-print-header2">
								<div class="col">
									<strong>Vessel:</strong> {{$order->vessel->name}}
								</div>
								<div class="col">
									<strong>Req. No:</strong> {{$order->req_no}}
								</div>
								<div class="col">
									<strong>Date:</strong> {{$order->req_date}}
								</div>
								<div class="col">
									<strong>Port:</strong> {{$order->port_name}}
								</div>
								<div class="col">
									<strong>Stage:</strong> <span class="badge badge-info">{{ $order->currentStageLabel() }}</span>
								</div>
							</div>
							@php
								$canEditReason = (auth()->user()->role->role == 'master' && empty($order->orderApproval->master_app))
									|| (auth()->user()->role->role == 'chief-engineer' && empty($order->orderApproval->chief_eng_app));
							@endphp
							@if($canEditReason)
							<div class="row mb-3">
								<div class="col-md-12">
									<label for="requisition_reason"><strong>Reason of Requisition</strong> (filled by Master/Chief Engineer)</label>
									<textarea class="form-control" id="requisition_reason" rows="3" placeholder="Why is this requisition needed?">{{ $order->reason }}</textarea>
								</div>
							</div>
							@elseif(!empty($order->reason))
							<div class="row mb-3">
								<div class="col-md-12">
									<strong>Reason of Requisition:</strong> {{ $order->reason }}
								</div>
							</div>
							@endif
							<thead>
								<tr>
									<th>Item No.</th>
									<th>IMPA Code No</th>
									<th>Item Name
										<!-- <span class="item-name">Item Name</span> -->
										<!-- <span class="item-name-print">Description <br> As per IMPA Code 6Th Edn</span> -->
									</th>
									<th>Unit</th>
									<th>Opening Stock</th>
									<th>Last <br>Supply </th>
									<th>In Stock</th>
									<th>Total Supply</th>
									<th>Req Qty</th>
									<th>Deliverd <br> Qty </th>
									<th>Rcv Qty</th>
									<!-- <th class="item-cat">Category</th> -->
									@if((auth()->user()->role->role=='am-ssm' && $order->status=='Supplied to Ship') || (auth()->user()->role->role=='operator' && $order->status=='delivered')|| (auth()->user()->role->role=='am-srd' && $order->ast_m_app==null)) 
									<th class="">Action</th>
									@endif
								</tr>
							</thead>
							<tbody>
								@if(!empty($order->orderItems))
								@foreach($order->orderItems as $orderItem)
								<tr>
									<td><b class="serial">{{$loop->iteration}}</b></td>
									<td>{{$orderItem->item->impa_code}}</td>
									<td class="item-name-td">{{$orderItem->item->name}}</td>
									<td class="item-unit">{{$orderItem->item->unit}}</td>
									{{-- Opening Stock and Last Supply are the figures captured when this
										 requisition was raised, so an old form still prints what justified
										 it. In Stock is live - what is on board right now. --}}
									<td>{{ $orderItem->opening_stock !== null ? $orderItem->opening_stock : '-' }}</td>
									<td>
										@if($orderItem->last_supply_qty !== null)
										{{ $orderItem->last_supply_qty }}
										@if($orderItem->last_supply_date)
										<br><span class="text-muted" style="font-size:11px;">{{ \Carbon\Carbon::parse($orderItem->last_supply_date)->format('d M Y') }}</span>
										@endif
										@else
										-
										@endif
									</td>
									<td>{{ $liveStock[$orderItem->item_id]['stock_qty'] ?? 0 }}</td>
									<td>{{ $totalSupplied[$orderItem->item_id] ?? 0 }}</td>
									<td class='req_qty'>
										@if(auth()->user()->role->role=='am-srd' && $order->ast_m_app==null)
										<div class="form-group" style="margin: 0">
											<input type="number" data-id="{{$orderItem->id}}" class="form-control req-qty" name="req_qty[{{$orderItem->id}}]" value="{{$orderItem->item_qty}}">
										</div>
										@else
										{{$orderItem->item_qty}}
										@endif
									</td>
									<td class='deliver_qty'>
										@if($canAct && in_array($currentRole, ['agm-ssm', 'am-ssm', 'superintendent-ssm']))
										<div class="form-group" style="margin: 0">
											<input type="number" data-id="{{$orderItem->id}}" class="form-control deliver-qty" name="deliver_qty[{{$orderItem->id}}]" value="{{ $orderItem->del_item_qty ?? $orderItem->item_qty }}">
										</div>
										@else
										{{!empty($orderItem->del_item_qty)?$orderItem->del_item_qty:''}}
										@endif
									</td>
									<td class='rcv_qty'>
										@if($canAct && $currentRole == 'master' && $order->status == 'delivered')
										<div class="form-group" style="margin: 0">
											<input type="number" data-id="{{$orderItem->id}}" class="form-control rcv-qty" name="rcv_qty[{{$orderItem->id}}]" value="{{ $orderItem->rcv_item_qty ?? $orderItem->del_item_qty }}">
										</div>
										@else
										{{!empty($orderItem->rcv_item_qty)?$orderItem->rcv_item_qty:''}}
										@endif
									</td>

									@if((auth()->user()->role->role=='am-ssm' && $order->status=='Supplied to Ship') || (auth()->user()->role->role=='operator' && $order->status=='delivered')|| (auth()->user()->role->role=='am-srd' && $order->ast_m_app==null))
									<td class="action">
										<button class="btn btn-info" id="indSave" data-role="{{auth()->user()->role->role}}"> Save</button>
									</td>
									@endif
								</tr>
								@endforeach
								@endif
							</tbody>
						</table>
						@if((auth()->user()->role->role=='am-ssm' && $order->status=='Supplied to Ship') || (auth()->user()->role->role=='operator' && $order->status=='delivered')|| (auth()->user()->role->role=='am-srd' && $order->ast_m_app==null))
						<input type="hidden" class="form-control" value="{{$order->id}}" name="orderId">
						<button class="btn btn-info float-right mt-2" type="submit"> Save All </button>
					</form>
					@endif

					<br>
					<hr>	
					<br>
					<div id="order-print-footer1" class="print-header" >
						<div class="signs-master-chief">
							
						    @foreach(\App\Role::orderBy('user_type','asc')->get() as $role)
								@if($role->user->id==$order->orderApproval->master_app
								|| $role->user->id==$order->orderApproval->chief_eng_app
								|| $role->user->id==$order->orderApproval->cheif_ofcr_app
								|| $role->user->id==$order->orderApproval->second_eng_app
								|| $role->user->id==$order->orderApproval->ast_m_app
								|| $role->user->id==$order->orderApproval->agm_app
								|| $role->user->id==$order->orderApproval->gm_app
								|| $role->user->id==$order->orderApproval->dgm_app_ssm
								|| $role->user->id==$order->orderApproval->agm_app_ssm
								|| $role->user->id==$order->orderApproval->am_app_ssm
									)
									<div class="master-chief">
								<span class="written-sign sign">
									<img src="{{url('/'.$role->user->sign)}}" alt="">
								</span>
								
								<span>_____________________</span>
								<span>( Signature)</span>
								<span>{{$role->role}}</span>
								<span class="signer-name">
									{{$role->user->name}}
								</span>
									</div>
									@endif
						
						
							@endforeach



			<!-- 				<div class="chief-officer">
								<span class="written-sign sign">
									@if((!empty($order->cheif_ofcr_app) && $order->cheif_ofcr_app==true))
									<img src="{{asset('/images/chief-sign.jpg')}}" alt="">
									@endif
								</span>
								<span>_____________________</span>
								<span>( Signature)</span>
								<span>Chief Officer</span>
								<span class="signer-name">
									@if((!empty($order->cheif_ofcr_app) && $order->cheif_ofcr_app==true))
									{{!empty($order->vessel->manager_name)?$order->vessel->manager_name:''}} 
									@endif
								</span>
								<span class="seal-chief-officer seal">
									@if((!empty($order->cheif_ofcr_app) && $order->cheif_ofcr_app==true))
									<img src="{{asset('/images/chief-officer-seal.jpg')}}" alt="">
									@endif
								</span>
							</div> -->
						</div>
					</div>

					<!-- sign section for bsc admin -->
					<div id="order-print-footer2" class="print-header" hidden>
						<div class="signs-master-chief-admin">
							<div class="master-chief">
								<span class="written-sign sign"><img src="{{asset('/images/master-sign.jpg')}}" alt=""></span>
								<span>_____________________</span>
								<span>( Signature)</span>
								<span>Master/Chief Engineer</span>
								<span class="signer-name">Md. Rabiul Chowdhury</span>
								<span class="seal-master-chief seal"><img src="{{asset('/images/master.jpg')}}" alt=""></span>
							</div>
							<div class="chief-officer">
								<span class="written-sign sign"><img src="{{asset('/images/chief-sign.jpg')}}" alt=""></span>
								<span>_____________________</span>
								<span>( Signature)</span>
								<span>Asistant General Manager</span>
								<span class="signer-name">Md. Rabiul Hasan</span>
								<span class="seal-chief-officer seal"><img src="{{asset('/images/chief-officer-seal.jpg')}}" alt=""></span>
							</div>
							<div class="chief-officer">
								<span class="written-sign sign"><img src="{{asset('/images/chief-sign.jpg')}}" alt=""></span>
								<span>_____________________</span>
								<span>( Signature)</span>
								<span>General Manager</span>
								<span class="signer-name">Md. Hasan Chowdhury</span>
								<span class="seal-chief-officer seal"><img src="{{asset('/images/chief-officer-seal.jpg')}}" alt=""></span>
							</div>
						</div>
					</div>
				</div> 
			</div>                               
		</div>
	</div>
</div>

<!-- print header -->
<div id="order-print-header1" class="print-header" >
	<div class="title-wrap od-title">
		<div class="logo">
			<a href="{{url('/')}}"><img src="{{asset('/images/logo.png')}}" alt="Site Logo"></a>
		</div>
		<div class="title-center">
			<h2 class="line1">SDD/SMM/Receipt Note/Ship's Copy</h2>
			<h2 class="line2">Bangladesh Shipping Corporation</h2>
			<h2 class="line3">Ship <span></span> Repair <span></span> Department</h2>
			<h3 class="line4x"><span class="req_cat">{{!empty($order->orderItems[0]->item->category->name)?$order->orderItems[0]->item->category->name:''}}</span> Requitition</h3>
		</div>
		<div class="title-right">
		</div>			
	</div>
</div>
<div id="order-print-header3" class="print-header" >
	<table class="office-use-table">
		<body>
			<tr>
				<td colspan="2" class="office_use">Office Use</td>
				<td colspan="3" class="office_use_form">
					<p>1 Checked by <span class="checked_by"></span> Date <span class="date"></span> Passed to SSM Dept. on <span class="passed"></span></p>
					<p>2 Invitation to Tender sent on <span class="invitation"></span> Tenders received on <span class="tender_rdate"></span></p>
					<p>3 Order approved on <span class="approved_date"></span> Supply order issued on <span class="soi_date"></span></p>
					<p>4 Delivered on board on <span class="delevered_obdate"> </span> Delivery complete/incomplete <span class="dci_date"></span></p>
					<p>5 Bill received on <span class="bil_rdate"></span> Put up for approval on <span class="pua_date"></span> passed for payment on <span class="pfp_date"></span></p>
				</td>
			</tr>
		</body>
	</table>
</div>
<!-- ./print header -->
@endsection

@section('home-js')
<script>
$(function () {
	// admin-master.blade.php also does a bare $('#example').DataTable() -
	// runs after this section, but on an already-initialized table that's a
	// no-op, so this is what actually takes effect: a plain item list here
	// doesn't need paging/search/the "Show N entries" picker.
	$('#example').DataTable({
		destroy: true,
		paging: false,
		searching: false,
		info: false,
	});
});
</script>
@endsection

