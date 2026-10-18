@extends('layouts.admin-master')
@section('main-content')
@include('partials.pms-styles')
<div class="col-lg-6 col-xl-12">
	{{-- All six lists share this one screen; the tabs switch between them. --}}
	<div class="pms-tabs">
		@foreach($types as $key => $t)
		<a href="{{ route('pms.master.index', $key) }}" class="pms-tab {{ $key === $type ? 'active' : '' }}">
			<i class="fas {{ $t['icon'] }}"></i>{{ $t['title'] }}
		</a>
		@endforeach
	</div>

	<div class="card">
		<div class="card-header pv-card-hader">
			<strong class="pptitle">PMS &mdash; {{ $def['title'] }}</strong>
			<div class="right-buttons">
				<button type="button" class="btn btn-sm btn-primary" id="pms-master-add"><i class="fas fa-plus-square"></i> Add {{ $def['singular'] }}</button>
			</div>
		</div>
		<div class="card-body">
			@if(!empty($def['note']))
			<p class="pms-note">{{ $def['note'] }}</p>
			@endif

			<table id="pms-master-table" class="table table-bordered dt-responsive" style="width:100%;">
				<thead>
					<tr>
						<th>#</th>
						@foreach($def['fields'] as $name => $field)
						<th>{{ $field['label'] }}</th>
						@endforeach
						@if(isset($def['usage']))
						<th>Components</th>
						@endif
						<th class="action">Action</th>
					</tr>
				</thead>
				<tbody>
					@foreach($rows as $row)
					<tr>
						<td><b class="serial">{{ $loop->iteration }}</b></td>
						@foreach($def['fields'] as $name => $field)
						<td>
							@if($field['type'] === 'checkbox')
								@if($row->$name)<span class="pms-flag pms-flag-yes"><i class="fas fa-check"></i> Yes</span>@else<span class="pms-muted">No</span>@endif
							@else
								{{ $row->$name !== null && $row->$name !== '' ? $row->$name : '—' }}
							@endif
						</td>
						@endforeach
						@if(isset($def['usage']))
						<td class="pms-num">{{ $row->{$def['usage'].'_count'} }}</td>
						@endif
						<td class="action">
							<button class="btn btn-info pms-master-edit" data-id="{{ $row->id }}"
								data-row="{{ json_encode($row->only(array_keys($def['fields']))) }}"><i class="fas fa-edit"></i></button>
							<button class="btn btn-danger pms-master-delete" data-id="{{ $row->id }}"
								data-name="{{ $row->name }}"><i class="fas fa-trash-alt"></i></button>
						</td>
					</tr>
					@endforeach
				</tbody>
			</table>
		</div>
	</div>
</div>

{{-- One modal for both add and edit - the JS sets the title, the action URL
	 and the field values before opening it. --}}
<div class="modal fade" id="pms-master-modal">
	<div class="modal-dialog">
		<div class="modal-content">
			<form id="pms-master-form">
				@csrf
				<div class="modal-header justify-content-between" style="background:#579eb9; color:#fff;">
					<legend class="modal-title text-center"><i class="fab fa-wpforms"></i> &nbsp; <span id="pms-master-title"></span></legend>
					<button type="button" class="close" data-dismiss="modal" style="color:#fff;">&times;</button>
				</div>
				<div class="modal-body">
					@foreach($def['fields'] as $name => $field)
					@if($field['type'] === 'checkbox')
					<div class="custom-control custom-checkbox mb-2">
						<input type="checkbox" class="custom-control-input" id="pmsf-{{ $name }}" name="{{ $name }}" value="1">
						<label class="custom-control-label" for="pmsf-{{ $name }}">{{ $field['label'] }}</label>
					</div>
					@else
					<div class="form-group">
						<label for="pmsf-{{ $name }}">{{ $field['label'] }} @if($field['required'] ?? false)<span class="text-danger">*</span>@endif</label>
						@if($field['type'] === 'select')
						<select class="form-control" id="pmsf-{{ $name }}" name="{{ $name }}">
							<option value="">—</option>
							@foreach($field['options'] as $option)
							<option value="{{ $option }}">{{ $option }}</option>
							@endforeach
						</select>
						@else
						<input type="{{ $field['type'] === 'number' ? 'number' : 'text' }}" class="form-control" id="pmsf-{{ $name }}" name="{{ $name }}"
							@if($field['type'] === 'number') min="0" step="1" @endif
							@if(isset($field['max'])) maxlength="{{ $field['max'] }}" @endif
							@if($field['required'] ?? false) required @endif
							@if(isset($field['hint'])) placeholder="{{ $field['hint'] }}" @endif>
						@endif
					</div>
					@endif
					@endforeach
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
					<button type="submit" class="btn btn-primary">Save</button>
				</div>
			</form>
		</div>
	</div>
</div>
@endsection

@section('home-js')
<script>
$(function () {
	$('#pms-master-table').DataTable({ order: [] });

	var storeUrl = '{{ route("pms.master.store", $type) }}';
	var baseUrl = '{{ url("/pms/master/".$type) }}';
	var singular = @json($def['singular']);
	var $form = $('#pms-master-form');

	function openModal(title, url, row) {
		$form[0].reset();
		$form.data('url', url);
		$('#pms-master-title').text(title);
		$.each(row || {}, function (name, value) {
			var $input = $form.find('[name="' + name + '"]');
			if ($input.is(':checkbox')) {
				$input.prop('checked', !!value);
			} else {
				$input.val(value === null ? '' : value);
			}
		});
		$('#pms-master-modal').modal('show');
	}

	$('#pms-master-add').on('click', function () {
		openModal('Add ' + singular, storeUrl, null);
	});

	$(document).on('click', '.pms-master-edit', function () {
		openModal('Edit ' + singular, baseUrl + '/' + $(this).data('id') + '/update', $(this).data('row'));
	});

	$form.on('submit', function (e) {
		e.preventDefault();
		var $btn = $form.find('[type=submit]').prop('disabled', true);
		$.post($form.data('url'), $form.serialize())
			.done(function () { window.location.reload(); })
			.fail(function (xhr) {
				$btn.prop('disabled', false);
				var errors = xhr.responseJSON && xhr.responseJSON.errors;
				toastr.error(errors ? Object.values(errors)[0][0]
					: ((xhr.responseJSON && xhr.responseJSON.message) || 'Something went wrong.'));
			});
	});

	$(document).on('click', '.pms-master-delete', function () {
		var id = $(this).data('id');
		swal({
			title: 'Delete "' + $(this).data('name') + '"?',
			text: 'This can\'t be undone.',
			type: 'warning',
			showCancelButton: true,
			confirmButtonColor: '#d33',
			confirmButtonText: 'Yes, delete it',
		}).then(function (result) {
			if (!(result === true || (result && result.value))) { return; }
			$.post(baseUrl + '/' + id + '/delete', { _token: '{{ csrf_token() }}' })
				.done(function () { window.location.reload(); })
				.fail(function (xhr) {
					toastr.error((xhr.responseJSON && xhr.responseJSON.message) || 'Something went wrong.');
				});
		});
	});
});
</script>
@endsection
