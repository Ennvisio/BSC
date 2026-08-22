@extends('layouts.admin-master')
@section('main-content')
<div class="col-lg-6 col-xl-12">
	<div class="card">
		<div class="card-header pv-card-hader">
			<strong class="pptitle">User Lists</strong>

			<div class="right-buttons users-btns">
				<button type="button" class="btn btn-primary" data-toggle="modal" data-target="#myModal"> <i class="fas fa-plus-square"></i> Add New User </button>
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
					<th>Email</th>
					<th>Role</th>
					<th>User Type</th>
					<th>Vessel Name</th>
					<th>Created By</th>
					<th>Updated By</th>
					<th class="action">Action</th>
				</thead>
				<tbody>
					@if(!empty($data['roles']))
					@foreach($data['roles'] as $role)
					<tr id="user-{{!empty($role->user->id)?$role->user->id:''}}">
						<td class="sl_no"> <b class="serial"> {{$loop->iteration}}</b> </td>
						<td>{{!empty($role->user->name)?$role->user->name:''}}</td>
						<td>{{!empty($role->user->email)?$role->user->email:''}}</td>
						<td>{{!empty($role->role)?$role->role:''}}</td>
						<td>{{!empty($role->user_type)?$role->user_type:''}}</td>
						<td>{{!empty($role->vessel->name)?$role->vessel->name:''}}</td>
						<td>
							{{!empty($role->created_by)?$role->created_by:''}} <br> {{!empty($role->user->created_at)?$role->user->created_at:''}}
						</td>
						<td>
							{{!empty($role->updated_by)?$role->updated_by:''}} <br>
							{{!empty($role->user->updated_at)?$role->user->updated_at:''}}
						</td>
						<td class="action">
							<button class="btn btn-info edit-user" data-id="{{!empty($role->user->id)?$role->user->id:''}}" data-toggle="modal" data-target="#edit_template_modal"><i class="fas fa-edit"></i>
							</button>
							<button class="btn btn-danger delete-user" data-id="{{!empty($role->user->id)?$role->user->id:''}}" data-toggle="modal" data-target="#delete_template_modal"><i class="fas fa-trash-alt"></i></button>
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
			<form id="user_add_form" class="form">
				@csrf
				<!-- Modal Header -->
				<div class="modal-header justify-content-between" style="background: #579eb9; color: #fff;">
					<legend class="modal-title text-center"><i class="fab fa-wpforms"></i> &nbsp; Fill Up Form To Add New User</legend>
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
					<div class="row funkyradio justify-content-center mb-3">
						<div class="funkyradio-primary col-md-3">
							<input type="radio" name="user_type" id="ship_user" value="ship"/>
							<label for="ship_user">Ship</label>
						</div>
						<div class="funkyradio-info col-md-3">
							<input type="radio" name="user_type" id="ssm_user" value="ssm"/>
							<label for="ssm_user">SSM</label>
						</div>
						<div class="funkyradio-success col-md-3">
							<input type="radio" name="user_type" id="srd_user" value="srd"/>
							<label for="srd_user">SRD</label>
						</div>
					</div>

					<div class="row justify-content-center form-group for_ship_user" hidden>
						<div class="col-md-3">
							<label for="Vessel_Name">Vessel: </label>
						</div>
						<div class="col-md-7">
							<select class="form-control Vessel_Name" name="Vessel_Name">
								<option selected="" value="">-- Choose Vessel --</option>
								@if(!empty($data['vessels']))
								@foreach($data['vessels'] as $vessel)
								<option value="{{$vessel->id}}">{{$vessel->name}}</option>
								@endforeach
								@endif
							</select>
						</div>
					</div>
					<div class="row justify-content-center all_user form-group" hidden>
						<div class="col-md-3">
							<label for="User_Role">User Role: </label>
						</div>
						<div class="col-md-7">
							<select class="form-control User_Role" name="User_Role">
								<option selected="" value="">-- Choose Role --</option>
								<option value="second-engineer" class="ship_role">Second Engineer</option>
								<option value="chief-officer" class="ship_role">Chief Officer</option>
								<option value="master" class="ship_role">Master</option>
								<option value="chief-engineer" class="ship_role">Chief Engineer</option>
								<option value="am-srd" class="admin_role role-srd">AM-(SRD)</option>
								<option value="agm-srd" class="admin_role role-srd">AGM-(SRD)</option>
								<option value="gm-srd" class="admin_role role-srd">GM-(SRD)</option>
								<option value="dgm-ssm" class="admin_role role-ssm">DGM-(SSM)</option>
								<option value="agm-ssm" class="admin_role role-ssm">AGM-(SSM)</option>
								<option value="am-ssm" class="admin_role role-ssm">AM-(SSM)</option>
							</select>
						</div>
					</div>
					<div class="row justify-content-center all_user form-group" hidden>
						<div class="col-md-3">
							<label for="User_Name">User Name: </label>
						</div>
						<div class="col-md-7">
							<input type="text" class="form-control User_Name" name="User_Name">
						</div>
					</div>
					<div class="row justify-content-center all_user form-group" hidden>
						<div class="col-md-3">
							<label for="Email"> Email </label>
						</div>
						<div class="col-md-7">
							<input type="email" class="form-control Email" name="email">
						</div>
					</div>
					<div class="row justify-content-center all_user form-group" hidden>
						<div class="col-md-3">
							<label for="Password">Password: </label>
						</div>
						<div class="col-md-7">
							<input type="password" class="form-control  Password" name="password">
						</div>
					</div>
					<div class="row justify-content-center all_user form-group" hidden>
						<div class="col-md-3">
							<label for="Conirm_Password">Confirm Password: </label>
						</div>
						<div class="col-md-7">
							<input type="password" class="form-control  Conirm_Password" name="password_confirmation">
							<input type="hidden" class="form-control vessel_not_for_admin" value="0" name="Vessel_Name" disabled>
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
<!-- Edit User Template Modal -->
<div class="modal fade" id="edit_template_modal" tabindex="-1" role="dialog" aria-labelledby="" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header" style="background-color: #579eb9; padding: 10px 0;">
				<legend style="color:#fff; text-align: center; margin-bottom:0;"><i class="far fa-edit"></i> &nbsp; Update User Info </legend>
				<button style="color: #fff;" type="button" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true" style="padding:10px 10px 0 0;">&times;</span>
				</button>
			</div>
			<form id="user_edit_form" class="form">
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
					<div class="row funkyradio justify-content-center mb-3">
						<div class="funkyradio-primary col-md-3">
							<input type="radio" name="user_type" id="ship_user_edit" value="ship">
							<label for="ship_user_edit">Ship</label>
						</div>
						<div class="funkyradio-info col-md-3">
							<input type="radio" name="user_type" id="ssm_user_edit" value="ssm">
							<label for="ssm_user_edit">SSM</label>
						</div>
						<div class="funkyradio-success col-md-3">
							<input type="radio" name="user_type" id="srd_user_edit" value="srd">
							<label for="srd_user_edit">SRD</label>
						</div>
					</div>
					<div class="row justify-content-center all_user form-group">
						<div class="col-md-3">
							<label for="User_Role">User Role: </label>
						</div>
						<div class="col-md-7">
							<select class="form-control User_Role" name="User_Role">
								<option selected="" value="">-- Choose Role --</option>
								<option value="second-engineer" class="ship_role role_opt">Second Engineer</option>
								<option value="chief-officer" class="ship_role role_opt">Chief Officer</option>
								<option value="master" class="ship_role role_opt">Master</option>
								<option value="chief-engineer" class="ship_role role_opt">Chief Engineer</option>
								<option value="am-srd" class="admin_role role-srd role_opt">AM-(SRD)</option>
								<option value="agm-srd" class="admin_role role-srd role_opt">AGM-(SRD)</option>
								<option value="gm-srd" class="admin_role role-srd role_opt">GM-(SRD)</option>
								<option value="dgm-ssm" class="admin_role role-ssm role_opt">DGM-(SSM)</option>
								<option value="agm-ssm" class="admin_role role-ssm role_opt">AGM-(SSM)</option>
								<option value="am-ssm" class="admin_role role-ssm role_opt">AM-(SSM)</option>
							</select>
						</div>
					</div>
					<div class="row justify-content-center form-group for_ship_user">
						<div class="col-md-3">
							<label for="Vessel_Name">Vessel: </label>
						</div>
						<div class="col-md-7">
							<select class="form-control Vessel_Name" name="Vessel_Name">
								<option selected="" value="">-- Choose Vessel --</option>
								@if(!empty($data['vessels']))
								@foreach($data['vessels'] as $vessel)
								<option class="vessel_opt" value="{{$vessel->id}}">{{$vessel->name}}</option>
								@endforeach
								@endif
							</select>
						</div>
					</div>
					<div class="row justify-content-center form-group">
						<div class="col-md-3">
							<label for="User_Name">User Name: </label>
						</div>
						<div class="col-md-7">
							<input type="text" class="form-control User_Name" name="User_Name">
						</div>
					</div>
					<div class="row justify-content-center form-group">
						<div class="col-md-3">
							<label for="Email"> Email </label>
						</div>
						<div class="col-md-7">
							<input type="email" class="form-control Email" name="email">
							<input type="hidden" class="form-control user_id" value=""	name="user_id">
							<input type="hidden" class="form-control vessel_not_for_admin" value="0" name="Vessel_Name" disabled>
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-danger" data-dismiss="modal">Cancel</button>
					<button type="submit" class="btn btn-primary">Update User</button>
				</div>
			</form>
		</div>
	</div>
</div>

<!-- logo-base64 for pdf page -->
@include('pdf.logo-base64')
<!-- logo-base64 for pdf page -->
@endsection