@extends('layouts.admin-master')
@section('main-content')
@include('partials.pms-styles')
<div class="col-lg-6 col-xl-12">
	<div class="card">
		<div class="card-header pv-card-hader">
			<strong class="pptitle">Import Preview &mdash; {{ $vessel->name }}</strong>
			<div class="right-buttons">
				<a href="{{ route('pms.components.import', ['vessel_id' => $vessel->id]) }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-upload"></i> Upload another file</a>
			</div>
		</div>
		<div class="card-body">
			@if($errors->any())
			<div class="alert alert-danger">{{ $errors->first() }}</div>
			@endif

			<p class="pms-note"><i class="fas fa-file-excel"></i> {{ $fileName }} &middot; {{ $rowCount }} {{ \Illuminate\Support\Str::plural('row', $rowCount) }} read from the Components sheet. Nothing has been saved yet.</p>

			<div class="pms-import-counts">
				<div class="pms-import-count create"><b>{{ $counts['create'] }}</b><span>to create</span>@if($readings)<small>{{ $readings }} with a running-hour reading</small>@endif</div>
				<div class="pms-import-count skip"><b>{{ $counts['skip'] }}</b><span>skipped (already there)</span>@if($counts['updated'])<small>{{ $counts['updated'] }} existing components will be updated</small>@endif</div>
				<div class="pms-import-count error"><b>{{ $counts['error'] }}</b><span>{{ \Illuminate\Support\Str::plural('error', $counts['error']) }}</span></div>
			</div>

			@if($counts['error'] > 0)
			<div class="alert alert-danger">Fix the rows with errors in the file and upload it again &mdash; nothing can be imported while any row has an error.</div>
			@elseif($counts['create'] === 0 && $counts['updated'] === 0)
			<div class="alert alert-info">Everything in this file is already on {{ $vessel->name }} &mdash; there is nothing to import.</div>
			@else
			<form method="POST" action="{{ route('pms.components.import.confirm', $token) }}" class="mb-3" id="imp-confirm-form">
				@csrf
				<button type="submit" class="btn btn-primary">
					<i class="fas fa-check"></i> Confirm
					@if($counts['create'])import of {{ $counts['create'] }} {{ \Illuminate\Support\Str::plural('component', $counts['create']) }}@endif
					@if($counts['create'] && $counts['updated'])and @endif
					@if($counts['updated'])update of {{ $counts['updated'] }} existing {{ \Illuminate\Support\Str::plural('component', $counts['updated']) }}@endif
				</button>
				<span class="pms-muted ml-2">All are saved together, or none are.</span>
			</form>
			@endif

			@if($issues->isNotEmpty())
			<h6 class="pms-section">Rows with errors or warnings ({{ $issues->count() }})</h6>
			<div class="pms-filters mb-2" role="group" aria-label="Filter rows">
				<button type="button" class="pms-filter active" data-show="all">All</button>
				<button type="button" class="pms-filter" data-show="error">Errors only</button>
			</div>
			<div class="table-responsive">
				<table class="table table-sm table-bordered pp-table" id="imp-issues">
					<thead><tr><th class="pms-num">Row</th><th>Component</th><th>Parent Path</th><th>Result</th><th>Details</th></tr></thead>
					<tbody>
						@foreach($issues as $r)
						<tr data-action="{{ $r['action'] }}">
							<td class="pms-num">{{ $r['row'] }}</td>
							<td>{{ $r['name'] ?: '—' }}<span class="pp-sub">{{ $r['location'] }}</span></td>
							<td>{{ $r['parent_path'] ?: '—' }}</td>
							<td>
								@if($r['action'] === 'error')<span class="pms-status pms-status-Overdue">Error</span>
								@elseif($r['action'] === 'skip')<span class="pms-status">Skipped</span>
								@else<span class="pms-status pms-status-Planned">Create</span>@endif
							</td>
							<td>
								@foreach($r['errors'] as $msg)<div class="text-danger">{{ $msg }}</div>@endforeach
								@foreach($r['warnings'] as $msg)<div class="pms-muted">{{ $msg }}</div>@endforeach
							</td>
						</tr>
						@endforeach
					</tbody>
				</table>
			</div>
			@endif
		</div>
	</div>
</div>
@endsection

@section('home-js')
<script>
$(function () {
	$('.pms-filter[data-show]').on('click', function () {
		$('.pms-filter[data-show]').removeClass('active');
		$(this).addClass('active');
		var errorsOnly = $(this).data('show') === 'error';
		$('#imp-issues tbody tr').each(function () { $(this).toggle(!errorsOnly || $(this).data('action') === 'error'); });
	});
	$('#imp-confirm-form').on('submit', function () { $(this).find('button').prop('disabled', true).text('Importing…'); });
});
</script>
@endsection
