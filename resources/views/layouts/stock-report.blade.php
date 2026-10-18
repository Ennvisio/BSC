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
				<div class="col-md-4">
					<label for="report_group_toggle">Group</label>
					{{-- Tree picker over the catalog's own item groups. Picking a
						 parent reports everything beneath it; picking a child,
						 only that branch. Loaded a level at a time, since one
						 category alone can hold thousands of groups. --}}
					<div class="sr-tree-select" id="report_group_picker">
						<button type="button" class="form-control sr-tree-field" id="report_group_toggle" disabled
							aria-haspopup="true" aria-expanded="false">
							<span class="sr-tree-field-text">Choose a category first</span>
							<i class="fas fa-caret-down"></i>
						</button>
						<div class="sr-tree-panel" id="report_group_panel" hidden>
							<button type="button" class="sr-tree-all" id="report_group_all">All groups in this category</button>
							<ul class="sr-tree" id="report_group_tree"></ul>
						</div>
					</div>
					<input type="hidden" id="report_group_id" value="">
				</div>
			</div>
			<div class="row mb-3">
				<div class="col-md-12">
					<div class="custom-control custom-checkbox">
						<input type="checkbox" class="custom-control-input" id="report_low_only">
						<label class="custom-control-label" for="report_low_only">Low stock only</label>
					</div>
				</div>
			</div>

			<div class="row mb-3" id="report-summary" style="display:none;">
				<div class="col-md-6"><div class="alert alert-secondary mb-0 text-center"><b id="summary_total">0</b> items in <span id="summary_scope">this category</span></div></div>
				<div class="col-md-6"><div class="alert alert-secondary mb-0 text-center"><b id="summary_zero">0</b> at zero stock</div></div>
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
</style>
<script>
$(function () {
	var reportTable = null;

	// Both the summary tiles and the table read the same two selections, so
	// this is the one place that decides what "nothing chosen yet" means.
	function selection() {
		return {
			vessel_id: $('#report_vessel_id').val(),
			category_id: $('#report_category_id').val(),
			group_id: $('#report_group_id').val(),
		};
	}

	// ---- Group tree picker ----
	var $groupField = $('#report_group_toggle'), $groupPanel = $('#report_group_panel');

	function escapeHtml(text) {
		return $('<div>').text(text).html();
	}

	function setGroup(id, label, title) {
		$('#report_group_id').val(id || '');
		$groupField.find('.sr-tree-field-text').text(label).attr('title', title || label);
		$('#summary_scope').text(id ? label : 'this category');
		$('#report_group_tree .sr-tree-node').removeClass('is-selected');
		if (id) {
			$('#report_group_tree li[data-id="' + id + '"] > .sr-tree-node').addClass('is-selected');
		}
	}

	function closeGroupPanel() {
		$groupPanel.prop('hidden', true);
		$groupField.attr('aria-expanded', 'false');
	}

	// One level of the tree into $list - the top level when parentId is null.
	function loadGroups(parentId, $list) {
		$list.html('<li class="sr-tree-msg">Loading…</li>');
		var params = { vessel_id: $('#report_vessel_id').val(), category_id: $('#report_category_id').val() };
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
			// Keep the current choice highlighted when its level loads in.
			$list.children('li[data-id="' + $('#report_group_id').val() + '"]').children('.sr-tree-node').addClass('is-selected');
		}).fail(function () {
			$list.html('<li class="sr-tree-msg">Could not load groups.</li>');
		});
	}

	// Vessel or category changed: the old tree and selection no longer apply.
	function resetGroupPicker() {
		closeGroupPanel();
		$('#report_group_tree').empty().data('loaded', false);
		var canPick = !!($('#report_vessel_id').val() && $('#report_category_id').val());
		$groupField.prop('disabled', !canPick);
		setGroup('', canPick ? 'All groups' : 'Choose a category first');
	}

	$groupField.on('click', function () {
		var opening = $groupPanel.prop('hidden');
		$groupPanel.prop('hidden', !opening);
		$groupField.attr('aria-expanded', opening ? 'true' : 'false');
		if (opening && !$('#report_group_tree').data('loaded')) {
			$('#report_group_tree').data('loaded', true);
			loadGroups(null, $('#report_group_tree'));
		}
	});

	$('#report_group_tree').on('click', '.sr-tree-caret', function () {
		var $li = $(this).closest('li'), $children = $li.children('ul');
		var expanding = $children.prop('hidden');
		$children.prop('hidden', !expanding);
		$(this).find('i').toggleClass('fa-caret-right', !expanding).toggleClass('fa-caret-down', expanding);
		if (expanding && !$children.data('loaded')) {
			$children.data('loaded', true);
			loadGroups($li.data('id'), $children);
		}
	});

	$('#report_group_tree').on('click', '.sr-tree-label', function () {
		var $li = $(this).closest('li');
		setGroup($li.data('id'), $(this).text(), $li.data('path'));
		closeGroupPanel();
		refresh();
	});

	$('#report_group_all').on('click', function () {
		setGroup('', 'All groups');
		closeGroupPanel();
		refresh();
	});

	$(document).on('click', function (e) {
		if (!$(e.target).closest('#report_group_picker').length) { closeGroupPanel(); }
	}).on('keydown', function (e) {
		if (e.key === 'Escape') { closeGroupPanel(); }
	});

	function ready() {
		var sel = selection();
		return !!(sel.vessel_id && sel.category_id);
	}

	function loadSummary() {
		if (!ready()) {
			$('#report-summary').hide();
			return;
		}

		var params = selection();
		if (!params.group_id) { delete params.group_id; }
		$.getJSON('{{ route('stock.report.summary') }}', params, function (res) {
			$('#summary_total').text(res.total_items.toLocaleString());
			$('#summary_zero').text(res.zero_stock.toLocaleString());
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

		// Escape text from the catalog before it goes into the table. Not
		// $.fn.dataTable.render.text(): in DataTables 1.10.19 that returns an
		// {display, filter} object, not a function, so calling it throws.
		function textRender(v, type) {
			if (v === null || v === undefined) { return ''; }
			return type === 'display' ? escapeHtml(String(v)) : v;
		}

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
					if (sel.group_id) { d.group_id = sel.group_id; }
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

	$('#report_vessel_id, #report_category_id').on('change', function () {
		resetGroupPicker();
		refresh();
	});

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
