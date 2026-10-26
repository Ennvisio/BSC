@extends('layouts.pms')
@section('main-content')
@include('partials.pms-styles')
<div class="col-lg-6 col-xl-12">
	<div class="card">
		@include('partials.pms-report-head', ['title' => 'Job History'])

			<form method="GET" action="{{ route('pms.reports.history') }}" class="pms-report-filters">
				@include('partials.pms-report-vessel-filter')
				<div class="form-group">
					<label for="rf-from">Completed from</label>
					<input type="date" name="from" id="rf-from" class="form-control" value="{{ $filters['from'] }}">
				</div>
				<div class="form-group">
					<label for="rf-to">to</label>
					<input type="date" name="to" id="rf-to" class="form-control" value="{{ $filters['to'] }}">
				</div>
				<div class="form-group">
					<label for="rf-component">Component</label>
					<input type="search" name="component" id="rf-component" class="form-control" value="{{ $filters['component'] }}" placeholder="Name contains…">
				</div>
				<div class="form-group">
					<label for="rf-rank">Done by</label>
					<select name="rank_id" id="rf-rank" class="form-control">
						<option value="">Any rank</option>
						@foreach($ranks as $r)<option value="{{ $r->id }}" {{ $filters['rank_id'] === $r->id ? 'selected' : '' }}>{{ $r->code }} — {{ $r->name }}</option>@endforeach
					</select>
				</div>
				<div class="form-group">
					<label for="rf-type">Job type</label>
					<select name="job_type_id" id="rf-type" class="form-control">
						<option value="">All</option>
						@foreach($jobTypes as $t)<option value="{{ $t->id }}" {{ $filters['job_type_id'] === $t->id ? 'selected' : '' }}>{{ $t->name }}</option>@endforeach
					</select>
				</div>
				<div class="form-group pms-check">
					<input type="checkbox" name="late" value="1" id="rf-late" {{ $filters['late'] ? 'checked' : '' }}>
					<label for="rf-late">Only completed late</label>
				</div>
				<div class="actions">
					<a href="{{ route('pms.reports.history') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
					<button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Apply</button>
				</div>
			</form>

			<div class="table-responsive">
				<table class="table table-bordered table-sm pms-report-table">
					<thead>
						<tr>
							@foreach(\App\Services\PmsReports::HISTORY_COLUMNS as $col => $label)
							@if(isset(\App\Services\PmsReports::HISTORY_SORTS[$col]))
							@include('partials.pms-report-th', ['col' => $col, 'label' => $label, 'class' => $col === 'rh_at_completion' ? 'pms-num' : '', 'defaultDir' => $col === 'completed_at' ? 'desc' : 'asc'])
							@else
							<th class="{{ $col === 'days_late' ? 'pms-num' : '' }}">{{ $label }}</th>
							@endif
							@endforeach
						</tr>
					</thead>
					<tbody>
						@forelse($rows as $row)
						<tr>
							<td>{{ \Carbon\Carbon::parse($row['completed_at'])->format('d M Y') }}</td>
							<td>{{ $row['vessel'] }}</td>
							<td>{{ $row['path'] }}</td>
							<td>{{ $row['job'] }}</td>
							<td>{{ $row['type'] ?? '—' }}</td>
							<td>{{ $row['done_by'] ?? '—' }}</td>
							<td class="pms-num">{{ $row['rh_at_completion'] !== null ? number_format($row['rh_at_completion'], 1) : '—' }}</td>
							<td>{{ $row['due_date'] ? \Carbon\Carbon::parse($row['due_date'])->format('d M Y') : '—' }}</td>
							<td class="pms-num">@if($row['days_late'])<span class="pms-overdue-date">{{ number_format($row['days_late']) }}</span>@endif</td>
							<td>{{ $row['condition'] ?? '—' }}</td>
							<td>{{ $row['overdue_reason'] ?? '' }}</td>
							<td>{{ $row['remark'] ?? '' }}</td>
							<td>{{ $row['approved'] ?? '' }}</td>
						</tr>
						@empty
						<tr><td colspan="13" class="text-center pms-muted">No completed jobs match these filters.</td></tr>
						@endforelse
					</tbody>
				</table>
			</div>
			@include('partials.pms-report-pager', ['rows' => $rows, 'noun' => 'job'])
		</div>
	</div>
</div>
@endsection
