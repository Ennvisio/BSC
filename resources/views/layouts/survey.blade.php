@extends('layouts.admin-master')
@section('main-content')
<div class="col-lg-6 col-xl-12">
	<div class="card">
		<div class="card-header pv-card-hader">
			<strong class="pptitle">Survey Lists</strong>
			<form id="order_search_form" class="form form-inline" method="post" action="{{url('/search/survey')}}">
				@csrf
				<div class="form-group">
					<select name="ship_id" class="form-control"  id="ship_name">
						<option value="" selected="">--Select Ship--</option>
						@if(!empty($vessels))
						@foreach($vessels as $vessel)
						<option value="{{$vessel->id}}" {{(!empty($ship_id) && $vessel->id == $ship_id) ?'selected':''}} >{{$vessel->name}}</option>
						@endforeach
						@endif
					</select>
				</div>
				<button type="submit" class="btn btn-primary ml-2"> <i class="fa fa-search" aria-hidden="true"></i> Search </button>
			</form>			

			<div class="right-buttons">  
				<button type="button" class="btn btn-primary" data-toggle="modal" data-target="#myModal"> <i class="fas fa-plus-square"></i> Add New Survey </button>
				<button class="btn btn-info btn-bvprint" onClick="print_this();"><i class="fa fa-print"></i>  Print</button>
			</div>
		</div>
		<!-- card-hader -->
		<!-- card-body -->
		<div class="card-body">
			<table id="example" class="table table-bordered dt-responsive" style="width: 100%;">
				<thead>
					<th>#</th>
					<th>Name</th>
					<th>Society Name</th>
					<th>Survey Date</th>
					<th>Exp Date</th>
					<th>Vessel Name</th>
					<th class="action">Action</th>
				</thead>
				<tbody>
					@if(!empty($vessel_surveys))
					@foreach($vessel_surveys as $vessel_survey)
					<tr id="survey-{{$vessel_survey->id}}">
						<td class="sl_no"> <b class="serial"> {{$loop->iteration}}</b> </td>
						<td>{{!empty($vessel_survey->survey->name)?$vessel_survey->survey->name:''}}</td>
						<td>{{!empty($vessel_survey->society_name)?$vessel_survey->society_name:''}}</td>
						<td>{{!empty($vessel_survey->survey_date)?$vessel_survey->survey_date:''}}</td>
						<td>{{!empty($vessel_survey->survey_exp_date)?$vessel_survey->survey_exp_date:''}}</td>
						<td>{{!empty($vessel_survey->vessel->name)?$vessel_survey->vessel->name:''}}</td>
						<td class="action">
							<button class="btn btn-info edit-survey" data-id="{{$vessel_survey->id}}" data-toggle="modal" data-target="#edit_template_modal"><i class="fas fa-edit"></i></button>
							<button class="btn btn-danger delete-survey" data-id="{{$vessel_survey->id}}" data-toggle="modal" data-target="#delete_template_modal"><i class="fas fa-trash-alt"></i></button>
						</td>
					</tr>
					@endforeach
					@endif
				</tbody>
			</table>
		</div>
	</div>
</div>
<!-- Survey Add Modal -->
<div class="modal fade" id="myModal">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<form id="survey_add_form">
				@csrf
				<!-- Modal Header -->
				<div class="modal-header justify-content-between" style="background: #579eb9; color: #fff;">
					<legend class="modal-title text-center"><i class="fab fa-wpforms"></i> &nbsp; Fill Up Form To Add New Survey</legend>
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
							<label for="Vessel_Name">Vessel: </label>
						</div>
						<div class="col-md-7">
							<select class="form-control Vessel_Name" name="Vessel_Name">
								<option selected="" value="">-- Choose Vessel --</option>
								@if(!empty($vessels))
								@foreach($vessels as $vessel)
								<option value="{{$vessel->id}}">{{$vessel->name}}</option>
								@endforeach
								@endif
							</select>
						</div>
					</div>
					<div class="row justify-content-center form-group">
						<div class="col-md-3">
							<label for="Survey_Type">Survey Name: </label>
						</div>
						<div class="col-md-7">
							<select class="form-control Survey_Name" name="Survey_Name">
								<option selected="" value="">-- Choose Survey --</option>
								@if(!empty($surveys))
								@foreach($surveys as $survey)
								<option value="{{$survey->id}}">{{$survey->name}}</option>
								@endforeach
								@endif
							</select>
						</div>
					</div><!-- 
					<div class="row justify-content-center form-group">
						<div class="col-md-3">
							<label for="Survey_Name">Survey Name: </label>
						</div>
						<div class="col-md-7">
							<input type="text" class="form-control Survey_Name" name="Survey_Name">
						</div>
					</div> -->
					<div class="row justify-content-center form-group">
						<div class="col-md-3">
							<label for="Survey_Society">Surveyer Society: </label>
						</div>
						<div class="col-md-7">
							<input type="text" class="form-control Survey_Society" name="Survey_Society">
						</div>
					</div>
					<div class="row justify-content-center form-group">
						<div class="col-md-3">
							<label for="Survey_Date">Survey Date: </label>
						</div>
						<div class="col-md-7">
							<input type="text" class="form-control date Survey_Date" name="Survey_Date">
						</div>
					</div>
					<div class="row justify-content-center form-group">
						<div class="col-md-3">
							<label for="Survey_Expire_Date">Expire Date: </label>
						</div>
						<div class="col-md-7">
							<input type="text" class="form-control date Survey_Expire_Date" name="Survey_Expire_Date">
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
<!-- Edit survey Template Modal -->
<div class="modal fade" id="edit_template_modal" tabindex="-1" role="dialog" aria-labelledby="" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header" style="background-color: #579eb9; padding: 10px 0;">
				<legend style="color:#fff; text-align: center; margin-bottom:0;"><i class="far fa-edit"></i> &nbsp; Update Survey Info </legend>
				<button style="color: #fff;" type="button" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true" style="padding:10px 10px 0 0;">&times;</span>
				</button>
			</div>
			<div class="alert alert-danger print-error-msg " style="display:none">
				<ul></ul>
			</div>
			<form id="survey_edit_form">
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
							<label for="Vessel_Name">Vessel Name: </label>
						</div>
						<div class="col-md-7">
							<select class="form-control Vessel_Name" name="Vessel_Name">
								<option selected="" value="" class='vessel_opt'>-- Choose Vessel --</option>
								@if(!empty($vessels))
								@foreach($vessels as $vessel)
								<option value="{{$vessel->id}}" class='vessel_opt'>{{$vessel->name}}</option>
								@endforeach
								@endif
							</select>
						</div>
					</div>
					<!-- <div class="row justify-content-center form-group">
						<div class="col-md-3">
							<label for="Survey_Type">Survey Type: </label>
						</div>
						<div class="col-md-7">
							<select class="form-control Survey_Type" name="Survey_Type">
								<option class='survey_type_opt' selected="" value="">-- Choose Survey Type--</option>
								<option class='survey_type_opt' value="Annual">ANNUAL SURVEY</option>
								<option class='survey_type_opt' value="Docking">DOCKING SURVEY</option>
								<option class='survey_type_opt' value="Intermediate">INTERMEDIATE SURVEY</option>
								<option class='survey_type_opt' value="Renewal">RENEWAL SURVEY</option>
							</select>
						</div>
					</div> -->
					<div class="row justify-content-center form-group">
						<div class="col-md-3">
							<label for="Survey_Name">Survey Name: </label>
						</div>
						<div class="col-md-7">
							<!-- <input type="text" class="form-control Survey_Name" name="Survey_Name"> -->
							<select class="form-control Survey_Name" name="Survey_Name">
								<option selected="" value="" class='survey_opt'>-- Choose Survey --</option>
								@if(!empty($surveys))
								@foreach($surveys as $survey)
								<option value="{{$survey->id}}" class='survey_opt'>{{$survey->name}}</option>
								@endforeach
								@endif
							</select>
						</div>
					</div>
					<div class="row justify-content-center form-group">
						<div class="col-md-3">
							<label for="Survey_Society">Surveyer Society: </label>
						</div>
						<div class="col-md-7">
							<input type="text" class="form-control Survey_Society" name="Survey_Society">
						</div>
					</div>
					<div class="row justify-content-center form-group">
						<div class="col-md-3">
							<label for="Survey_Date">Survey Date: </label>
						</div>
						<div class="col-md-7">
							<input type="text" class="form-control date Survey_Date" name="Survey_Date">
						</div>
					</div>
					<div class="row justify-content-center form-group">
						<div class="col-md-3">
							<label for="Survey_Expire_Date">Expire Date: </label>
						</div>
						<div class="col-md-7">
							<input type="text" class="form-control date Survey_Expire_Date" name="Survey_Expire_Date">
							<input type="hidden" class="form-control Survey_Id" value="" name="survey_Id">
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-danger" data-dismiss="modal">Cancel</button>
					<button type="submit" class="btn btn-primary">Update Survey</button>
				</div>
			</form>
		</div>
	</div>
</div>

@include('pdf.logo-base64')

@endsection