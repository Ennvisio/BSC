@extends('layouts.admin-master')
@section('main-content')
@if($categoriesManageable)
{{-- Certificate categories - the sections of the TEC-04 Certificate
	 Checklist. Super-admin's list only: a vessel's Master/Chief Engineer
	 picks one of these when recording a certificate, but never adds to it. --}}
<div class="col-lg-6 col-xl-12">
	<div class="card">
		<div class="card-header pv-card-hader">
			<strong class="pptitle">Certificate Categories</strong>
			<div class="right-buttons">
				<button type="button" class="btn btn-primary" data-toggle="modal" data-target="#certCat_add_modal"> <i class="fas fa-plus-square"></i> Add Category</button>
			</div>
		</div>
		<div class="card-body">
			<table id="cert-category-table" class="table table-bordered dt-responsive" style="width:100%;">
				<thead>
					<th>#</th>
					<th>Category</th>
					<th class="action">Action</th>
				</thead>
				<tbody>
					@foreach($categories as $category)
					<tr id="cert-category-{{$category->id}}">
						<td><b class="serial">{{$loop->iteration}}</b></td>
						<td class="cc-name">{{$category->name}}</td>
						<td class="action">
							<button class="btn btn-info btn-sm edit-cert-category" data-id="{{$category->id}}" data-name="{{$category->name}}" data-toggle="modal" data-target="#certCat_edit_modal"><i class="fas fa-edit"></i></button>
							<button class="btn btn-danger btn-sm delete-cert-category" data-id="{{$category->id}}"><i class="fas fa-trash-alt"></i></button>
						</td>
					</tr>
					@endforeach
				</tbody>
			</table>
		</div>
	</div>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="certCat_add_modal">
	<div class="modal-dialog">
		<div class="modal-content">
			<form id="certCat_add_form">
				@csrf
				<div class="modal-header justify-content-between" style="background:#579eb9; color:#fff;">
					<legend class="modal-title text-center"><i class="fab fa-wpforms"></i> &nbsp; Add Certificate Category</legend>
					<button type="button" class="close" data-dismiss="modal" style="color:#fff;">&times;</button>
				</div>
				<div class="modal-body">
					<div class="alert alert-danger form_error" style="display:none"></div>
					<div class="form-group">
						<label>Category Name <span class="text-danger">*</span></label>
						<input type="text" class="form-control" name="name" placeholder="e.g. Statutory Certificates" required>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
					<button type="submit" class="btn btn-primary">Add Category</button>
				</div>
			</form>
		</div>
	</div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="certCat_edit_modal">
	<div class="modal-dialog">
		<div class="modal-content">
			<form id="certCat_edit_form">
				@csrf
				<input type="hidden" name="category_id">
				<div class="modal-header justify-content-between" style="background:#579eb9; color:#fff;">
					<legend class="modal-title text-center"><i class="far fa-edit"></i> &nbsp; Edit Certificate Category</legend>
					<button type="button" class="close" data-dismiss="modal" style="color:#fff;">&times;</button>
				</div>
				<div class="modal-body">
					<div class="alert alert-danger form_error" style="display:none"></div>
					<div class="form-group">
						<label>Category Name <span class="text-danger">*</span></label>
						<input type="text" class="form-control" name="name" required>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-danger" data-dismiss="modal">Cancel</button>
					<button type="submit" class="btn btn-primary">Update Category</button>
				</div>
			</form>
		</div>
	</div>
</div>
@endif

<div class="col-lg-6 col-xl-12">
	<div class="card">
		<div class="card-header pv-card-hader">
			@if($lockedVessel)
			<strong class="pptitle">Certificate List &mdash; {{ $lockedVessel->name }}</strong>
			@else
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
			@endif
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
					<th>Category</th>
					<th>Certificate Title</th>
					<th>Issueing Authority</th>
					<th>Issued On</th>
					<th>Renewal</th>
					<th>Expiry</th>
					@if(!$lockedVessel)
					<th>Vessel Name</th>
					@endif
					<th class="tdfile">Cert. Copy</th>
					<th class="action">Action</th>
				</thead>
				<tbody>
					@if(!empty($vessel_certificates))
					@foreach($vessel_certificates as $vessel_cert)
					<tr id="certificate-{{$vessel_cert->id}}">
						<td class="sl_no"> <b class="serial"> {{$loop->iteration}}</b> </td>
						<td>{{ $vessel_cert->category->name ?? '' }}</td>
						<td>{{ $vessel_cert->title }}</td>
						<td>{{!empty($vessel_cert->issue_auth)?$vessel_cert->issue_auth:''}}</td>
						<td>{{!empty($vessel_cert->issue_date)?$vessel_cert->issue_date:''}}</td>
						<td>
							@if($vessel_cert->is_permanent)
							<span class="badge badge-success">Permanent</span>
							@elseif($vessel_cert->validity_years)
							<span class="badge badge-info">{{$vessel_cert->validity_years}} {{ $vessel_cert->validity_years == 1 ? 'year' : 'years' }}</span>
							@else
							<span class="text-muted">—</span>
							@endif
						</td>
						<td>
							@if($vessel_cert->is_permanent)
							<span class="text-muted">—</span>
							@else
							{{!empty($vessel_cert->exp_date)?$vessel_cert->exp_date:''}}
							@endif
						</td>
						@if(!$lockedVessel)
						<td>{{!empty($vessel_cert->vessel->name)?$vessel_cert->vessel->name:''}}</td>
						@endif
						<td class="tdfile">
							<button type="button" class="cert_file btn btn-info btn-sm" data-toggle="modal" data-target="#fileShowModal" data-file="{{url('/')}}/{{!empty($vessel_cert->cert_copy)?$vessel_cert->cert_copy:''}}" data-name="{{ $vessel_cert->title }}">
								<i class="fas fa-eye"></i> Show File
							</button>
						</td>
						<td class="action">
							<button class="btn btn-info btn-sm edit-certificate" data-id="{{$vessel_cert->id}}" data-toggle="modal" data-target="#edit_template_modal"><i class="fas fa-edit"></i></button>
							<button class="btn btn-danger btn-sm delete-certificate" data-id="{{$vessel_cert->id}}" data-toggle="modal" data-target="#delete_template_modal"><i class="fas fa-trash-alt"></i></button>
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
					{{-- A vessel's own Master/Chief Engineer only ever records
						 certificates for their own ship (the controller forces it
						 from their role regardless of what's posted), so there's
						 nothing to choose or read here - just the hidden value the
						 validator expects. --}}
					@if($lockedVessel)
					<input type="hidden" name="Vessel_Name" value="{{$lockedVessel->id}}">
					@else
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
					@endif
					<div class="row justify-content-center form-group">
						<div class="col-md-3">
							<label for="category_id">Category: <span class="text-danger">*</span></label>
						</div>
						<div class="col-md-7">
							<select class="form-control category_id" name="category_id">
								<option selected="" value="">-- Choose Category --</option>
								@foreach($categories as $category)
								<option value="{{$category->id}}">{{$category->name}}</option>
								@endforeach
							</select>
						</div>
					</div>
					<div class="row justify-content-center form-group">
						<div class="col-md-3">
							<label for="title">Certificate Title: <span class="text-danger">*</span></label>
						</div>
						<div class="col-md-7">
							<input type="text" class="form-control cert-title" name="title" placeholder="e.g. Certificate of Registry">
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
					<div class="row justify-content-center form-group cert-validity-group">
						<div class="col-md-3">
							<label for="validity_years">Renews every (years): </label>
						</div>
						<div class="col-md-7">
							<input type="number" min="1" max="50" class="form-control cert-validity" name="validity_years">
						</div>
					</div>
					<div class="row justify-content-center form-group">
						<div class="col-md-7 offset-md-3">
							<div class="custom-control custom-checkbox">
								<input type="checkbox" class="custom-control-input cert-permanent" id="cert-permanent-add" name="is_permanent" value="1">
								<label class="custom-control-label" for="cert-permanent-add">No expiry (Permanent)</label>
							</div>
						</div>
					</div>
					<div class="row justify-content-center form-group cert-expiry-row">
						<div class="col-md-3">
							<label for="Certificate_Expire_Date">Expire Date: </label>
						</div>
						<div class="col-md-7">
							<input type="text" class="form-control date Certificate_Expire_Date" name="Certificate_Expire_Date">
							<small class="form-text text-muted">Filled in from Issue Date + years. Enter it directly instead if the certificate says otherwise - one of the two is needed.</small>
						</div>
					</div>
					<div class="row justify-content-center form-group">
						<div class="col-md-3">
							<label for="Certificate_Copy">Certificate Copy: </label>
						</div>
						<div class="col-md-7">
							<div class="row justify-content-center">
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
					{{-- Hidden for a vessel-scoped Master/Chief Engineer - see the
						 same note on the Add modal above. --}}
					@if($lockedVessel)
					<input type="hidden" name="Vessel_Name" value="{{$lockedVessel->id}}">
					@else
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
					@endif
					<div class="row justify-content-center form-group">
						<div class="col-md-3">
							<label for="category_id">Category: <span class="text-danger">*</span></label>
						</div>
						<div class="col-md-7">
							<select class="form-control category_id" name="category_id">
								<option selected="" value="" class='cat_opt'>-- Choose Category --</option>
								@foreach($categories as $category)
								<option value="{{$category->id}}" class='cat_opt'>{{$category->name}}</option>
								@endforeach
							</select>
						</div>
					</div>
					<div class="row justify-content-center form-group">
						<div class="col-md-3">
							<label for="title">Certificate Title: <span class="text-danger">*</span></label>
						</div>
						<div class="col-md-7">
							<input type="text" class="form-control cert-title" name="title">
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
					<div class="row justify-content-center form-group cert-validity-group">
						<div class="col-md-3">
							<label for="validity_years">Renews every (years): </label>
						</div>
						<div class="col-md-7">
							<input type="number" min="1" max="50" class="form-control cert-validity" name="validity_years">
						</div>
					</div>
					<div class="row justify-content-center form-group">
						<div class="col-md-7 offset-md-3">
							<div class="custom-control custom-checkbox">
								<input type="checkbox" class="custom-control-input cert-permanent" id="cert-permanent-edit" name="is_permanent" value="1">
								<label class="custom-control-label" for="cert-permanent-edit">No expiry (Permanent)</label>
							</div>
						</div>
					</div>
					<div class="row justify-content-center form-group cert-expiry-row">
						<div class="col-md-3">
							<label for="Certificate_Expire_Date">Expire Date: </label>
						</div>
						<div class="col-md-7">
							<input type="text" class="form-control date Certificate_Expire_Date" name="Certificate_Expire_Date">
							<small class="form-text text-muted">Filled in from Issue Date + years. Enter it directly instead if the certificate says otherwise - one of the two is needed.</small>
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

<!-- File Show Modal -->
<div class="modal fade" id="fileShowModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<div class="modal-title" id="exampleModalLabel"><b>Certificate Copy of <span></span></b></div>

				<div class="right-buttons-wrapper">
					<button type="button" class="close" data-dismiss="modal" aria-label="Close">
						<span aria-hidden="true">&times;</span>
					</button>
				</div>
			</div>
			<div class="modal-body">
				<div class="row justify-content-center" id="certificate-photo">
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

@section('home-js')
<script>
$(function () {
	// Permanent means no expiry and no renewal cycle - hide and clear both.
	function syncPermanentToggle($form) {
		var checked = $form.find('.cert-permanent').is(':checked');
		$form.find('.cert-expiry-row').toggle(!checked);
		$form.find('.cert-validity-group').toggle(!checked);
		if (checked) {
			$form.find('.Certificate_Expire_Date').val('');
			$form.find('input[name=validity_years]').val('');
		}
	}
	$(document).on('change', '.cert-permanent', function () { syncPermanentToggle($(this).closest('form')); });
	syncPermanentToggle($('#certificate_add_form'));

	// Expire Date = Issue Date + however many years it runs for. Filled in
	// as soon as both are known so nobody has to work it out by hand, but
	// left editable: a real certificate's expiry isn't always exactly
	// issue + N years. The server does the same sum for whatever is left
	// blank (HomeController::expiryDateFor).
	function recalcExpiry($form) {
		if ($form.find('.cert-permanent').is(':checked')) { return; }

		var issued = $form.find('.Issue_Date').val();
		var years = parseInt($form.find('.cert-validity').val(), 10);
		if (!issued || !years) { return; }

		// Issue Date is Y-m-d (see the Zebra_DatePicker setup in
		// admin-master.blade.php), so it can be split rather than handed to
		// Date(), which parses a bare date string as UTC and can shift the
		// day backwards in timezones behind it.
		var parts = issued.split('-');
		if (parts.length !== 3) { return; }

		var d = new Date(+parts[0] + years, +parts[1] - 1, +parts[2]);
		if (isNaN(d.getTime())) { return; }

		var pad = function (n) { return (n < 10 ? '0' : '') + n; };
		$form.find('.Certificate_Expire_Date')
			.val(d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()));
	}

	$(document).on('change keyup', '.Issue_Date, .cert-validity', function () {
		recalcExpiry($(this).closest('form'));
	});

	@if($categoriesManageable)
	$('#cert-category-table').DataTable();

	$('#certCat_add_form').on('submit', function (e) {
		e.preventDefault();
		$(this).find('.form_error').hide().empty();
		$.post('{{ route("store.certificate-category") }}', $(this).serialize())
			.done(function () { window.location.reload(); })
			.fail(function (xhr) {
				var msg = (xhr.responseJSON && (xhr.responseJSON.message || Object.values(xhr.responseJSON.errors || {}).flat().join(' '))) || 'Something went wrong.';
				$('#certCat_add_form .form_error').text(msg).show();
			});
	});

	$('.edit-cert-category').on('click', function () {
		var $form = $('#certCat_edit_form');
		$form.find('[name=category_id]').val($(this).data('id'));
		$form.find('[name=name]').val($(this).data('name'));
	});

	$('#certCat_edit_form').on('submit', function (e) {
		e.preventDefault();
		$(this).find('.form_error').hide().empty();
		$.post('{{ route("update.certificate-category") }}', $(this).serialize())
			.done(function () { window.location.reload(); })
			.fail(function (xhr) {
				var msg = (xhr.responseJSON && (xhr.responseJSON.message || Object.values(xhr.responseJSON.errors || {}).flat().join(' '))) || 'Something went wrong.';
				$('#certCat_edit_form .form_error').text(msg).show();
			});
	});

	$(document).on('click', '.delete-cert-category', function () {
		var id = $(this).data('id');
		var row = $('#cert-category-' + id);
		swal({
			title: 'Remove this category?',
			text: 'Refused while any vessel still files a certificate under it.',
			type: 'warning', showCancelButton: true, confirmButtonColor: '#d33',
			confirmButtonText: 'Yes, remove it',
		}).then(function (result) {
			if (!(result === true || (result && result.value))) { return; }
			$.post('{{ route("delete.certificate-category") }}', { _token: '{{ csrf_token() }}', id: id })
				.done(function () { $('#cert-category-table').DataTable().row(row).remove().draw(); })
				.fail(function (xhr) {
					swal('Not removed', (xhr.responseJSON && xhr.responseJSON.message) || 'Something went wrong.', 'error');
				});
		});
	});
	@endif
});
</script>
@endsection
