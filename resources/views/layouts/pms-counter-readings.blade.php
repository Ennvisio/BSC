@extends('layouts.admin-master')
@section('main-content')
@include('partials.pms-styles')
<div class="col-lg-6 col-xl-12">
	<div class="card">
		<div class="card-header pv-card-hader">
			<strong class="pptitle">Running Hours &mdash; {{ $component->name }}</strong>
			<div class="right-buttons">
				<a href="{{ route('pms.components.index', ['vessel_id' => $component->vessel_id]) }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-sitemap"></i> Back to components</a>
			</div>
		</div>
		<div class="card-body">
			<p class="pms-note">
				{{ $component->vessel->name ?? '' }} &rsaquo; {{ $component->location->name ?? '' }}
				@if($component->parent) &rsaquo; {{ $component->parent->name }}@endif
				&rsaquo; <strong>{{ $component->name }}</strong>.
				Each reading is the total on the meter that day.
			</p>

			@if(!$component->has_counter)
			<div class="alert alert-warning">This component doesn't have a running-hour counter, so no new readings can be added.</div>
			@elseif($canEdit)
			<form id="pms-reading-form" class="form-inline mb-3" style="gap:10px;">
				@csrf
				<label for="pr-date" class="sr-only">Reading date</label>
				<input type="date" class="form-control" id="pr-date" name="reading_date" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required>
				<label for="pr-value" class="sr-only">Meter reading (hours)</label>
				<input type="number" class="form-control" id="pr-value" name="value" step="0.1" min="0" placeholder="Meter total (h)" required>
				<button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add Reading</button>
			</form>
			@endif

			<table id="pms-readings-table" class="table table-bordered" style="width:100%;">
				<thead>
					<tr>
						<th>Date</th>
						<th class="pms-num">Meter (h)</th>
						<th class="pms-num">Since previous (h)</th>
						<th>Entered by</th>
						@if($canEdit)<th class="action">Action</th>@endif
					</tr>
				</thead>
				<tbody>
					@forelse($readings as $reading)
					<tr>
						<td data-order="{{ $reading->reading_date->format('Y-m-d') }}">{{ $reading->reading_date->format('d M Y') }}</td>
						<td class="pms-num">{{ number_format((float) $reading->value, 1) }}</td>
						<td class="pms-num">{{ $deltas[$reading->id] !== null ? number_format($deltas[$reading->id], 1) : '—' }}</td>
						<td>{{ optional($reading->enteredBy)->name ?? '—' }} <span class="pms-muted">&middot; {{ $reading->created_at->format('d M Y') }}</span></td>
						@if($canEdit)
						<td class="action">
							<button type="button" class="btn btn-danger pms-reading-delete" data-url="{{ route('pms.readings.delete', $reading) }}" data-date="{{ $reading->reading_date->format('d M Y') }}" title="Delete this reading"><i class="fas fa-trash-alt"></i></button>
						</td>
						@endif
					</tr>
					@empty
					<tr><td colspan="{{ $canEdit ? 5 : 4 }}" class="text-center pms-muted">No readings recorded yet.</td></tr>
					@endforelse
				</tbody>
			</table>
		</div>
	</div>
</div>
@endsection

@section('home-js')
<script>
$(function () {
	$('#pms-reading-form').on('submit', function (e) {
		e.preventDefault();
		var $btn = $(this).find('[type=submit]').prop('disabled', true);
		$.post('{{ route("pms.readings.store", $component->id) }}', $(this).serialize())
			.done(function () { window.location.reload(); })
			.fail(function (xhr) {
				$btn.prop('disabled', false);
				var errors = xhr.responseJSON && xhr.responseJSON.errors;
				toastr.error(errors ? Object.values(errors)[0][0]
					: ((xhr.responseJSON && xhr.responseJSON.message) || 'Something went wrong.'));
			});
	});

	$(document).on('click', '.pms-reading-delete', function () {
		var url = $(this).data('url');
		swal({
			title: 'Delete the ' + $(this).data('date') + ' reading?',
			type: 'warning',
			showCancelButton: true,
			confirmButtonColor: '#d33',
			confirmButtonText: 'Yes, delete it',
		}).then(function (result) {
			if (!(result === true || (result && result.value))) { return; }
			$.post(url, { _token: '{{ csrf_token() }}' })
				.done(function () { window.location.reload(); })
				.fail(function (xhr) {
					toastr.error((xhr.responseJSON && xhr.responseJSON.message) || 'Something went wrong.');
				});
		});
	});
});
</script>
@endsection
