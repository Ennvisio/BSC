@extends('layouts.admin-master')
@section('main-content') 
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
<div class="col-lg-6 col-xl-12">
	<div class="card">
		<div class="card-header pv-card-hader">
			<strong class="pptitle">Requisition Lists of
				&nbsp; 
				<span style="color:red;">{{!empty(auth()->user()->role->vessel->name)?auth()->user()->role->vessel->name:''}}</span>
			</strong>

			<div class="filter_form">
				<form id="order_search_form" class="form form-inline" method="post" action="{{url('/search/order')}}">
					@csrf
					<div class="form-group">
						<select name="ship_id" class="form-control"  id="ship_name">
							<option value="" selected="">--Select Ship--</option>
							@if(!empty($vessels))
							@foreach($vessels as $vessel)
							<option value="{{$vessel->id}}" {{$vessel->id == $ship_id ?'selected':''}} >{{$vessel->name}}</option>
							@endforeach
							@endif
						</select>
					</div>
					<div class="form-group">
						<select name="cat_id" class="form-control"  id="cate_name">
							<option value="" selected="">--Select Category--</option>
							@if(!empty($categories))
							@foreach($categories as $cat)
							<option value="{{$cat->id}}" {{$cat->id==$cat_id?'selected':''}}>{{$cat->name}}</option>
							@endforeach
							@endif
						</select>
					</div>
					<div class="form-group item_name_wrapper">
						<select name="item_id" class="form-control"  id="item_name">
							<option class="item_opt_default" value="" selected="">--Select Item--</option>
							@if(!empty($category->items))
							@foreach($category->items as $item)
							<option class="searched_item_opt" value="{{$item->id}}" {{$item->id==$item_id?'selected':''}}>{{$item->name}}</option>
							@endforeach
							@endif
						</select>
						<img class="field-loader" src="{{asset('/assets/image/f2.gif')}}">
					</div>
					<div class="form-group">
						<input type="text" class="form-control date" value="{{!empty($from_date)?$from_date:''}}" name="from_date" placeholder="From Date">
					</div>
					<div class="form-group">
						<input type="text" value="{{!empty($end_date)?$end_date:''}}" class="form-control date" name="end_date" placeholder="To Date">
					</div>
					<div class="form-group">
						<button type="submit" class="btn btn-info bsc-search">Search</button>
					</div>
				</form>
			</div>

			<div class="right-buttons">	
				<button class="btn btn-info btn-bvprint" onClick="order_list();">
					<i class="fa fa-print"></i>  Print
				</button>
			</div>

		</div>
		<!-- card-hader -->
		<!-- card-body -->
		<div class="card-body">
			<table id="example" class="table table-bordered dt-responsive" style="width: 100%;">
				<thead>
					<th>#</th>
					<th>Item</th>
					<th>Qty</th>
					<th>Category</th>
					<th>Req. No</th>
					<th>Vessel Name</th>
					<th>Req. Date</th>
					<th>Port</th>
					<th>status</th>
					<th>Created By</th>
					<th>Updated By</th>
					<!-- <th class="action">Action</th> -->
				</thead>
				<tbody>
					@if(!empty($orders))
					@foreach($orders as $order)
					@foreach($order->orderItems->whereIn('item_id',$item_id) as $orderitem)
					<tr id="order-{{$order->id}}">
						<td class="sl_no"> <b class="serial"> {{$loop->iteration}}</b> </td>
						<td>{{!empty($orderitem->item->name)?$orderitem->item->name:''}}</td>
						<td>{{!empty($orderitem->item_qty)?$orderitem->item_qty:''}}</td>
						<td>{{!empty($orderitem->item->category->name)?$orderitem->item->category->name:''}}</td>
						<td>
							<a href="{{url('/order/detail/'.$order->id)}}" class="req_no_link">
								{{!empty($order->req_no)?$order->req_no:''}}
							</a>
						</td>
						<td>{{!empty($order->vessel->name)?$order->vessel->name:''}}</td>
						<td>{{!empty($order->req_date)?$order->req_date:''}}</td>
						<td>{{!empty($order->port_name)?$order->port_name:''}}</td>

						<td>{{!empty($order->status)?$order->status:''}}</td>
						<td>{{!empty($order->created_by)?$order->created_by:''}}</td>
						<td>{{!empty($order->updated_by)?$order->updated_by:''}}</td>
					</tr>
					@endforeach
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

<!-- order-print-header -->
<div id="order-print-header" class="print-header" >
	<div class="title-wrap">
		<h2 class="line2">Bangladesh Shipping Corporation</h2>
		<h2 class="line3">Ship <span></span> Repair <span></span> Department</h2>
		<h3 class="line4">Order List</h3>
	</div>
</div>
<!-- ./order-print-header -->
@endsection