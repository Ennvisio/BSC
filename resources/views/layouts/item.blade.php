@extends('layouts.admin-master')
@section('main-content')
<div class="col-lg-6 col-xl-12">
	<div class="card">
		<div class="card-header pv-card-hader">
			<strong class="pptitle">Item List</strong>

			<div class="right-buttons">		
				<button type="button" class="btn btn-primary" data-toggle="modal" data-target="#myModal"> <i class="fas fa-plus-square"></i> Add New Item </button>		
				<button class="btn btn-info btn-bvprint" onClick="print_this();"><i class="fa fa-print"></i>  Print</button>
			</div>
		</div>
		<!-- card-hader -->
		<!-- card-body -->
		<div class="card-body">
			<table id="example" class="table table-bordered dt-responsive" style="width: 100%;">
				<thead>
					<th>#</th>
					<th>Impa Code</th>
					<th>Name</th>
					<th>Unit</th>
					<th>Category</th>
					<th>Created By</th>
					<th>Updated By</th>
					<th class="action">Action</th>
				</thead>
				<tbody>
					@if(!empty($items))
					@foreach($items as $item)
					<tr id="item-{{$item->id}}">
						<td class="sl_no"> <b class="serial"> {{$loop->iteration}}</b> </td>
						<td>{{!empty($item->impa_code)?$item->impa_code:''}}</td>
						<td>{{!empty($item->name)?$item->name:''}}</td>
						<td>{{!empty($item->unit)?$item->unit:''}}</td>
						<td>{{!empty($item->category->name)?$item->category->name:''}}</td>
						<td>{{!empty($item->created_by)?$item->created_by:''}}</td>
						<td>{{!empty($item->updated_by)?$item->updated_by:''}}</td>
						<td class="action">
							<button class="btn btn-info edit-item" data-id="{{$item->id}}" data-name="{{$item->name}}" data-toggle="modal" data-target="#edit_template_modal"><i class="fas fa-edit"></i></button>
							<button class="btn btn-danger delete-item" data-id="{{$item->id}}" data-toggle="modal" data-target="#delete_template_modal"><i class="fas fa-trash-alt"></i></button>
						</td>
					</tr>
					@endforeach
					@endif
				</tbody>
			</table>
		</div>
	</div>
</div>
<!-- item Add Modal -->
<div class="modal fade" id="myModal">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<form id="item_add_form" class="form">
				@csrf
				<!-- Modal Header -->
				<div class="modal-header justify-content-between" style="background: #579eb9; color: #fff;">
					<legend class="modal-title text-center"><i class="fab fa-wpforms"></i> &nbsp;  Add New Item Form</legend>
					<button type="button" class="close" data-dismiss="modal">&times;</button>
				</div>
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
								<option selected="" value="">-- Choose Category --</option>
								@if(!empty($categories))
								@foreach($categories as $category)
								<option value="{{$category->id}}">{{$category->name}}</option>
								@endforeach
								@endif
							</select>
						</div>
					</div>
					<div class="row justify-content-center form-group">
						<div class="col-md-3">
							<label for="item_Name">Item Name: </label>
						</div>
						<div class="col-md-7">
							<input type="text" class="form-control item_Name" name="Item_Name">
						</div>
					</div>
					<div class="row justify-content-center form-group">
						<div class="col-md-3">
							<label for="impa_code">Impa Code No: </label>
						</div>
						<div class="col-md-7">
							<input type="text" class="form-control impa_code" name="Impa_Code_No">
						</div>
					</div>
					<div class="row justify-content-center form-group">
						<div class="col-md-3">
							<label for="measurement_unit">Measurement Unit: </label>
						</div>
						<div class="col-md-7">
							<input type="text" class="form-control measurement_unit" name="Measurement_Unit">
						</div>
					</div>
				</div>
				<!-- Modal footer -->
				<div class="modal-footer">
					<button type="button" class="btn btn-danger" data-dismiss="modal"> <i class="far fa-window-close"></i> Close</button>
					<button type="submit" class="btn btn-primary"> <i class="fas fa-check-square"></i> Confirm Add </button>
				</div>
			</form>
		</div>
	</div>
</div>
<!-- Edit item Template Modal -->
<div class="modal fade" id="edit_template_modal" tabindex="-1" role="dialog" aria-labelledby="" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header" style="background-color: #579eb9; padding: 10px 0;">
				<legend style="color:#fff; text-align: center; margin-bottom:0;"><i class="far fa-edit"></i> &nbsp; Update item </legend>
				<button style="color: #fff;" type="button" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true" style="padding:10px 10px 0 0;">&times;</span>
				</button>
			</div>
			<form id="item_edit_form" class="form">
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
							<label for="item_Name">Item Name: </label>
						</div>
						<div class="col-md-7">
							<input type="text" class="form-control item_Name" name="Item_Name">
						</div>
					</div>
					<div class="row justify-content-center form-group">
						<div class="col-md-3">
							<label for="impa_code">Impa Code No: </label>
						</div>
						<div class="col-md-7">
							<input type="text" class="form-control impa_code" name="Impa_Code_No">
						</div>
					</div>
					<div class="row justify-content-center form-group">
						<div class="col-md-3">
							<label for="measurement_unit">Measurement Unit: </label>
						</div>
						<div class="col-md-7">
							<input type="text" class="form-control measurement_unit" name="Measurement_Unit">
							<input type="hidden" class="form-control item_id" name="item_id">
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-danger" data-dismiss="modal">Cancel</button>
					<button type="submit" class="btn btn-primary">Update item</button>
				</div>
			</form>
		</div>
	</div>
</div>


<!-- logo-base64 for pdf page -->
@include('pdf.logo-base64')
<!-- logo-base64 for pdf page -->
@endsection
