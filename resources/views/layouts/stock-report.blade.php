@extends('layouts.admin-master')
@section('main-content')
<div class="col-lg-6 col-xl-12">
	<div class="card">
		<div class="card-header pv-card-hader">
			<strong class="pptitle">Stock Report{{ $lockedVessel ? ' — '.$lockedVessel->name : '' }}</strong>
		</div>
		<div class="card-body">
			<div class="row mb-3">
				<div class="col-md-4">
					<label for="report_vessel_id">Vessel</label>
					@if($lockedVessel)
					{{-- Master/Chief Engineer only ever see their own vessel - the
						 same rule StockController itself enforces for maintaining
						 stock, applied here to reading it. --}}
					<input type="text" class="form-control" value="{{ $lockedVessel->name }}" disabled>
					<input type="hidden" id="report_vessel_id" value="{{ $lockedVessel->id }}">
					@else
					<select id="report_vessel_id" class="form-control">
						<option value="">Choose vessel…</option>
						@foreach($vessels as $vessel)
						<option value="{{ $vessel->id }}">{{ $vessel->name }}</option>
						@endforeach
					</select>
					@endif
				</div>
				<div class="col-md-4">
					<label for="report_category_id">Category</label>
					<select id="report_category_id" class="form-control">
						<option value="">Choose category…</option>
						@foreach($categories as $category)
						<option value="{{ $category->id }}">{{ $category->name }}</option>
						@endforeach
					</select>
				</div>
				<div class="col-md-4 d-flex align-items-end">
					<div class="custom-control custom-checkbox">
						<input type="checkbox" class="custom-control-input" id="report_low_only">
						<label class="custom-control-label" for="report_low_only">Low stock only (at or below reorder level)</label>
					</div>
				</div>
			</div>

			<div class="row mb-3" id="report-summary" style="display:none;">
				<div class="col-md-4"><div class="alert alert-secondary mb-0 text-center"><b id="summary_total">0</b> items in this category</div></div>
				<div class="col-md-4"><div class="alert alert-secondary mb-0 text-center"><b id="summary_zero">0</b> at zero stock</div></div>
				<div class="col-md-4"><div class="alert alert-secondary mb-0 text-center"><b id="summary_low">0</b> at or below reorder level</div></div>
			</div>

			<p class="text-muted" id="report-placeholder">
				Choose a {{ $lockedVessel ? '' : 'vessel and ' }}category above to see stock figures.
			</p>

			<div class="table-responsive" id="report-table-wrap" style="display:none;">
				<table id="stock-report-table" class="table table-bordered table-sm" style="width:100%;">
					<thead>
						<tr>
							<th>Item Name</th>
							<th>Article Number</th>
							<th>IMPA Code</th>
							<th>Unit</th>
							<th>Opening Stock</th>
							<th>In Stock</th>
							<th>Reorder Level</th>
							<th>Last Supply Qty</th>
							<th>Last Supply Date</th>
						</tr>
					</thead>
					<tbody></tbody>
				</table>
			</div>
		</div>
	</div>
</div>
@endsection

@section('home-js')
<script>
$(function () {
	var reportTable = null;

	// Both the summary tiles and the table read the same two selections, so
	// this is the one place that decides what "nothing chosen yet" means.
	function selection() {
		return {
			vessel_id: $('#report_vessel_id').val(),
			category_id: $('#report_category_id').val(),
		};
	}

	function ready() {
		var sel = selection();
		return !!(sel.vessel_id && sel.category_id);
	}

	function loadSummary() {
		if (!ready()) {
			$('#report-summary').hide();
			return;
		}

		$.getJSON('{{ route('stock.report.summary') }}', selection(), function (res) {
			$('#summary_total').text(res.total_items.toLocaleString());
			$('#summary_zero').text(res.zero_stock.toLocaleString());
			$('#summary_low').text(res.low_stock.toLocaleString());
			$('#report-summary').show();
		});
	}

	// A fresh instance per vessel/category pair, not just an ajax.reload() -
	// changing either resets which rows exist at all (a different category
	// can be a different order of magnitude, tens vs tens of thousands), so
	// stale pagination/sort state from the last one isn't worth carrying over.
	function initTable() {
		if (reportTable) {
			reportTable.destroy();
			$('#stock-report-table tbody').empty();
		}

		var textRender = $.fn.dataTable.render.text();

		reportTable = $('#stock-report-table').DataTable({
			serverSide: true,
			processing: true,
			lengthMenu: [25, 50, 100, 250],
			ajax: {
				url: '{{ route('stock.report.data') }}',
				data: function (d) {
					var sel = selection();
					d.vessel_id = sel.vessel_id;
					d.category_id = sel.category_id;
					d.low_only = $('#report_low_only').is(':checked') ? 1 : 0;
				}
			},
			columns: [
				{ data: 'name', render: textRender },
				{ data: 'article_number', render: function (v, type) { return type === 'display' ? (v || '—') : v; } },
				{ data: 'impa_code', render: function (v, type) { return type === 'display' ? (v || '—') : v; } },
				{ data: 'unit', render: textRender },
				{ data: 'opening_stock', className: 'text-right', render: function (v, type) { return type === 'display' ? (v === null ? '—' : v) : v; } },
				{
					data: 'stock_qty', className: 'text-right',
					render: function (v, type, row) {
						if (type !== 'display') { return v; }
						return row.low ? v + ' <span class="badge badge-warning">low</span>' : v;
					}
				},
				{ data: 'min_qty', className: 'text-right', render: function (v, type) { return type === 'display' ? (v === null ? '—' : v) : v; } },
				{ data: 'last_supply_qty', className: 'text-right', render: function (v, type) { return type === 'display' ? (v === null ? '—' : v) : v; } },
				{ data: 'last_supply_date', render: function (v, type) { return type === 'display' ? (v ? textRender(v, type) : '—') : v; } },
			],
			order: [[0, 'asc']]
		});
	}

	function refresh() {
		if (!ready()) {
			$('#report-table-wrap, #report-summary').hide();
			$('#report-placeholder').show();
			return;
		}

		$('#report-placeholder').hide();
		$('#report-table-wrap').show();
		loadSummary();
		initTable();
	}

	$('#report_vessel_id, #report_category_id').on('change', refresh);

	$('#report_low_only').on('change', function () {
		if (reportTable) {
			reportTable.ajax.reload();
		}
		loadSummary();
	});

	// Master/Chief Engineer land with their vessel already fixed - only the
	// category is left to pick, so there is nothing to wire beyond the
	// change handlers above. Nothing auto-loads for anyone until a category
	// is actually chosen, ship-locked or not - the point of the placeholder
	// text is to say so, not to be replaced by a silent empty table.
});
</script>
@endsection
