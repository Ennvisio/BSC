@extends('layouts.pms')
@section('main-content')
@include('partials.pms-styles')
<div class="col-lg-6 col-xl-12">
	<div class="card">
		@include('partials.pms-report-head', ['title' => 'PMS Reports'])

			<form method="GET" action="{{ route('pms.reports.overview') }}" class="pms-report-filters">
				@include('partials.pms-report-vessel-filter')
				<div class="actions">
					@unless($locked)<button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Apply</button>@endunless
				</div>
			</form>

			<p class="pms-note">Open jobs per vessel by status. Click a number to open those jobs. % Overdue is overdue ÷ open: <span class="pms-band pms-band-good">&lt; 5%</span> <span class="pms-band pms-band-warn">5–15%</span> <span class="pms-band pms-band-bad">&gt; 15%</span></p>

			@php
				$jobLink = fn ($row, $filter, $n) => $n
					? '<a class="pms-num-link" href="'.e(route('pms.jobs.index', ['vessel_id' => $row['vessel_id'], 'filter' => $filter])).'">'.number_format($n).'</a>'
					: '<span class="zero">0</span>';
			@endphp
			<div class="table-responsive">
				<table class="table table-bordered table-sm pms-report-table">
					<thead>
						<tr>
							@foreach(\App\Services\PmsReports::OVERVIEW_COLUMNS as $col => $label)
							@include('partials.pms-report-th', ['col' => $col, 'label' => $label, 'class' => $col === 'vessel' || $col === 'last_completed' ? '' : 'pms-num', 'defaultDir' => $col === 'vessel' ? 'asc' : 'desc'])
							@endforeach
						</tr>
					</thead>
					<tbody>
						@forelse($rows as $row)
						<tr>
							<td><strong>{{ $row['vessel'] }}</strong></td>
							<td class="pms-num">{!! $jobLink($row, 'all', $row['open']) !!}</td>
							<td class="pms-num">{!! $jobLink($row, 'planned', $row['planned']) !!}</td>
							<td class="pms-num">{!! $jobLink($row, 'duesoon', $row['due_soon']) !!}</td>
							<td class="pms-num">{!! $jobLink($row, 'overdue', $row['overdue']) !!}</td>
							<td class="pms-num">{!! $jobLink($row, 'approval', $row['approval']) !!}</td>
							<td class="pms-num">
								@if($row['critical_overdue'])
								<a class="pms-num-link" href="{{ route('pms.reports.overdue', ['vessel_id' => $row['vessel_id'], 'critical' => 1]) }}">{{ number_format($row['critical_overdue']) }}</a>
								@else<span class="zero">0</span>@endif
							</td>
							<td class="pms-num">
								@if($row['pct_overdue'] !== null)
								<span class="pms-band pms-band-{{ \App\Services\PmsReports::overdueBand($row['pct_overdue']) }}">{{ number_format($row['pct_overdue'], 1) }}%</span>
								@else<span class="zero">—</span>@endif
							</td>
							<td>{{ $row['last_completed'] ? \Carbon\Carbon::parse($row['last_completed'])->format('d M Y') : '—' }}</td>
							<td class="pms-num">
								@if($row['stale_counters'])
								<a class="pms-num-link" href="{{ route('pms.counters.index', ['vessel_id' => $row['vessel_id']]) }}">{{ number_format($row['stale_counters']) }}</a>
								@else<span class="zero">0</span>@endif
							</td>
						</tr>
						@empty
						<tr><td colspan="10" class="text-center pms-muted">No vessels.</td></tr>
						@endforelse
					</tbody>
					@if($rows->total() > 1)
					<tfoot>
						<tr>
							<td>{{ $totals['vessel'] }}</td>
							@foreach(['open', 'planned', 'due_soon', 'overdue', 'approval', 'critical_overdue'] as $key)
							<td class="pms-num">{{ number_format($totals[$key]) }}</td>
							@endforeach
							<td class="pms-num">
								@if($totals['pct_overdue'] !== null)
								<span class="pms-band pms-band-{{ \App\Services\PmsReports::overdueBand($totals['pct_overdue']) }}">{{ number_format($totals['pct_overdue'], 1) }}%</span>
								@else — @endif
							</td>
							<td>{{ $totals['last_completed'] ? \Carbon\Carbon::parse($totals['last_completed'])->format('d M Y') : '—' }}</td>
							<td class="pms-num">{{ number_format($totals['stale_counters']) }}</td>
						</tr>
					</tfoot>
					@endif
				</table>
			</div>
			@include('partials.pms-report-pager', ['rows' => $rows, 'noun' => 'vessel'])
		</div>
	</div>
</div>
@endsection
