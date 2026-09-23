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
			{{-- Exact-match filters, on top of DataTables' own free-text search
				 box - a superadmin scanning hundreds of users across a growing
				 fleet is looking for "everyone on this vessel" far more often
				 than typing a name, and a dropdown beats remembering/typing a
				 vessel's exact spelling. Wired in this page's own home-js below,
				 not dataForm.js - #example is reused by several other pages with
				 different columns, so filtering by column index has to live
				 where those indices are actually known. --}}
			<div class="row mb-3" id="user-filters">
				<div class="col-md-3">
					<label class="mb-1" for="filter_user_type">User Type</label>
					<select id="filter_user_type" class="form-control form-control-sm">
						<option value="">All types</option>
						<option value="ship">Ship</option>
						<option value="srd">SRD</option>
						<option value="ssm">SSM</option>
					</select>
				</div>
				<div class="col-md-3">
					<label class="mb-1" for="filter_role">Role</label>
					<select id="filter_role" class="form-control form-control-sm">
						<option value="">All roles</option>
						@foreach($data['roles']->pluck('role')->unique()->sort() as $roleSlug)
						<option value="{{ $roleSlug }}">{{ ucwords(str_replace('-', ' ', $roleSlug)) }}</option>
						@endforeach
					</select>
				</div>
				<div class="col-md-4">
					<label class="mb-1" for="filter_vessel">Vessel</label>
					<select id="filter_vessel" class="form-control form-control-sm">
						<option value="">All vessels</option>
						@if(!empty($data['vessels']))
						@foreach($data['vessels'] as $vessel)
						<option value="{{ $vessel->name }}">{{ $vessel->name }}</option>
						@endforeach
						@endif
						<option value="__none__">— Ashore (no vessel) —</option>
					</select>
				</div>
				<div class="col-md-2 d-flex align-items-end">
					<button type="button" id="clear_user_filters" class="btn btn-outline-secondary btn-sm btn-block">Clear filters</button>
				</div>
			</div>

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
					@php
						// Distinguishes the three user types at a glance in a long,
						// vessel-mixed list - ship crew (blue), shore SRD (green),
						// shore SSM (amber).
						$userTypeBadge = match($role->user_type) {
							'ship' => 'badge-primary',
							'srd' => 'badge-success',
							'ssm' => 'badge-warning',
							default => 'badge-secondary',
						};
					@endphp
					<tr id="user-{{!empty($role->user->id)?$role->user->id:''}}">
						<td class="sl_no"> <b class="serial"> {{$loop->iteration}}</b> </td>
						<td>{{!empty($role->user->name)?$role->user->name:''}}</td>
						<td>{{!empty($role->user->email)?$role->user->email:''}}</td>
						<td>{{!empty($role->role)?$role->role:''}}</td>
						<td>
							@if(!empty($role->user_type))
							<span class="badge {{ $userTypeBadge }}">{{ strtoupper($role->user_type) }}</span>
							@endif
						</td>
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
								<option value="dgm-srd" class="admin_role role-srd">DGM-(SRD)</option>
								<option value="superintendent-srd" class="admin_role role-srd">Superintendent-(SRD)</option>
								<option value="gm-srd" class="admin_role role-srd">GM-(SRD)</option>
								<option value="dgm-ssm" class="admin_role role-ssm">DGM-(SSM)</option>
								<option value="agm-ssm" class="admin_role role-ssm">AGM-(SSM)</option>
								<option value="am-ssm" class="admin_role role-ssm">AM-(SSM)</option>
								<option value="superintendent-ssm" class="admin_role role-ssm">Superintendent-(SSM)</option>
								<option value="technical-superintendent" class="admin_role role-srd">Technical Superintendent</option>
								<option value="marine-superintendent" class="admin_role role-srd">Marine Superintendent</option>
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
								<option value="dgm-srd" class="admin_role role-srd role_opt">DGM-(SRD)</option>
								<option value="superintendent-srd" class="admin_role role-srd role_opt">Superintendent-(SRD)</option>
								<option value="gm-srd" class="admin_role role-srd role_opt">GM-(SRD)</option>
								<option value="dgm-ssm" class="admin_role role-ssm role_opt">DGM-(SSM)</option>
								<option value="agm-ssm" class="admin_role role-ssm role_opt">AGM-(SSM)</option>
								<option value="am-ssm" class="admin_role role-ssm role_opt">AM-(SSM)</option>
								<option value="superintendent-ssm" class="admin_role role-ssm role_opt">Superintendent-(SSM)</option>
								<option value="technical-superintendent" class="admin_role role-srd role_opt">Technical Superintendent</option>
								<option value="marine-superintendent" class="admin_role role-srd role_opt">Marine Superintendent</option>
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
					{{-- Optional - only sent if filled, so editing a user's name or
						 vessel never forces a password change. Always blank on open
						 (see the show.bs.modal handler below): the loaded user has no
						 password to show, and leaving a previously-typed one sitting
						 here would risk silently resetting the WRONG user's password
						 if the admin edits someone else next without noticing. --}}
					<div class="row justify-content-center form-group">
						<div class="col-md-3">
							<label for="New_Password">New Password: </label>
						</div>
						<div class="col-md-7">
							<input type="password" class="form-control New_Password" name="password" autocomplete="new-password" placeholder="Leave blank to keep the current password">
						</div>
					</div>
					<div class="row justify-content-center form-group">
						<div class="col-md-3">
							<label for="Confirm_New_Password">Confirm New Password: </label>
						</div>
						<div class="col-md-7">
							<input type="password" class="form-control Confirm_New_Password" name="password_confirmation" autocomplete="new-password">
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

@section('home-js')
<script>
$(function () {
	// Re-grabs the API instance dataForm.js already initialised - calling
	// .DataTable() again on a table that already has one doesn't reinitialise
	// it, just hands back the same object, which is what makes it safe to
	// wire page-specific behaviour here rather than inside dataForm.js
	// (shared by several other pages with completely different columns).
	var usersTable = $('#example').DataTable();

	// Column cells carry markup now (the user-type badge), so filtering
	// compares against their rendered TEXT, not the raw cell content -
	// otherwise a search would have to know about the badge's own class names.
	function cellText(html) {
		return $('<div>').html(html || '').text().trim();
	}

	$.fn.dataTable.ext.search.push(function (settings, data) {
		// This search plugin runs for every DataTable on the page, not just
		// this one - #example is a shared id across the app, so the guard
		// stops these filters reaching a table they were never meant for.
		if (settings.nTable.id !== 'example') {
			return true;
		}

		var type = $('#filter_user_type').val();
		var role = $('#filter_role').val();
		var vessel = $('#filter_vessel').val();

		if (type && cellText(data[4]).toLowerCase() !== type) {
			return false;
		}
		if (role && cellText(data[3]) !== role) {
			return false;
		}
		if (vessel === '__none__' && cellText(data[5]) !== '') {
			return false;
		}
		if (vessel && vessel !== '__none__' && cellText(data[5]) !== vessel) {
			return false;
		}

		return true;
	});

	$('#filter_user_type, #filter_role, #filter_vessel').on('change', function () {
		usersTable.draw();
	});

	$('#clear_user_filters').on('click', function () {
		$('#filter_user_type, #filter_role, #filter_vessel').val('');
		usersTable.draw();
	});

	// Cleared on every open, not just after a successful save - Cancelling
	// out of an edit leaves whatever was typed sitting in the form, and the
	// next user opened for edit must never inherit it.
	$('#edit_template_modal').on('show.bs.modal', function () {
		$(this).find('.New_Password, .Confirm_New_Password').val('');
	});
});
</script>
@endsection