{{-- Card header + report tabs, shared by the three reports. Takes $title, $vessel, $locked. --}}
<div class="card-header pv-card-hader">
	<strong class="pptitle">{{ $title }} &mdash; {{ $vessel->name ?? 'Fleet' }}</strong>
	<div class="right-buttons">
		<a href="{{ request()->fullUrlWithQuery(['export' => 1, 'page' => null]) }}" class="btn btn-sm btn-success"><i class="fas fa-file-excel"></i> Export Excel</a>
	</div>
</div>
<div class="card-body">
	<div class="pms-report-tabs">
		@php $keep = $locked || !$vessel ? [] : ['vessel_id' => $vessel->id]; @endphp
		<a href="{{ route('pms.reports.overview', $keep) }}" class="pms-tab {{ Route::is('pms.reports.overview') ? 'active' : '' }}"><i class="fas fa-chart-bar"></i> Vessel Overview</a>
		<a href="{{ route('pms.reports.overdue', $keep) }}" class="pms-tab {{ Route::is('pms.reports.overdue') ? 'active' : '' }}"><i class="fas fa-exclamation-triangle"></i> Overdue Jobs</a>
		<a href="{{ route('pms.reports.history', $keep) }}" class="pms-tab {{ Route::is('pms.reports.history') ? 'active' : '' }}"><i class="fas fa-history"></i> Job History</a>
	</div>
