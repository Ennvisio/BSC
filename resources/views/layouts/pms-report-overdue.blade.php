@extends('layouts.pms')
@section('main-content')
@include('partials.pms-styles')
<div class="col-lg-6 col-xl-12">
	<div class="card">
		@include('partials.pms-report-head', ['title' => 'Overdue Jobs'])

			<form method="GET" action="{{ route('pms.reports.overdue') }}" class="pms-report-filters">
				@include('partials.pms-report-vessel-filter')
				<div class="form-group">
					<label for="rf-location">Location</label>
					<select name="location_id" id="rf-location" class="form-control">
						<option value="">All</option>
						@foreach($locations as $l)<option value="{{ $l->id }}" {{ $filters['location_id'] === $l->id ? 'selected' : '' }}>{{ $l->name }}</option>@endforeach
					</select>
				</div>
				<div class="form-group">
					<label for="rf-rank">Rank</label>
					<select name="rank_id" id="rf-rank" class="form-control">
						<option value="">All</option>
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
				<div class="form-group">
					<label for="rf-days">Days overdue</label>
					<select name="days" id="rf-days" class="form-control">
						<option value="">Any</option>
						<option value="30" {{ $filters['days'] === '30' ? 'selected' : '' }}>&gt; 30 days</option>
						<option value="90" {{ $filters['days'] === '90' ? 'selected' : '' }}>&gt; 90 days</option>
					</select>
				</div>
				<div class="form-group pms-check">
					<input type="checkbox" name="critical" value="1" id="rf-critical" {{ $filters['critical'] ? 'checked' : '' }}>
					<label for="rf-critical">Critical only</label>
				</div>
				<div class="actions">
					<a href="{{ route('pms.reports.overdue') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
					<button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Apply</button>
				</div>
			</form>

			<div class="pms-report-summary">
				<div class="pms-report-stat danger"><b>{{ number_format($summary['total']) }}</b><span>overdue jobs</span></div>
				@foreach($summary['bands'] as $label => $n)
				<div class="pms-report-stat"><b>{{ number_format($n) }}</b><span>{{ $label }}</span></div>
				@endforeach
			</div>
			@if($summary['by_rank']->isNotEmpty())
			<div class="pms-report-ranks mb-3">
				<span class="pms-muted">By rank:</span>
				@foreach($summary['by_rank'] as $r)<span class="chip">{{ $r['rank'] }} <b>{{ number_format($r['count']) }}</b></span>@endforeach
			</div>
			@endif

			<div class="table-responsive">
				<table class="table table-bordered table-sm pms-report-table">
					<thead>
						<tr>
							@foreach(\App\Services\PmsReports::OVERDUE_COLUMNS as $col => $label)
							@if(isset(\App\Services\PmsReports::OVERDUE_SORTS[$col]))
							@include('partials.pms-report-th', ['col' => $col, 'label' => $label, 'class' => in_array($col, ['due_rh', 'days']) ? 'pms-num' : '', 'defaultDir' => in_array($col, ['days', 'critical']) ? 'desc' : 'asc'])
							@else
							<th class="{{ $col === 'current_rh' ? 'pms-num' : '' }}">{{ $label }}</th>
							@endif
							@endforeach
						</tr>
					</thead>
					<tbody>
						@forelse($rows as $row)
						<tr>
							<td>{{ $row['vessel'] }}</td>
							<td>{{ $row['location'] ?? '—' }}</td>
							<td>{{ $row['path'] }}</td>
							<td><a href="{{ route('pms.jobs.index', ['vessel_id' => $row['vessel_id'], 'open' => $row['job_id']]) }}">{{ $row['job'] }}</a></td>
							<td>{{ $row['type'] ?? '—' }}</td>
							<td>{{ $row['rank'] ?? '—' }}</td>
							<td class="pms-overdue-date">{{ $row['due_date'] ? \Carbon\Carbon::parse($row['due_date'])->format('d M Y') : '—' }}</td>
							<td class="pms-num">{{ $row['due_rh'] !== null ? number_format($row['due_rh'], 1) : '—' }}</td>
							<td class="pms-num">{{ $row['current_rh'] !== null ? number_format($row['current_rh'], 1) : '—' }}</td>
							<td class="pms-num"><strong>{{ $row['days'] !== null ? number_format($row['days']) : '—' }}</strong></td>
							<td>@if($row['critical'])<span class="pms-flag pms-flag-critical">Critical</span>@endif</td>
							<td>{{ $row['reschedule_reason'] ?? '' }}</td>
						</tr>
						@empty
						<tr><td colspan="12" class="text-center pms-muted">No overdue jobs match these filters.</td></tr>
						@endforelse
					</tbody>
				</table>
			</div>
			@include('partials.pms-report-pager', ['rows' => $rows, 'noun' => 'job'])
		</div>
	</div>
</div>
@endsection
