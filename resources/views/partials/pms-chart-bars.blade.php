{{-- A plain HTML/CSS vertical bar chart - no chart library. Takes $chart =
	 ['bars' => [{value,short,label,current,pct,readout}], 'gridlines' => [{pct,value}]]
	 (see PmsDashboardController::scaleBars()) and an optional $empty message. --}}
<div class="pms-chart" data-chart>
	<div class="pms-chart-readout" data-chart-readout>Hover or focus a bar for the exact figure.</div>
	<div class="pms-chart-plot">
		<div class="pms-chart-grid">
			@foreach($chart['gridlines'] as $line)
			<div class="pms-chart-gridline" style="bottom:{{ $line['pct'] }}%"><span>{{ number_format($line['value']) }}</span></div>
			@endforeach
		</div>
		<div class="pms-chart-bars">
			@foreach($chart['bars'] as $bar)
			<button type="button" class="pms-chart-bar {{ $bar['current'] ? 'is-current' : '' }}" style="height:{{ $bar['pct'] }}%" data-chart-value="{{ $bar['readout'] }}" aria-label="{{ $bar['readout'] }}"></button>
			@endforeach
		</div>
	</div>
	<div class="pms-chart-labels">
		@foreach($chart['bars'] as $bar)
		<span class="pms-chart-label">{{ $bar['short'] }}</span>
		@endforeach
	</div>
</div>
