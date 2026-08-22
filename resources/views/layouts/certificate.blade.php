@extends('layouts.admin-master')
@section('main-content')
<div class="col-lg-6 col-xl-12">
	<div class="card">
		<div class="card-header pv-card-hader">
			<strong class="pptitle">Vessel's Certificate List</strong>
			<form id="certificate_search_form" class="form form-inline" method="post" action="{{url('/search/certificate')}}">
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
				<button type="button" class="btn btn-primary" data-toggle="modal" data-target="#myModal"> <i class="fas fa-plus-square"></i> Add New Certificate</button>
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
					<th>Issueing Authority</th>
					<th>Issued On</th>
					<th>Expiry</th>
					<th>Vessel Name</th>
					<th class="tdfile">Cert. Copy</th>
					<th class="action">Action</th>
				</thead>
				<tbody>
					@if(!empty($vessel_certificates))
					@foreach($vessel_certificates as $vessel_cert)
					<tr id="certificate-{{$vessel_cert->id}}">
						<td class="sl_no"> <b class="serial"> {{$loop->iteration}}</b> </td>
						<td>{{!empty($vessel_cert->certificate->name)?$vessel_cert->certificate->name:''}}</td>
						<td>{{!empty($vessel_cert->issue_auth)?$vessel_cert->issue_auth:''}}</td>
						<td>{{!empty($vessel_cert->issue_date)?$vessel_cert->issue_date:''}}</td>
						<td>{{!empty($vessel_cert->exp_date)?$vessel_cert->exp_date:''}}</td>
						<td>{{!empty($vessel_cert->vessel->name)?$vessel_cert->vessel->name:''}}</td>
						<td class="tdfile">
							<button type="button" class="cert_file btn btn-info" data-toggle="modal" data-target="#fileShowModal" data-file="{{url('/')}}/{{!empty($vessel_cert->cert_copy)?$vessel_cert->cert_copy:''}}" data-name="{{!empty($vessel_cert->certificate->name)?$vessel_cert->certificate->name:''}}"> 
								<i class="fas fa-eye"></i> Show File
							</button>
						</td>
						<td class="action">
							<button class="btn btn-info edit-certificate" data-id="{{$vessel_cert->id}}" data-toggle="modal" data-target="#edit_template_modal"><i class="fas fa-edit"></i></button>
							<button class="btn btn-danger delete-certificate" data-id="{{$vessel_cert->id}}" data-toggle="modal" data-target="#delete_template_modal"><i class="fas fa-trash-alt"></i></button>
						</td>
					</tr>
					@endforeach
					@endif
				</tbody>
			</table>
		</div>
	</div>
</div>
<!-- Certificate Add Modal -->
<div class="modal fade" id="myModal">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<form id="certificate_add_form" class="form">
				@csrf
				<!-- Modal Header -->
				<div class="modal-header justify-content-between" style="background: #579eb9; color: #fff;">
					<legend class="modal-title text-center"><i class="fab fa-wpforms"></i> &nbsp; Fill Up Form To Add New Certificate</legend>
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
							<label for="Certificate_Name">Certificate Name: </label>
						</div>
						<div class="col-md-7">
							<select class="form-control Certificate_Name " name="Certificate_Name">
								<option selected="" value="">-- Choose Vessel --</option>
								@if(!empty($certificates))
								@foreach($certificates as $certificate)
								<option value="{{$certificate->id}}">{{$certificate->name}}</option>
								@endforeach
								@endif
							</select>
						</div>
					</div>
					<div class="row justify-content-center form-group">
						<div class="col-md-3">
							<label for="Issuing_Authority">Issuing Authority: </label>
						</div>
						<div class="col-md-7">
							<input type="text" class="form-control Issuing_Authority" name="Issuing_Authority">
						</div>
					</div>
					<div class="row justify-content-center form-group">
						<div class="col-md-3">
							<label for="Issue_Date">Issue Date: </label>
						</div>
						<div class="col-md-7">
							<input type="text" class="form-control date Issue_Date" name="Issue_Date">
						</div>
					</div>
					<div class="row justify-content-center form-group">
						<div class="col-md-3">
							<label for="Certificate_Expire_Date">Expire Date: </label>
						</div>
						<div class="col-md-7">
							<input type="text" class="form-control date Certificate_Expire_Date" name="Certificate_Expire_Date">
						</div>
					</div>
					<div class="row justify-content-center form-group">
						<div class="col-md-3">
							<label for="Certificate_Copy">Certificate Copy: </label>
						</div>
						<div class="col-md-7">
							<div class="row justify-content-center">
								<!-- <img id="prev_image1" src=""  alt="preview certificate" hidden> -->
								<iframe id="prev_image1" src="" width="100%" frameborder="0" hidden></iframe>
							</div>
							<input type="file" id="image1" class="form-control Certificate_Copy" name="Certificate_Copy">
							
							<div class="err_msg">
								<span class="file_error"></span>
							</div>
							<input type="hidden" class="form-control Cert_Id" value="" name="Cert_Id">
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

<!-- Edit Certificate Template Modal -->
<div class="modal fade" id="edit_template_modal" tabindex="-1" role="dialog" aria-labelledby="" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header" style="background-color: #579eb9; padding: 10px 0;">
				<legend style="color:#fff; text-align: center; margin-bottom:0;"><i class="far fa-edit"></i> &nbsp; Update Certificate Info </legend>

				<button style="color: #fff;" type="button" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true" style="padding:10px 10px 0 0;">&times;</span>
				</button>
			</div>
			<form id="certificate_edit_form" class="form">
				@csrf
				<!-- Modal body -->
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
					<div class="row justify-content-center form-group">
						<div class="col-md-3">
							<label for="Certificate_Name">Certificate Name: </label>
						</div>
						<div class="col-md-7">
							<select class="form-control Certificate_Name" name="Certificate_Name">
								<option selected="" value="" class='cert_opt'>-- Choose Certificate --</option>
								@if(!empty($certificates))
								@foreach($certificates as $certificate)
								<option value="{{$certificate->id}}" class='cert_opt'>{{$certificate->name}}</option>
								@endforeach
								@endif
							</select>
						</div>
					</div>
					<div class="row justify-content-center form-group">
						<div class="col-md-3">
							<label for="Issuing_Authority">Issuing Authority: </label>
						</div>
						<div class="col-md-7">
							<input type="text" class="form-control Issuing_Authority" name="Issuing_Authority">
						</div>
					</div>
					<div class="row justify-content-center form-group">
						<div class="col-md-3">
							<label for="Issue_Date">Issue Date: </label>
						</div>
						<div class="col-md-7">
							<input type="text" class="form-control date Issue_Date" name="Issue_Date">
						</div>
					</div>
					<div class="row justify-content-center form-group">
						<div class="col-md-3">
							<label for="Certificate_Expire_Date">Expire Date: </label>
						</div>
						<div class="col-md-7">
							<input type="text" class="form-control date Certificate_Expire_Date" name="Certificate_Expire_Date">
						</div>
					</div>
					<div class="row justify-content-center form-group">
						<div class="col-md-3">
							<label for="Certificate_Copy">Certificate Copy: </label>
						</div>
						<div class="col-md-7">
							<div class="row justify-content-center">
								<iframe id="prev_image1exist" src="" width="100%" frameborder="0"></iframe>
							</div>
							<input type="file" id="image1exist"  class="form-control Certificate_Copy" name="Certificate_Copy">
							<div class="err_msg">
								<span class="file_error"></span>
							</div>
							<input type="hidden" class="form-control Cert_Id" value="" name="Cert_Id">
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-danger" data-dismiss="modal">Cancel</button>
					<button type="submit" class="btn btn-primary">Update Certificate</button>
				</div>
			</form>
		</div>
	</div>
</div>

<!-- Button trigger modal -->

<!-- File Show Modal -->
<div class="modal fade" id="fileShowModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<div class="modal-title" id="exampleModalLabel"><b>Certificate Copy of <span></span></b></div>

				<div class="right-buttons-wrapper">	
					<!--<button class="btn btn-info btn-pcert">-->
					<!--	<i class="fa fa-print"></i>  Print-->
					<!--</button>	-->

					<button type="button" class="close" data-dismiss="modal" aria-label="Close">
						<span aria-hidden="true">&times;</span>
					</button>
				</div>
			</div>
			<div class="modal-body">
				<div class="row justify-content-center" id="certificate-photo">
					<!-- <img src="" id="fileShowImg"> -->
					<iframe id="fileShowImg" src="" width="100%" height="842" frameborder="0"></iframe>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-warning" data-dismiss="modal">Close</button>
			</div>
		</div>
	</div>
</div>

@include('pdf.logo-base64')

@endsection