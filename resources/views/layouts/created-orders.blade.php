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
			@if(
			auth()->user()->role=='chief-officer'||
			auth()->user()->role=='second-engineer')
						<strong class="pptitle">
				Created Requisition List of <span style="color:red;display: inline-block;padding-left: 5px;"> {{auth()->user()->role->vessel->name}} </span>
			</strong>
			@endif

		

			<div class="right-buttons">	
				@if(auth()->user()->role->role=='second-engineer' || auth()->user()->role->role=='chief-officer')	
				
				<a href="{{url('/create/order')}}" class="btn btn-primary">
					<i class="fas fa-plus-square"></i> Add New Requisition
				</a>

				@endif
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
					<th>Req. No</th>
					<th>Category</th>
					<th>Vessel Name</th>
					<th>Req. Date</th>
					<th>Port</th>
					<th>status</th>
					<th>status from ssm</th>
					<th>Created By</th>
					<th>Updated By</th>
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
						<td>{{!empty($order->status)?$order->status:''}}</td>
						<td>{{!empty($order->status_from_am)?$order->status_from_am:''}}</td>
						<td>{{!empty($order->created_by)?$order->created_by:''}}</td>
						<td>{{!empty($order->updated_by)?$order->updated_by:''}}</td>
						<!-- <td class="action">
							<button class="btn btn-info edit-order" data-id="{{$order->id}}" data-name="{{$order->name}}" data-toggle="modal" data-target="#edit_template_modal"><i class="fas fa-edit"></i></button>
							<button class="btn btn-danger delete-order" data-id="{{$order->id}}" data-toggle="modal" data-target="#delete_template_modal"><i class="fas fa-trash-alt"></i></button>
						</td> -->
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

<!-- logo-base64 for pdf page -->
@include('pdf.logo-base64')
<!-- logo-base64 for pdf page -->
@endsection