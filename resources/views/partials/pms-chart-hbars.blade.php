{{-- A plain HTML/CSS stacked horizontal bar chart - one row per vessel (or
	 the one vessel). Takes $overdueByAge = ['rows' => [{vessel,segments,total}],
	 'buckets' => [{label,color}]] (see PmsDashboardController::overdueByAge()). --}}
<div class="pms-chart" data-chart>
	<div class="pms-chart-readout" data-chart-readout>Hover or focus a segment for the exact figure.</div>
	<div class="pms-chart-hrows">
		@forelse($overdueByAge['rows'] as $row)
		<div class="pms-chart-hrow">
			<span class="pms-chart-hrow-label" title="{{ $row['vessel'] }}">{{ $row['vessel'] }}</span>
			<div class="pms-chart-hbar">
				@foreach($row['segments'] as $seg)
				<button type="button" class="pms-chart-hseg" style="width:{{ $seg['pct'] }}%; background:{{ $seg['color'] }};" data-chart-value="{{ $seg['readout'] }}" aria-label="{{ $seg['readout'] }}"></button>
				@endforeach
			</div>
			<span class="pms-chart-hrow-total">{{ $row['total'] }}</span>
		</div>
		@empty
		<p class="pms-muted">No vessels in scope.</p>
		@endforelse
	</div>
	<div class="pms-chart-legend">
		@foreach($overdueByAge['buckets'] as $b)
		<span class="pms-chart-legend-item"><span class="pms-chart-legend-swatch" style="background:{{ $b['color'] }};"></span>{{ $b['label'] }}</span>
		@endforeach
	</div>
</div>
