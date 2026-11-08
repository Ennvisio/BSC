@extends('layouts.admin-master')
@section('main-content')
<div class="col-lg-6 col-xl-12">
	<div class="card">
		<div class="card-header pv-card-hader">
			<strong class="pptitle">Stock Movements &amp; Consumption{{ $lockedVessel ? ' — '.$lockedVessel->name : '' }}</strong>
			<div class="right-buttons" id="mv-actions" style="display:none;">
				<a href="#" id="mv-export-link" class="btn btn-outline-secondary btn-sm"><i class="fas fa-file-excel"></i> Export</a>
				<a href="#" id="mv-pdf-link" class="btn btn-outline-secondary btn-sm" target="_blank"><i class="fas fa-file"></i> PDF</a>
			</div>
		</div>
		<div class="card-body">
			<div class="row mb-3">
				@if(!$lockedVessel)
				<div class="col-md-3 mb-2">
					<label for="mv_vessel_id">Vessel</label>
					<select id="mv_vessel_id" class="form-control">
						<option value="">Whole fleet</option>
						@foreach($vessels as $vessel)
						<option value="{{ $vessel->id }}">{{ $vessel->name }}</option>
						@endforeach
					</select>
				</div>
				@endif
				<div class="col-md-3 mb-2">
					<label for="mv_category_id">Category</label>
					<select id="mv_category_id" class="form-control">
						<option value="">All categories</option>
						@foreach($categories as $category)
						<option value="{{ $category->id }}">{{ $category->name }}</option>
						@endforeach
					</select>
				</div>
				<div class="col-md-3 mb-2">
					<label for="mv_type">Type</label>
					<select id="mv_type" class="form-control">
						<option value="">All types</option>
						@foreach($types as $value => $label)
						<option value="{{ $value }}">{{ $label }}</option>
						@endforeach
					</select>
				</div>
				<div class="col-md-3 mb-2">
					<label for="mv_department">Department</label>
					<select id="mv_department" class="form-control">
						<option value="">Any</option>
						<option value="Deck">Deck</option>
						<option value="Engine">Engine</option>
					</select>
				</div>
			</div>
			<div class="row mb-3">
				<div class="col-md-3 mb-2">
					<label for="mv_from">From</label>
					<input type="date" id="mv_from" class="form-control" value="{{ now()->subMonths(12)->toDateString() }}">
				</div>
				<div class="col-md-3 mb-2">
					<label for="mv_to">To</label>
					<input type="date" id="mv_to" class="form-control" value="{{ now()->toDateString() }}">
				</div>
				<div class="col-md-3 mb-2 d-flex align-items-end">
					<button type="button" id="mv_apply" class="btn btn-primary btn-block">Apply Filters</button>
				</div>
			</div>

			<div class="row mb-4" id="mv-summary" style="display:none;">
				<div class="col-lg-6 mb-3">
					<div class="od-card-head" style="font-weight:600; margin-bottom:6px;">Received vs Consumed per Month</div>
					<canvas id="mv-month-chart" height="180"></canvas>
				</div>
				<div class="col-lg-3 mb-3">
					<div class="od-card-head" style="font-weight:600; margin-bottom:6px;">Consumption by Type</div>
					<canvas id="mv-type-chart" height="180"></canvas>
				</div>
				<div class="col-lg-3 mb-3">
					<div class="od-card-head" style="font-weight:600; margin-bottom:6px;">Top 10 Consumed Items</div>
					<ol id="mv-top-consumed" class="pl-3" style="font-size:13px;"></ol>
				</div>
			</div>

			<div class="table-responsive">
				<table id="movements-table" class="table table-bordered table-sm" style="width:100%;">
					<thead>
						<tr>
							<th>Date</th>
							@if(!$lockedVessel)<th>Vessel</th>@endif
							<th>Item</th>
							<th>Type</th>
							<th class="text-right">Qty</th>
							<th class="text-right">Balance After</th>
							<th>Department</th>
							<th>Source</th>
							<th>By</th>
						</tr>
					</thead>
					<tbody></tbody>
				</table>
			</div>
		</div>
	</div>

	<div class="card mt-3">
		<div class="card-header pv-card-hader">
			<strong class="pptitle">Requisition → Consumption Trace</strong>
		</div>
		<div class="card-body">
			<p class="text-muted">Look up a requisition to see how much of what it brought aboard has actually been consumed since.</p>
			<div class="row mb-3">
				<div class="col-md-6" style="position:relative;">
					<input type="text" id="trace_search" class="form-control" placeholder="Search requisition number…" autocomplete="off">
					<div id="trace_results" class="list-group" style="position:absolute; z-index:20; width:100%; max-height:260px; overflow:auto; display:none;"></div>
				</div>
			</div>
			<div id="trace_wrap" style="display:none;">
				<h6 id="trace_req_no"></h6>
				<div class="table-responsive">
					<table class="table table-bordered table-sm">
						<thead>
							<tr>
								<th>Item</th>
								<th class="text-right">Required</th>
								<th class="text-right">Received</th>
								<th class="text-right">Consumed</th>
								<th class="text-right">Remaining</th>
							</tr>
						</thead>
						<tbody id="trace_rows"></tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</div>
@endsection

@section('home-js')
<script type="text/javascript" src="{{ asset('assets/js/Chart.bundle.js') }}"></script>
<style>
#trace_results a{ cursor:pointer; }
</style>
<script>
$(function () {
	var movementsTable = null;
	var monthChart = null, typeChart = null;

	function escapeHtml(text) {
		return $('<div>').text(text === null || text === undefined ? '' : text).html();
	}

	function filters() {
		return {
			vessel_id: $('#mv_vessel_id').length ? $('#mv_vessel_id').val() : '',
			category_id: $('#mv_category_id').val(),
			type: $('#mv_type').val(),
			department: $('#mv_department').val(),
			from: $('#mv_from').val(),
			to: $('#mv_to').val(),
		};
	}

	function queryString() {
		var f = filters();
		var params = {};
		$.each(f, function (k, v) { if (v) { params[k] = v; } });
		return $.param(params);
	}

	function updateActionLinks() {
		var qs = queryString();
		$('#mv-export-link').attr('href', '{{ route('inventory.movements.export') }}?' + qs);
		$('#mv-pdf-link').attr('href', '{{ route('inventory.movements.pdf') }}?' + qs);
		$('#mv-actions').show();
	}

	function renderMonthChart(byMonth) {
		var ctx = document.getElementById('mv-month-chart').getContext('2d');
		if (monthChart) { monthChart.destroy(); }
		monthChart = new Chart(ctx, {
			type: 'bar',
			data: {
				labels: byMonth.map(function (r) { return r.month; }),
				datasets: [
					{ label: 'Received', data: byMonth.map(function (r) { return r.received; }), backgroundColor: 'rgba(14,124,116,.65)' },
					{ label: 'Consumed', data: byMonth.map(function (r) { return r.consumed; }), backgroundColor: 'rgba(214,69,69,.65)' },
				]
			},
			options: {
				scales: { yAxes: [{ ticks: { beginAtZero: true } }] },
				legend: { position: 'bottom' }
			}
		});
	}

	function renderTypeChart(byType) {
		var ctx = document.getElementById('mv-type-chart').getContext('2d');
		if (typeChart) { typeChart.destroy(); }
		var palette = ['#0E7C74', '#D64545', '#E0A526', '#5B6B6A', '#3E7CB1', '#9B59B6'];
		typeChart = new Chart(ctx, {
			type: 'doughnut',
			data: {
				labels: byType.map(function (r) { return r.label; }),
				datasets: [{ data: byType.map(function (r) { return r.qty; }), backgroundColor: palette }]
			},
			options: { legend: { position: 'bottom', labels: { boxWidth: 12, fontSize: 11 } } }
		});
	}

	function renderTopConsumed(top) {
		if (!top.length) {
			$('#mv-top-consumed').html('<li class="text-muted" style="list-style:none;">Nothing consumed in this range.</li>');
			return;
		}
		$('#mv-top-consumed').html(top.map(function (r) {
			return '<li>' + escapeHtml(r.name) + ' <span class="text-muted">(' + r.qty + ' ' + escapeHtml(r.unit) + ')</span></li>';
		}).join(''));
	}

	function loadSummary() {
		$.getJSON('{{ route('inventory.movements.summary') }}', filters(), function (res) {
			$('#mv-summary').show();
			renderMonthChart(res.by_month);
			renderTypeChart(res.by_type);
			renderTopConsumed(res.top_consumed);
		});
	}

	function initTable() {
		if (movementsTable) {
			movementsTable.destroy();
			$('#movements-table tbody').empty();
		}

		var columns = [
			{ data: 'moved_on' },
			@if(!$lockedVessel)
			{ data: 'vessel', render: function (v) { return v || '—'; } },
			@endif
			{
				data: 'name',
				render: function (v, type, row) {
					if (type !== 'display') { return v; }
					return '<a href="{{ url('/inventory/stock') }}/' + row.item_id + (row.vessel_id ? '?vessel_id=' + row.vessel_id : '') + '">' + escapeHtml(v) + '</a>';
				}
			},
			{
				data: 'type',
				render: function (v, type, row) {
					if (type !== 'display') { return v; }
					return row.consumption_type ? v + ' <span class="text-muted">(' + escapeHtml(row.consumption_type) + ')</span>' : v;
				}
			},
			{ data: 'qty', className: 'text-right', render: function (v, type) { return type === 'display' ? (v > 0 ? '+' + v : v) : v; } },
			{ data: 'balance_after', className: 'text-right' },
			{ data: 'department', render: function (v) { return v || '—'; } },
			{
				data: 'source',
				render: function (v, type, row) {
					if (type !== 'display') { return v; }
					if (row.order_id) { return '<a href="{{ url('/order/detail') }}/' + row.order_id + '">' + escapeHtml(v) + '</a>'; }
					return v ? escapeHtml(v) : '—';
				}
			},
			{ data: 'user', render: function (v) { return v || '—'; } },
		];

		movementsTable = $('#movements-table').DataTable({
			serverSide: true,
			processing: true,
			lengthMenu: [25, 50, 100, 250],
			ajax: {
				url: '{{ route('inventory.movements.data') }}',
				data: function (d) { $.extend(d, filters()); }
			},
			columns: columns,
			order: [[0, 'desc']]
		});
	}

	$('#mv_apply').on('click', function () {
		updateActionLinks();
		loadSummary();
		initTable();
	});

	// First load.
	updateActionLinks();
	loadSummary();
	initTable();

	// ---- Requisition trace ----
	var traceTimer = null;
	$('#trace_search').on('keyup', function () {
		var term = $(this).val();
		clearTimeout(traceTimer);
		traceTimer = setTimeout(function () {
			var params = { q: term };
			var vesselId = $('#mv_vessel_id').length ? $('#mv_vessel_id').val() : '';
			if (vesselId) { params.vessel_id = vesselId; }
			$.getJSON('{{ route('inventory.movements.requisition-search') }}', params, function (orders) {
				if (!orders.length) {
					$('#trace_results').html('<span class="list-group-item">No matching requisitions.</span>').show();
					return;
				}
				$('#trace_results').html(orders.map(function (o) {
					return '<a class="list-group-item list-group-item-action" data-id="' + o.id + '">' + escapeHtml(o.req_no) + '</a>';
				}).join('')).show();
			});
		}, 250);
	});

	$(document).on('click', '#trace_results a', function () {
		var orderId = $(this).data('id');
		$('#trace_search').val($(this).text());
		$('#trace_results').hide().empty();

		$.getJSON('{{ route('inventory.movements.requisition-trace') }}', { order_id: orderId }, function (res) {
			$('#trace_req_no').text(res.req_no);
			$('#trace_rows').html(res.lines.map(function (l) {
				return '<tr><td>' + escapeHtml(l.item_name) + '</td>'
					+ '<td class="text-right">' + l.required_qty + '</td>'
					+ '<td class="text-right">' + l.received_qty + '</td>'
					+ '<td class="text-right">' + l.consumed_qty + '</td>'
					+ '<td class="text-right">' + l.remaining_qty + '</td></tr>';
			}).join(''));
			$('#trace_wrap').show();
		});
	});

	$(document).on('click', function (e) {
		if (!$(e.target).closest('#trace_search, #trace_results').length) { $('#trace_results').hide(); }
	});
});
</script>
@endsection
