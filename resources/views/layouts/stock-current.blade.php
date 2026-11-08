@extends('layouts.admin-master')
@section('main-content')
<div class="col-lg-6 col-xl-12">
	<div class="card">
		<div class="card-header pv-card-hader">
			<strong class="pptitle">Current Stock{{ $lockedVessel ? ' — '.$lockedVessel->name : '' }}</strong>
		</div>
		<div class="card-body">
			<div class="row mb-3">
				<div class="col-md-4">
					<label for="cs_vessel_id">Vessel</label>
					@if($lockedVessel)
					<input type="text" class="form-control" value="{{ $lockedVessel->name }}" disabled>
					<input type="hidden" id="cs_vessel_id" value="{{ $lockedVessel->id }}">
					@else
					<select id="cs_vessel_id" class="form-control">
						<option value="">Choose vessel…</option>
						@foreach($vessels as $vessel)
						<option value="{{ $vessel->id }}">{{ $vessel->name }}</option>
						@endforeach
					</select>
					@endif
				</div>
				<div class="col-md-4">
					<label for="cs_category_id">Category</label>
					<select id="cs_category_id" class="form-control">
						<option value="">Choose category…</option>
						@foreach($categories as $category)
						<option value="{{ $category->id }}">{{ $category->name }}</option>
						@endforeach
					</select>
				</div>
				<div class="col-md-4">
					<label for="cs_group_toggle">Group</label>
					{{-- Same tree picker as Stock Report (reads the same
						 /stock/report/groups endpoint - the group tree doesn't
						 differ between the two pages). --}}
					<div class="sr-tree-select" id="cs_group_picker">
						<button type="button" class="form-control sr-tree-field" id="cs_group_toggle" disabled
							aria-haspopup="true" aria-expanded="false">
							<span class="sr-tree-field-text">Choose a category first</span>
							<i class="fas fa-caret-down"></i>
						</button>
						<div class="sr-tree-panel" id="cs_group_panel" hidden>
							<button type="button" class="sr-tree-all" id="cs_group_all">All groups in this category</button>
							<ul class="sr-tree" id="cs_group_tree"></ul>
						</div>
					</div>
					<input type="hidden" id="cs_group_id" value="">
				</div>
			</div>
			<div class="row mb-3">
				<div class="col-md-6">
					<div class="custom-control custom-checkbox">
						<input type="checkbox" class="custom-control-input" id="cs_low_only">
						<label class="custom-control-label" for="cs_low_only">Low stock only</label>
					</div>
				</div>
				<div class="col-md-6 text-right" id="cs-actions" style="display:none;">
					<a href="#" id="cs-export-link" class="btn btn-outline-secondary btn-sm" target="_blank"><i class="fas fa-file-excel"></i> Export</a>
					<a href="#" id="cs-print-link" class="btn btn-outline-secondary btn-sm" target="_blank"><i class="fas fa-print"></i> Print</a>
				</div>
			</div>

			<div class="row mb-3" id="cs-summary" style="display:none;">
				<div class="col-6 col-md-3 mb-2"><div class="alert alert-secondary mb-0 text-center"><b id="cs_in_stock">0</b><div style="font-size:12px;">In stock</div></div></div>
				<div class="col-6 col-md-3 mb-2"><div class="alert alert-secondary mb-0 text-center"><b id="cs_zero_stock">0</b><div style="font-size:12px;">Zero stock</div></div></div>
				<div class="col-6 col-md-3 mb-2"><div class="alert alert-warning mb-0 text-center"><b id="cs_below_minimum">0</b><div style="font-size:12px;">Below minimum</div></div></div>
				<div class="col-6 col-md-3 mb-2"><div class="alert alert-secondary mb-0 text-center"><b id="cs_consumed_month">0</b><div style="font-size:12px;">Consumed this month</div></div></div>
			</div>

			<p class="text-muted" id="cs-placeholder">
				Choose a {{ $lockedVessel ? '' : 'vessel and ' }}category above to see current stock.
			</p>

			<div class="table-responsive" id="cs-table-wrap" style="display:none;">
				<table id="current-stock-table" class="table table-bordered table-sm" style="width:100%;">
					<thead>
						<tr>
							<th>Item Name</th>
							<th>Article Number</th>
							<th>IMPA Code</th>
							<th>Unit</th>
							<th>Opening</th>
							<th>Received</th>
							<th>Consumed</th>
							<th>Net Adjustments</th>
							<th>Current Stock</th>
							<th>Min Qty</th>
							<th>Last Supply Qty</th>
							<th>Last Supply Date</th>
							<th>Last Consumed</th>
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
<style>
.sr-tree-select{ position:relative; }
.sr-tree-field{ display:flex; align-items:center; justify-content:space-between; gap:8px; text-align:left; cursor:pointer; background:#fff; }
.sr-tree-field:disabled{ cursor:not-allowed; background:#e9ecef; }
.sr-tree-field-text{ overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.sr-tree-panel{
	position:absolute; z-index:20; top:calc(100% + 4px); left:0; right:0;
	max-height:360px; overflow:auto; background:#fff;
	border:1px solid #ced4da; border-radius:6px; box-shadow:0 8px 24px rgba(0,0,0,.12); padding:6px 0;
}
.sr-tree-all{ display:block; width:100%; text-align:left; border:0; background:none; padding:6px 12px; font-weight:600; color:#005866; }
.sr-tree-all:hover, .sr-tree-all:focus{ background:#eef6f6; }
.sr-tree, .sr-tree ul{ list-style:none; margin:0; padding:0; }
.sr-tree ul{ padding-left:18px; }
.sr-tree-node{ display:flex; align-items:center; gap:4px; padding:2px 8px; }
.sr-tree-node.is-selected{ background:#e3f1f1; }
.sr-tree-caret{ width:20px; height:22px; flex:0 0 20px; border:0; background:none; padding:0; color:#5f6b6b; }
.sr-tree-caret[disabled]{ visibility:hidden; }
.sr-tree-label{ flex:1; min-width:0; border:0; background:none; text-align:left; padding:2px 4px; border-radius:4px; font-size:13.5px; }
.sr-tree-label:hover, .sr-tree-label:focus{ background:#eef6f6; }
.sr-tree-count{ font-size:11.5px; color:#6b7877; font-variant-numeric:tabular-nums; }
.sr-tree-msg{ padding:4px 12px; color:#6b7877; font-size:13px; }
#current-stock-table tbody tr{ cursor:pointer; }
</style>
<script>
$(function () {
	var currentStockTable = null;

	function selection() {
		return {
			vessel_id: $('#cs_vessel_id').val(),
			category_id: $('#cs_category_id').val(),
			group_id: $('#cs_group_id').val(),
		};
	}

	// ---- Group tree picker (same shape as Stock Report's own) ----
	var $groupField = $('#cs_group_toggle'), $groupPanel = $('#cs_group_panel');

	function escapeHtml(text) {
		return $('<div>').text(text).html();
	}

	function setGroup(id, label, title) {
		$('#cs_group_id').val(id || '');
		$groupField.find('.sr-tree-field-text').text(label).attr('title', title || label);
		$('#cs_group_tree .sr-tree-node').removeClass('is-selected');
		if (id) {
			$('#cs_group_tree li[data-id="' + id + '"] > .sr-tree-node').addClass('is-selected');
		}
	}

	function closeGroupPanel() {
		$groupPanel.prop('hidden', true);
		$groupField.attr('aria-expanded', 'false');
	}

	function loadGroups(parentId, $list) {
		$list.html('<li class="sr-tree-msg">Loading…</li>');
		var params = { vessel_id: $('#cs_vessel_id').val(), category_id: $('#cs_category_id').val() };
		if (parentId) { params.parent_id = parentId; }

		$.getJSON('{{ route('stock.report.groups') }}', params, function (groups) {
			if (!groups.length) {
				$list.html('<li class="sr-tree-msg">No stocked items under this group.</li>');
				return;
			}
			$list.html($.map(groups, function (g) {
				return '<li data-id="' + g.id + '" data-path="' + escapeHtml(g.path) + '">' +
					'<div class="sr-tree-node">' +
						'<button type="button" class="sr-tree-caret" aria-label="Expand"' + (g.has_children ? '' : ' disabled') + '>' +
							'<i class="fas fa-caret-right"></i></button>' +
						'<button type="button" class="sr-tree-label">' + escapeHtml(g.name) + '</button>' +
						'<span class="sr-tree-count">' + g.items.toLocaleString() + '</span>' +
					'</div>' +
					'<ul hidden></ul>' +
				'</li>';
			}).join(''));
			$list.children('li[data-id="' + $('#cs_group_id').val() + '"]').children('.sr-tree-node').addClass('is-selected');
		}).fail(function () {
			$list.html('<li class="sr-tree-msg">Could not load groups.</li>');
		});
	}

	function resetGroupPicker() {
		closeGroupPanel();
		$('#cs_group_tree').empty().data('loaded', false);
		var canPick = !!($('#cs_vessel_id').val() && $('#cs_category_id').val());
		$groupField.prop('disabled', !canPick);
		setGroup('', canPick ? 'All groups' : 'Choose a category first');
	}

	$groupField.on('click', function () {
		var opening = $groupPanel.prop('hidden');
		$groupPanel.prop('hidden', !opening);
		$groupField.attr('aria-expanded', opening ? 'true' : 'false');
		if (opening && !$('#cs_group_tree').data('loaded')) {
			$('#cs_group_tree').data('loaded', true);
			loadGroups(null, $('#cs_group_tree'));
		}
	});

	$('#cs_group_tree').on('click', '.sr-tree-caret', function () {
		var $li = $(this).closest('li'), $children = $li.children('ul');
		var expanding = $children.prop('hidden');
		$children.prop('hidden', !expanding);
		$(this).find('i').toggleClass('fa-caret-right', !expanding).toggleClass('fa-caret-down', expanding);
		if (expanding && !$children.data('loaded')) {
			$children.data('loaded', true);
			loadGroups($li.data('id'), $children);
		}
	});

	$('#cs_group_tree').on('click', '.sr-tree-label', function () {
		var $li = $(this).closest('li');
		setGroup($li.data('id'), $(this).text(), $li.data('path'));
		closeGroupPanel();
		refresh();
	});

	$('#cs_group_all').on('click', function () {
		setGroup('', 'All groups');
		closeGroupPanel();
		refresh();
	});

	$(document).on('click', function (e) {
		if (!$(e.target).closest('#cs_group_picker').length) { closeGroupPanel(); }
	}).on('keydown', function (e) {
		if (e.key === 'Escape') { closeGroupPanel(); }
	});

	function ready() {
		var sel = selection();
		return !!(sel.vessel_id && sel.category_id);
	}

	function exportParams() {
		var sel = selection();
		var params = { vessel_id: sel.vessel_id, category_id: sel.category_id, low_only: $('#cs_low_only').is(':checked') ? 1 : 0 };
		if (sel.group_id) { params.group_id = sel.group_id; }
		return $.param(params);
	}

	function updateActionLinks() {
		$('#cs-export-link').attr('href', '{{ route('inventory.stock.export') }}?' + exportParams());
		$('#cs-print-link').attr('href', '{{ route('inventory.stock.print') }}?' + exportParams());
	}

	function loadSummary() {
		if (!ready()) {
			$('#cs-summary').hide();
			return;
		}

		var params = selection();
		if (!params.group_id) { delete params.group_id; }
		$.getJSON('{{ route('inventory.stock.summary') }}', params, function (res) {
			$('#cs_in_stock').text(res.in_stock.toLocaleString());
			$('#cs_zero_stock').text(res.zero_stock.toLocaleString());
			$('#cs_below_minimum').text(res.below_minimum.toLocaleString());
			$('#cs_consumed_month').text(res.consumed_this_month.toLocaleString());
			$('#cs-summary').show();
		});
	}

	function initTable() {
		if (currentStockTable) {
			currentStockTable.destroy();
			$('#current-stock-table tbody').empty();
		}

		function textRender(v, type) {
			if (v === null || v === undefined) { return ''; }
			return type === 'display' ? escapeHtml(String(v)) : v;
		}
		function numRender(v, type) {
			return type === 'display' ? (v === null || v === undefined ? '—' : v) : v;
		}

		currentStockTable = $('#current-stock-table').DataTable({
			serverSide: true,
			processing: true,
			lengthMenu: [25, 50, 100, 250],
			ajax: {
				url: '{{ route('inventory.stock.data') }}',
				data: function (d) {
					var sel = selection();
					d.vessel_id = sel.vessel_id;
					d.category_id = sel.category_id;
					if (sel.group_id) { d.group_id = sel.group_id; }
					d.low_only = $('#cs_low_only').is(':checked') ? 1 : 0;
				}
			},
			columns: [
				{ data: 'name', render: textRender },
				{ data: 'article_number', render: function (v, type) { return type === 'display' ? (v || '—') : v; } },
				{ data: 'impa_code', render: function (v, type) { return type === 'display' ? (v || '—') : v; } },
				{ data: 'unit', render: textRender },
				{ data: 'opening_stock', className: 'text-right', render: numRender },
				{ data: 'total_received', className: 'text-right', render: numRender },
				{ data: 'total_consumed', className: 'text-right', render: numRender },
				{ data: 'net_adjustments', className: 'text-right', render: numRender },
				{
					data: 'stock_qty', className: 'text-right',
					render: function (v, type, row) {
						if (type !== 'display') { return v; }
						return row.low ? v + ' <span class="badge badge-warning">low</span>' : v;
					}
				},
				{ data: 'min_qty', className: 'text-right', render: numRender },
				{ data: 'last_supply_qty', className: 'text-right', render: numRender },
				{ data: 'last_supply_date', render: function (v, type) { return type === 'display' ? (v ? textRender(v, type) : '—') : v; } },
				{ data: 'last_consumed_date', render: function (v, type) { return type === 'display' ? (v ? textRender(v, type) : '—') : v; } },
			],
			order: [[0, 'asc']]
		});
	}

	// Row click opens that item's full stock card for the vessel currently
	// in view - delegated so it survives initTable() rebuilding the table.
	$('#current-stock-table tbody').on('click', 'tr', function () {
		var data = currentStockTable && currentStockTable.row(this).data();
		if (!data || !data.item_id) { return; }
		window.location = '{{ url('/inventory/stock') }}/' + data.item_id + '?vessel_id=' + encodeURIComponent($('#cs_vessel_id').val());
	});

	function refresh() {
		if (!ready()) {
			$('#cs-table-wrap, #cs-summary, #cs-actions').hide();
			$('#cs-placeholder').show();
			return;
		}

		$('#cs-placeholder').hide();
		$('#cs-table-wrap, #cs-actions').show();
		updateActionLinks();
		loadSummary();
		initTable();
	}

	$('#cs_vessel_id, #cs_category_id').on('change', function () {
		resetGroupPicker();
		refresh();
	});

	$('#cs_low_only').on('change', function () {
		updateActionLinks();
		if (currentStockTable) {
			currentStockTable.ajax.reload();
		}
		loadSummary();
	});
});
</script>
@endsection
