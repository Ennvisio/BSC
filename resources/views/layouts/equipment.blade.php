@extends('layouts.admin-master')
@section('main-content')
<div class="col-lg-6 col-xl-12">
	<div class="card">
		<div class="card-header pv-card-hader">
			<strong class="pptitle">Equipment &amp; Maker List &mdash; {{ auth()->user()->role->vessel->name ?? '' }}</strong>
			<div class="right-buttons">
				<button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#add-equipment-modal"><i class="fas fa-plus-square"></i> Add Equipment</button>
				<button type="button" class="btn btn-sm btn-info" data-toggle="modal" data-target="#upload-equipment-modal"><i class="fas fa-upload"></i> Upload Sheet</button>
			</div>
		</div>
		<div class="card-body">
			@if(session('message'))
			<div class="alert alert-info">{{ session('message') }}</div>
			@endif

			<table id="equipment-table" class="table table-bordered dt-responsive" style="width:100%;">
				<thead>
					<th>#</th>
					<th>Equipment Name</th>
					<th>Maker</th>
					<th class="action">Action</th>
				</thead>
				<tbody>
					@forelse($equipment as $e)
					<tr id="equipment-{{ $e->id }}">
						<td><b class="serial">{{ $loop->iteration }}</b></td>
						<td class="eq-name">{{ $e->name }}</td>
						<td class="eq-maker">{{ $e->maker ?: '—' }}</td>
						<td class="action">
							<button class="btn btn-info edit-equipment" data-id="{{ $e->id }}" data-name="{{ $e->name }}" data-maker="{{ $e->maker }}" data-toggle="modal" data-target="#edit-equipment-modal"><i class="fas fa-edit"></i></button>
							<button class="btn btn-danger delete-equipment" data-id="{{ $e->id }}"><i class="fas fa-trash-alt"></i></button>
						</td>
					</tr>
					@empty
					<tr><td colspan="4" class="text-center">No equipment on record yet - add one or upload a sheet.</td></tr>
					@endforelse
				</tbody>
			</table>
		</div>
	</div>
</div>

<!-- Add Equipment Modal -->
<div class="modal fade" id="add-equipment-modal">
	<div class="modal-dialog">
		<div class="modal-content">
			<form id="add-equipment-form">
				@csrf
				<div class="modal-header justify-content-between" style="background:#579eb9; color:#fff;">
					<legend class="modal-title text-center"><i class="fab fa-wpforms"></i> &nbsp; Add Equipment</legend>
					<button type="button" class="close" data-dismiss="modal" style="color:#fff;">&times;</button>
				</div>
				<div class="modal-body">
					<div class="form-group">
						<label>Equipment Name <span class="text-danger">*</span></label>
						<input type="text" class="form-control" name="name" required>
					</div>
					<div class="form-group">
						<label>Maker</label>
						<input type="text" class="form-control" name="maker">
					</div>
				</div>
				<div class="modal-footer">
					<button type="submit" class="btn btn-primary">Save</button>
				</div>
			</form>
		</div>
	</div>
</div>

<!-- Edit Equipment Modal -->
<div class="modal fade" id="edit-equipment-modal">
	<div class="modal-dialog">
		<div class="modal-content">
			<form id="edit-equipment-form">
				@csrf
				<input type="hidden" name="id">
				<div class="modal-header justify-content-between" style="background:#579eb9; color:#fff;">
					<legend class="modal-title text-center"><i class="fab fa-wpforms"></i> &nbsp; Edit Equipment</legend>
					<button type="button" class="close" data-dismiss="modal" style="color:#fff;">&times;</button>
				</div>
				<div class="modal-body">
					<div class="form-group">
						<label>Equipment Name <span class="text-danger">*</span></label>
						<input type="text" class="form-control" name="name" required>
					</div>
					<div class="form-group">
						<label>Maker</label>
						<input type="text" class="form-control" name="maker">
					</div>
				</div>
				<div class="modal-footer">
					<button type="submit" class="btn btn-primary">Save</button>
				</div>
			</form>
		</div>
	</div>
</div>

<!-- Upload Sheet Modal -->
<div class="modal fade" id="upload-equipment-modal">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header justify-content-between" style="background:#579eb9; color:#fff;">
				<legend class="modal-title text-center"><i class="fas fa-upload"></i> &nbsp; Upload Equipment Sheet</legend>
				<button type="button" class="close" data-dismiss="modal" style="color:#fff;">&times;</button>
			</div>
			<div class="modal-body">
				<p class="text-muted small">
					Download the current list, edit it, and upload it back &mdash; a name already on your list
					is updated (its Maker replaced), not duplicated. A blank Maker cell clears that equipment's
					maker, since there's no "leave alone" reading for a name/maker sheet the way there is for a
					stock count.
				</p>
				<a href="{{ route('equipment.template') }}" class="btn btn-sm btn-info mb-3"><i class="fas fa-download"></i> Download Current List</a>
				<form action="{{ route('equipment.upload') }}" method="POST" enctype="multipart/form-data">
					@csrf
					<div class="form-group">
						<label>Filled-in sheet (.xlsx, .xls, .csv)</label>
						<input type="file" name="equipment_file" class="form-control-file" accept=".xlsx,.xls,.csv" required>
					</div>
					<button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Upload</button>
				</form>
			</div>
		</div>
	</div>
</div>
@endsection

@section('home-js')
<script>
$(function () {
	$('#equipment-table').DataTable();

	$('#add-equipment-form').on('submit', function (e) {
		e.preventDefault();
		$.post('{{ route("equipment.store") }}', $(this).serialize())
			.done(function () { window.location.reload(); })
			.fail(function (xhr) {
				toastr.error((xhr.responseJSON && xhr.responseJSON.message) || 'Something went wrong.');
			});
	});

	$('.edit-equipment').on('click', function () {
		$('#edit-equipment-form [name=id]').val($(this).data('id'));
		$('#edit-equipment-form [name=name]').val($(this).data('name'));
		$('#edit-equipment-form [name=maker]').val($(this).data('maker'));
	});

	$('#edit-equipment-form').on('submit', function (e) {
		e.preventDefault();
		$.post('{{ route("equipment.update") }}', $(this).serialize())
			.done(function () { window.location.reload(); })
			.fail(function (xhr) {
				toastr.error((xhr.responseJSON && xhr.responseJSON.message) || 'Something went wrong.');
			});
	});

	$(document).on('click', '.delete-equipment', function () {
		var id = $(this).data('id');
		var row = $('#equipment-' + id);
		swal({
			title: 'Remove this equipment?',
			text: 'It will no longer be selectable for a Shore Repair service requisition.',
			type: 'warning',
			showCancelButton: true,
			confirmButtonColor: '#d33',
			confirmButtonText: 'Yes, remove it',
		}).then(function (result) {
			if (!(result === true || (result && result.value))) { return; }
			$.post('{{ url("/vessel/equipment/delete") }}', { _token: '{{ csrf_token() }}', id: id })
				.done(function () { $('#equipment-table').DataTable().row(row).remove().draw(); })
				.fail(function (xhr) {
					toastr.error((xhr.responseJSON && xhr.responseJSON.message) || 'Something went wrong.');
				});
		});
	});
});
</script>
@endsection
