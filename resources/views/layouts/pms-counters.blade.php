@extends('layouts.admin-master')
@section('main-content')
@include('partials.pms-styles')
<div class="col-lg-6 col-xl-12">
	<div class="card">
		<div class="card-header pv-card-hader">
			<strong class="pptitle">Counters &mdash; {{ $vessel->name }}</strong>
			@if($canEdit && $counters->isNotEmpty())
			<div class="right-buttons">
				<button type="button" class="btn btn-sm btn-primary pc-save"><i class="fas fa-save"></i> Save all</button>
			</div>
			@endif
		</div>
		<div class="card-body">
			<div class="pms-toolbar">
				@if($vessels->isNotEmpty())
				<form method="GET" action="{{ route('pms.counters.index') }}">
					<select name="vessel_id" class="form-control" id="pms-vessel-picker" aria-label="Vessel">
						@foreach($vessels as $v)
						<option value="{{ $v->id }}" {{ $v->id === $vessel->id ? 'selected' : '' }}>{{ $v->name }}</option>
						@endforeach
					</select>
				</form>
				@endif
				<input type="search" class="form-control pms-search" id="pc-search" placeholder="Search components…" aria-label="Search counters">
				<span class="spacer"></span>
				<span class="pms-muted" style="font-size:13px;">{{ $counters->count() }} {{ \Illuminate\Support\Str::plural('counter', $counters->count()) }}</span>
			</div>

			@if($canEdit)
			<p class="pms-note">Enter the meter total for each counter you have read, then <strong>Save all</strong>. Only rows with a New RH are saved; a row that breaks a rule (lower than before, over 24 h a day, a second reading on one date) is shown in red and the others are still saved. RH jobs on the saved components are recalculated straight away.</p>
			@else
			<p class="pms-note"><i class="fas fa-eye"></i> View only &mdash; readings are entered on board.</p>
			@endif

			@if($counters->isEmpty())
			<p class="pms-location-empty">No component on {{ $vessel->name }} has a running-hour counter. Tick "Has running-hour counter" on a component to list it here.</p>
			@else
			<div class="table-responsive">
				<table class="table table-sm table-bordered pp-table pc-table" id="pc-table">
					<thead>
						<tr>
							<th>Component</th>
							<th>Last Reading</th>
							<th class="pms-num">Avg RH / day</th>
							@if($canEdit)
							<th style="width:160px;">New Date</th>
							<th style="width:150px;">New RH</th>
							<th>Result</th>
							@endif
						</tr>
					</thead>
					<tbody>
						@php $currentLocation = null; @endphp
						@foreach($counters as $c)
						@if($c['location'] !== $currentLocation)
						@php $currentLocation = $c['location']; @endphp
						<tr class="pc-location"><td colspan="{{ $canEdit ? 6 : 3 }}">{{ $currentLocation }}</td></tr>
						@endif
						<tr data-id="{{ $c['id'] }}" data-last="{{ $c['last_value'] }}" data-last-date="{{ $c['last_date'] }}"
							data-search="{{ mb_strtolower($c['path'].' '.$c['name']) }}" class="{{ $c['inactive'] ? 'pc-inactive' : '' }}">
							<td>
								<a href="{{ route('pms.readings.index', $c['id']) }}" class="pc-name">{{ $c['name'] }}</a>
								@if($c['inactive'])<span class="pms-flag pms-flag-inactive">Inactive</span>@endif
								@if($c['path'])<span class="pp-sub">{{ $c['path'] }}</span>@endif
							</td>
							<td class="pc-last">
								@if($c['last_value'] !== null)
								<strong>{{ number_format($c['last_value'], 1) }} h</strong><span class="pp-sub">{{ \Carbon\Carbon::parse($c['last_date'])->format('d M Y') }}</span>
								@else
								<span class="pms-muted">No reading yet</span>
								@endif
							</td>
							<td class="pms-num pc-avg">{{ $c['per_day'] !== null ? number_format($c['per_day'], 1) : '—' }}</td>
							@if($canEdit)
							<td><input type="date" class="form-control form-control-sm pc-date" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" aria-label="New reading date for {{ $c['name'] }}"></td>
							<td>
								<input type="number" class="form-control form-control-sm pc-value" min="0" step="0.1" aria-label="New RH for {{ $c['name'] }}">
								<span class="pc-hint"></span>
							</td>
							<td class="pc-result"></td>
							@endif
						</tr>
						@endforeach
					</tbody>
				</table>
			</div>
			<p class="pms-location-empty pms-hidden" id="pc-no-match">No counter matches your search.</p>
			@if($canEdit)
			<div class="text-right"><button type="button" class="btn btn-primary pc-save"><i class="fas fa-save"></i> Save all</button></div>
			@endif
			@endif
		</div>
	</div>
</div>
@endsection

@section('home-js')
<style>
.pc-table td{ vertical-align:middle; }
.pc-location td{ background:var(--srd-surface); font-size:11.5px; font-weight:700; letter-spacing:.07em; text-transform:uppercase; color:var(--srd-muted); }
.pc-name{ font-weight:600; color:var(--srd-ink); }
.pc-inactive .pc-name{ color:var(--srd-muted); }
.pc-hint{ display:block; font-size:11.5px; color:var(--srd-muted); min-height:1em; font-variant-numeric:tabular-nums; }
.pc-hint.bad{ color:var(--srd-danger); }
.pc-result{ font-size:12.5px; }
tr.pc-error td{ background:var(--srd-danger-tint); }
tr.pc-saved td{ background:var(--srd-success-tint); }
</style>
<script>
$(function () {
	var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
	function fmtDate(iso) { var p = iso.split('-'); return p[2] + ' ' + months[parseInt(p[1], 10) - 1] + ' ' + p[0]; }
	function fmtH(v) { return Number(v).toLocaleString(undefined, { minimumFractionDigits: 1, maximumFractionDigits: 1 }); }
	function esc(t) { return $('<div>').text(t == null ? '' : String(t)).html(); }

	$('#pms-vessel-picker').on('change', function () { this.form.submit(); });

	$('#pc-search').on('input', function () {
		var q = $.trim($(this).val()).toLowerCase(), any = false;
		$('#pc-table tbody tr[data-id]').each(function () {
			var hit = !q || ($(this).attr('data-search') || '').indexOf(q) !== -1;
			$(this).toggle(hit);
			any = any || hit;
		});
		// A location heading shows while any of its rows does.
		$('#pc-table tbody tr.pc-location').each(function () {
			$(this).toggle($(this).nextUntil('.pc-location').filter(':visible').length > 0);
		});
		$('#pc-no-match').toggleClass('pms-hidden', any);
	});

	// As a value is typed: how much it adds, and the rate since the last reading.
	function hint($row) {
		var $h = $row.find('.pc-hint').removeClass('bad').text('');
		var v = parseFloat($row.find('.pc-value').val()), last = parseFloat($row.attr('data-last')), lastDate = $row.attr('data-last-date');
		if (isNaN(v) || isNaN(last) || !lastDate) { return; }
		var days = Math.round((new Date($row.find('.pc-date').val()) - new Date(lastDate)) / 86400000);
		var diff = v - last;
		if (diff < 0) { $h.addClass('bad').text('Lower than the last reading'); return; }
		$h.text('+' + fmtH(diff) + ' h' + (days > 0 ? ' · ' + fmtH(diff / days) + ' h/day' : ''));
		if (days > 0 && diff / days > 24) { $h.addClass('bad'); }
	}
	$('#pc-table').on('input change', '.pc-value, .pc-date', function () {
		var $row = $(this).closest('tr');
		$row.removeClass('pc-error pc-saved').find('.pc-result').empty();
		hint($row);
	});

	$('.pc-save').on('click', function () {
		var payload = { _token: '{{ csrf_token() }}', vessel_id: @json($vessel->id), readings: {} }, n = 0;
		$('#pc-table tbody tr[data-id]').each(function () {
			var v = $.trim($(this).find('.pc-value').val());
			if (v === '') { return; }
			payload.readings[$(this).attr('data-id')] = { reading_date: $(this).find('.pc-date').val(), value: v };
			n++;
		});
		if (!n) { toastr.info('Enter a New RH on at least one row.'); return; }

		var $btns = $('.pc-save').prop('disabled', true);
		$.post('{{ route('pms.counters.store') }}', payload)
			.done(function (res) { apply(res); toastr.success(res.message); })
			.fail(function (xhr) {
				var res = xhr.responseJSON || {};
				if (res.saved) { apply(res); toastr.error(res.message); return; }
				toastr.error((res.errors && Object.values(res.errors)[0][0]) || res.message || 'Something went wrong.');
			})
			.always(function () { $btns.prop('disabled', false); });
	});

	function apply(res) {
		$.each(res.saved || [], function (_, id) {
			var $row = $('#pc-table tr[data-id="' + id + '"]'), r = res.rows[id];
			$row.removeClass('pc-error').addClass('pc-saved').attr('data-last', r.last_value).attr('data-last-date', r.last_date);
			$row.find('.pc-last').html('<strong>' + fmtH(r.last_value) + ' h</strong><span class="pp-sub">' + fmtDate(r.last_date) + '</span>');
			$row.find('.pc-avg').text(r.per_day === null ? '—' : fmtH(r.per_day));
			$row.find('.pc-value').val('');
			$row.find('.pc-hint').text('');
			$row.find('.pc-result').html('<span class="text-success"><i class="fas fa-check"></i> Saved</span>');
		});
		$.each(res.errors || {}, function (id, message) {
			var $row = $('#pc-table tr[data-id="' + id + '"]');
			$row.removeClass('pc-saved').addClass('pc-error').find('.pc-result').html('<span class="text-danger">' + esc(message) + '</span>');
		});
		var $firstError = $('#pc-table tr.pc-error').first();
		if ($firstError.length) { $firstError[0].scrollIntoView({ block: 'center' }); }
	}
});
</script>
@endsection
