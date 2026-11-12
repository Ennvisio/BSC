@extends('layouts.admin-master')
@section('main-content')
{{-- Certificate categories - the sections of the TEC-04 Certificate
	 Checklist. A vessel's Master/Chief Engineer picks one of these when
	 recording a certificate, but never adds to it. Linked from the Admin
	 section of the sidebar (HomeController@getCertificateCategories). --}}
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
@endsection

@section('home-js')
<script>
$(function () {
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
});
</script>
@endsection
