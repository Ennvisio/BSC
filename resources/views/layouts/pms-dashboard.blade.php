@extends('layouts.pms')
@section('main-content')
@include('partials.pms-styles')
@php
	// A plain array, not a function: this partial can render more than once
	// in one PHP process (the test suite, php artisan serve), and a bare
	// `function` declaration here would fatal with "cannot redeclare" on
	// the second render.
	$bandColors = ['good' => 'var(--od-accent)', 'warn' => 'var(--od-warn)', 'bad' => 'var(--od-danger)'];
	$conditionColors = ['Good' => 'var(--od-accent)', 'Fair' => 'var(--od-warn)', 'Bad' => 'var(--od-danger)', 'Not Classified' => 'var(--od-faint)'];
@endphp
<div class="col-lg-6 col-xl-12">
	<div class="pms-dash-head">
		<div>
			<h1 class="pms-dash-title">PMS Dashboard</h1>
			<p class="pms-dash-sub">{{ $fleet ? 'Fleet · '.$vessels->count().' '.\Illuminate\Support\Str::plural('vessel', $vessels->count()) : $vessel->name }} &middot; status as of {{ $generatedAt->format('d M Y, H:i') }}</p>
		</div>
	</div>

	<section class="pms-dash-tiles" aria-label="Key figures">
		@foreach($kpis as $k)
		<a href="{{ $k['href'] }}" class="pms-dash-tile">
			<span class="pms-dash-tile-label">{{ $k['label'] }}</span>
			<span class="pms-dash-tile-value pms-dash-tile-{{ $k['color'] }}">{{ number_format($k['value']) }}</span>
			<span class="pms-dash-tile-sub">{{ $k['sub'] }}</span>
		</a>
		@endforeach
	</section>

	<div class="pms-dash-row">
		@if($fleet)
		<section class="card pms-dash-card pms-dash-card-main">
			<div class="card-header pv-card-hader">
				<strong class="pptitle">Vessel job overview</strong>
				<span class="pms-muted" style="font-size:12px;">Click a vessel to open its jobs</span>
			</div>
			<div class="card-body" style="padding:0;">
				<div class="table-responsive">
					<table class="table pms-dash-table">
						<thead>
							<tr><th>Vessel</th><th class="pms-num">Open</th><th class="pms-num">Overdue</th><th class="pms-num">Due soon</th><th class="pms-num">Approval</th><th style="width:220px;">% overdue</th></tr>
						</thead>
						<tbody>
							@foreach($overview['rows'] as $row)
							@php $band = \App\Services\PmsReports::overdueBand($row['pct_overdue']); @endphp
							<tr>
								<td><a href="{{ route('pms.jobs.index', ['vessel_id' => $row['vessel_id']]) }}" style="font-weight:600;">{{ $row['vessel'] }}</a></td>
								<td class="pms-num">{{ $row['open'] }}</td>
								<td class="pms-num" style="color:var(--od-danger); font-weight:600;">{{ $row['overdue'] }}</td>
								<td class="pms-num" style="color:var(--od-warn-dark);">{{ $row['due_soon'] }}</td>
								<td class="pms-num">{{ $row['approval'] }}</td>
								<td>
									<div class="pms-dash-bar">
										<div class="pms-dash-bar-track"><div class="pms-dash-bar-fill" style="width:{{ $row['pct_overdue'] ?? 0 }}%; background:{{ $bandColors[$band] ?? 'var(--od-faint)' }};"></div></div>
										<span style="width:48px; text-align:right; font-weight:600; color:{{ $bandColors[$band] ?? 'var(--od-faint)' }};">{{ $row['pct_overdue'] !== null ? $row['pct_overdue'].'%' : '—' }}</span>
									</div>
								</td>
							</tr>
							@endforeach
							<tr class="pms-dash-total">
								<td>Fleet total</td>
								<td class="pms-num">{{ $overview['totals']['open'] }}</td>
								<td class="pms-num" style="color:var(--od-danger);">{{ $overview['totals']['overdue'] }}</td>
								<td class="pms-num" style="color:var(--od-warn-dark);">{{ $overview['totals']['due_soon'] }}</td>
								<td class="pms-num">{{ $overview['totals']['approval'] }}</td>
								@php $totalBand = \App\Services\PmsReports::overdueBand($overview['totals']['pct_overdue']); @endphp
								<td style="color:{{ $bandColors[$totalBand] ?? 'var(--od-faint)' }};">{{ $overview['totals']['pct_overdue'] !== null ? $overview['totals']['pct_overdue'].'%' : '—' }}</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
		</section>
		@endif

		<section class="card pms-dash-card {{ $fleet ? 'pms-dash-card-side' : 'pms-dash-card-main' }}">
			<div class="card-header pv-card-hader"><strong class="pptitle">Needs attention</strong></div>
			<ul class="pms-dash-attention">
				@foreach($attention as $a)
				<li><a href="{{ $a['href'] }}">
					<span class="pms-dash-pill pms-dash-pill-{{ $a['bg'] }}">{{ $a['value'] }}</span>
					<span class="pms-dash-attn-text"><strong>{{ $a['label'] }}</strong><span>{{ $a['sub'] }}</span></span>
				</a></li>
				@endforeach
			</ul>
		</section>
	</div>

	<div class="pms-dash-row">
		<section class="card pms-dash-card pms-dash-card-main">
			<div class="card-header pv-card-hader">
				<strong class="pptitle">Due next</strong>
				<a href="{{ route('pms.jobs.index', $vessel ? ['vessel_id' => $vessel->id, 'filter' => 'due14'] : ['filter' => 'due14']) }}" style="font-size:13px; font-weight:600;">All jobs due in 14 days</a>
			</div>
			<div class="card-body" style="padding:0;">
				<div class="table-responsive">
					<table class="table pms-dash-table">
						<thead><tr><th>Vessel</th><th>Job</th><th>Component</th><th>Due</th></tr></thead>
						<tbody>
							@forelse($dueNext as $d)
							<tr>
								<td style="font-weight:600;">{{ $d['vessel'] }}</td>
								<td>{{ $d['job'] }}</td>
								<td class="pms-muted">{{ $d['component'] }}</td>
								<td><span class="pms-status pms-status-DueSoon">{{ \Carbon\Carbon::parse($d['due_date'])->format('d M Y') }}</span></td>
							</tr>
							@empty
							<tr><td colspan="4" class="text-center pms-muted">Nothing due.</td></tr>
							@endforelse
						</tbody>
					</table>
				</div>
			</div>
		</section>

		<section class="card pms-dash-card pms-dash-card-side">
			<div class="card-header pv-card-hader"><strong class="pptitle">Component condition</strong></div>
			<div class="card-body pms-dash-conditions">
				@foreach($conditions as $c)
				<div class="pms-dash-cond-row">
					<div class="pms-dash-cond-label"><span>{{ $c['label'] }}</span><span style="font-weight:600;">{{ number_format($c['value']) }}</span></div>
					<div class="pms-dash-bar-track"><div class="pms-dash-bar-fill" style="width:{{ $c['pct'] }}%; background:{{ $conditionColors[$c['label']] ?? 'var(--od-faint)' }};"></div></div>
				</div>
				@endforeach
				<p class="pms-muted" style="font-size:12px; margin:8px 0 0;">{{ number_format(collect($conditions)->sum('value')) }} components{{ $fleet ? ' across '.$vessels->count().' '.\Illuminate\Support\Str::plural('vessel', $vessels->count()) : '' }}</p>
			</div>
		</section>
	</div>

	<div class="pms-dash-row">
		<section class="card pms-dash-card pms-dash-card-side">
			<div class="card-header pv-card-hader"><strong class="pptitle">Jobs completed per month</strong></div>
			<div class="card-body">
				@include('partials.pms-chart-bars', ['chart' => $completedPerMonth])
			</div>
		</section>

		<section class="card pms-dash-card pms-dash-card-side">
			<div class="card-header pv-card-hader"><strong class="pptitle">Workload: jobs due per week</strong></div>
			<div class="card-body">
				@include('partials.pms-chart-bars', ['chart' => $dueByWeek])
			</div>
		</section>
	</div>

	<div class="pms-dash-row">
		<section class="card pms-dash-card" style="width:100%;">
			<div class="card-header pv-card-hader"><strong class="pptitle">Overdue jobs by how late</strong></div>
			<div class="card-body">
				@include('partials.pms-chart-hbars', ['overdueByAge' => $overdueByAge])
			</div>
		</section>
	</div>
</div>
@endsection

@section('home-js')
<script>
document.addEventListener('DOMContentLoaded', function () {
	// Hover/focus any bar or segment: show its exact figure in that chart's
	// own readout line, above the chart. Reverts on mouseout/blur.
	document.querySelectorAll('[data-chart]').forEach(function (chart) {
		var readout = chart.querySelector('[data-chart-readout]');
		if (!readout) { return; }
		var defaultText = readout.textContent;
		var show = function (e) {
			var bar = e.target.closest('[data-chart-value]');
			if (bar) { readout.textContent = bar.getAttribute('data-chart-value'); }
		};
		var reset = function (e) {
			var bar = e.target.closest('[data-chart-value]');
			if (bar) { readout.textContent = defaultText; }
		};
		chart.addEventListener('mouseover', show);
		chart.addEventListener('mouseout', reset);
		chart.addEventListener('focusin', show);
		chart.addEventListener('focusout', reset);
	});
});
</script>
@endsection
