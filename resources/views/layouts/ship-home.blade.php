@extends('layouts.admin-master')
@section('main-content')
<div class="col-lg-12 col-md-12">
	<style>
	.item_name_wrapper {
		position: relative;
	}

	.item_name_wrapper img.field-loader {
		position: absolute;
		width: 25px;
		height: auto;
		right: 3px;
		display: none; 
		top: 50%;
		margin-top: -12.5px;
		padding: 0;
	}
</style>
{{-- Only present when HomeController@index built the dashboard for
	 chief-officer/second-engineer - Pending/Approved/Received Requisition
	 reuse this same view for every ship role without passing $stats, so
	 they render exactly as before. --}}
@isset($stats)
<div class="row mb-3">
	<div class="col-6 col-md-4 mb-3">
		<a href="{{url('/home')}}" class="text-decoration-none">
			<div class="srd-stat-card">
				<div class="srd-stat-icon is-amber"><i class="fas fa-file-alt"></i></div>
				<div class="srd-stat-body">
					<div class="srd-stat-label">My Draft Requisitions</div>
					<div class="srd-stat-value">{{ $stats['draft'] }}</div>
				</div>
			</div>
		</a>
	</div>
	<div class="col-6 col-md-4 mb-3">
		<a href="{{url('/pending/requisition')}}" class="text-decoration-none">
			<div class="srd-stat-card">
				<div class="srd-stat-icon is-blue"><i class="fas fa-hourglass-half"></i></div>
				<div class="srd-stat-body">
					<div class="srd-stat-label">In Progress</div>
					<div class="srd-stat-value">{{ $stats['in_progress'] }}</div>
				</div>
			</div>
		</a>
	</div>
	<div class="col-6 col-md-4 mb-3">
		<a href="{{url('/approved/requisition')}}" class="text-decoration-none">
			<div class="srd-stat-card">
				<div class="srd-stat-icon is-green"><i class="fas fa-truck"></i></div>
				<div class="srd-stat-body">
					<div class="srd-stat-label">Delivered - Awaiting Receipt</div>
					<div class="srd-stat-value">{{ $stats['delivered'] }}</div>
				</div>
			</div>
		</a>
	</div>
	<div class="col-6 col-md-4 mb-3">
		<a href="{{url('/received/requisition')}}" class="text-decoration-none">
			<div class="srd-stat-card">
				<div class="srd-stat-icon is-slate"><i class="fas fa-inbox"></i></div>
				<div class="srd-stat-body">
					<div class="srd-stat-label">Received</div>
					<div class="srd-stat-value">{{ $stats['received'] }}</div>
				</div>
			</div>
		</a>
	</div>
	<div class="col-6 col-md-4 mb-3">
		<a href="{{url('/catalog/browse')}}" class="text-decoration-none">
			<div class="srd-stat-card">
				<div class="srd-stat-icon is-indigo"><i class="fas fa-cubes"></i></div>
				<div class="srd-stat-body">
					<div class="srd-stat-label">Catalog Items</div>
					<div class="srd-stat-value">{{ $stats['items'] }}</div>
				</div>
			</div>
		</a>
	</div>
	<div class="col-6 col-md-4 mb-3">
		<a href="{{url('/catalog/browse')}}" class="text-decoration-none">
			<div class="srd-stat-card">
				<div class="srd-stat-icon is-pink"><i class="fas fa-exclamation-triangle"></i></div>
				<div class="srd-stat-body">
					<div class="srd-stat-label">Low Stock Items</div>
					<div class="srd-stat-value">{{ $stats['low_stock'] }}</div>
				</div>
			</div>
		</a>
	</div>
</div>
@endisset

@if(isset($drafts) && $drafts->count() > 0)
<div class="col-lg-6 col-xl-12">
	<div class="card">
		<div class="card-header pv-card-hader">
			<strong class="pptitle">My Draft Requisitions</strong>
		</div>
		<div class="card-body">
			<table class="table table-bordered">
				<thead>
					<tr>
						<th>Title</th>
						<th>Port</th>
						<th>Started</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					@foreach($drafts as $draft)
					<tr>
						<td>{{ $draft->title }}</td>
						<td>{{ $draft->port_name }}</td>
						<td>{{ $draft->created_at->format('Y-m-d H:i') }}</td>
						<td>
							@if(empty($draft->category_id))
							<a href="{{ route('requisition.step2', $draft) }}" class="btn btn-sm btn-info">Continue - Add Items</a>
							@else
							<a href="{{ route('requisition.step3', $draft) }}" class="btn btn-sm btn-info">Continue - Review &amp; Submit</a>
							@endif
						</td>
					</tr>
					@endforeach
				</tbody>
			</table>
		</div>
	</div>
</div>
@endif
<div class="col-lg-6 col-xl-12">
	<div class="card">
		<div class="card-header pv-card-hader">
			<strong class="pptitle">
				Requisition List of
				<span style="color:red;display: inline-block;padding-left: 5px;">
					{{auth()->user()->role->vessel->name}}
				</span>
			</strong>
			<div class="right-buttons">	
				@if(auth()->user()->role->role=='operator')	
				<a href="{{url('/create/order')}}" class="btn btn-primary">
					<i class="fas fa-plus-square"></i> Add New Requisition
				</a>
				@endif
				<button class="btn btn-info btn-bvprint" onClick="order_list();">
					<i class="fa fa-print"></i>  Print
				</button>
			</div>
		</div>
		<!-- /card-hader -->
		<!-- card-body -->
		<div class="card-body">
			<table id="example" class="table table-bordered dt-responsive" style="width: 100%;">
				<thead>
					<th>#</th>
					<th>Req. No</th>
					<th>Category</th>
					<th>Vessel Name</th>
					<th>Req. Date</th>
					<th>Port</th>
					<th>Stage</th>
					<th>status</th>
					<th>status from ssm</th>
					<th>Created By</th>
					<!-- <th class="action">Action</th> -->
				</thead>
				<tbody>
					@if(!empty($orders))
					@foreach($orders as $order)
					<tr id="order-{{$order->id}}">
						<td class="sl_no"> <b class="serial"> {{$loop->iteration}}</b> </td>
						<td>
							<a href="{{url('/order/detail/'.$order->id)}}" class="req_no_link">
								{{!empty($order->req_no)?$order->req_no:''}}
							</a>
						</td>
						<td>{{!empty($order->category->name)?$order->category->name:''}}</td>
						<td>{{!empty($order->vessel->name)?$order->vessel->name:''}}</td>
						<td>{{!empty($order->req_date)?$order->req_date:''}}</td>
						<td>{{!empty($order->port_name)?$order->port_name:''}}</td>
						<td><span class="badge badge-info">{{ $order->currentStageLabel() }}</span></td>
						<td>{{!empty($order->status)?$order->status:''}}</td>
						<td>{{!empty($order->status_from_am)?$order->status_from_am:''}}</td>
						<td>{{ $order->creator->name ?? '' }}</td>
					</tr>
					@endforeach
					@endif
				</tbody>
			</table>
		</div>
	</div>
</div>

<!-- Edit order Template Modal -->
<div class="modal fade" id="edit_template_modal" tabindex="-1" role="dialog" aria-labelledby="" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header" style="background-color: #579eb9; padding: 10px 0;">
				<legend style="color:#fff; text-align: center; margin-bottom:0;"><i class="far fa-edit"></i> &nbsp; Update order </legend>
				<button style="color: #fff;" type="button" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true" style="padding:10px 10px 0 0;">&times;</span>
				</button>
			</div>
			<form id="order_edit_form" class="form">
				@csrf
				<!-- Modal body -->
				<div class="modal-body">
					<div class="row justify-content-center form-group">
						<div class="col-md-11 alert alert-danger alert-dismissible fade show form_error" style="display:none" role="alert">
							<strong>Error Submission!!</strong> Please correct following info and resubmit. 
							<label>    </label>
							<button type="button" class="close close_error_alert">
								<span aria-hidden="true">&times;</span>
							</button>
						</div>
					</div>

					<div class="row justify-content-center form-group">
						<div class="col-md-3">
							<label for="Category_Name">Category Name: </label>
						</div>
						<div class="col-md-7">
							<select class="form-control Category_Name" name="Category_Name">
								<option selected="" value="" class='cat_opt'>-- Choose Category --</option>
								@if(!empty($categories))
								@foreach($categories as $category)
								<option value="{{$category->id}}" class='cat_opt'>{{$category->name}}</option>
								@endforeach
								@endif
							</select>
						</div>
					</div>

					<div class="row justify-content-center form-group">
						<div class="col-md-3">
							<label for="order_Name">order Name: </label>
						</div>
						<div class="col-md-7">
							<input type="text" class="form-control order_Name" name="order_Name">
						</div>
					</div>
					<div class="row justify-content-center form-group">
						<div class="col-md-3">
							<label for="impa_code">Impa Code No: </label>
						</div>
						<div class="col-md-7">
							<input type="number" class="form-control impa_code" name="Impa_Code_No">
						</div>
					</div>
					<div class="row justify-content-center form-group">
						<div class="col-md-3">
							<label for="measurement_unit">Measurement Unit: </label>
						</div>
						<div class="col-md-7">
							<input type="text" class="form-control measurement_unit" name="Measurement_Unit">
							<input type="hidden" class="form-control order_id" name="order_id">
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-danger" data-dismiss="modal">Cancel</button>
					<button type="submit" class="btn btn-primary">Update order</button>
				</div>
			</form>
		</div>
	</div>
</div>
@include('pdf.logo-base64')
</div> 

@endsection
