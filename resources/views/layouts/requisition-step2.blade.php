@extends('layouts.admin-master')
@section('main-content')
<div class="order-section container">
	<div class="row">
		<div class="col-xl-12">
			<div class="card order-card">
				<div class="card-header first">
					<strong class="pptitle">{{ $order->title }} &nbsp;
						<span style="color:red;">{{ $order->vessel->name }}</span>
					</strong>
					<div class="right-button">Step 2 of 3 — Add Items</div>
				</div>
				<div class="card-body">
					@if($errors->any())
					<div class="alert alert-danger">
						<ul class="mb-0">
							@foreach($errors->all() as $error)
							<li>{{ $error }}</li>
							@endforeach
						</ul>
					</div>
					@endif

					<form id="add_order_items_form" method="POST" action="{{ route('requisition.step2.store', $order) }}">
						@csrf

						<div class="form-group row align-items-end">
							<div class="col-md-5">
								<label for="category"> Category: </label>
								<select class="form-control Category_Name" id="cate_name" name="Category_Name" required>
									<option value="" selected class='cat_opt'>-- Choose Category --</option>
									@foreach($categories as $category)
									<option value="{{$category->id}}" class='cat_opt' data-catalog="{{$category->is_catalog ? 1 : 0}}">{{$category->name}}</option>
									@endforeach
								</select>
							</div>
							<!-- Catalog-backed categories: one "Add Item" button opens the
								 search/browse-and-stage-multiple modal below. -->
							<div class="col-md-3" id="catalog-add-wrapper" style="display:none;">
								<button type="button" class="btn btn-info" id="open-item-picker" data-toggle="modal" data-target="#item-picker-modal">
									<i class="fa fa-plus"></i> Add Item
								</button>
							</div>
						</div>

						<div class="item-selection mt-4">
							<!-- Legacy (non-catalog) categories: the original flat dropdown - small
								 enough lists that a search modal isn't needed. -->
							<div id="legacy-add-wrapper" class="form-group row justify-content-between" style="display:none;">
								<div class="col-md-5">
									<label for="Item_Name"> Choose Item: </label>
									<select type="text" class="form-control" id="Item_Name">
										<option value="" selected class="item_opt_default">-- Select Item -- </option>
									</select>
								</div>
								<div class="col-md-2">
									<label for="item_qty"> Quantity: </label>
									<input type="number" class="form-control" id="item_qty" value="1">
								</div>
								<div class="col-md-2">
									<label for="">&nbsp;</label>  <br>
									<button type="button" class="btn btn-info btn-add" id="order_add">
										<i class="fa fa-plus"></i> Add
									</button>
								</div>
							</div>

							<hr>
							<div class="form-group row">
								<div class="col-md-12 item-list-shown">
									<table id="example1" class="table table-striped table-bordered orderedItemTable" style="width:100%">
										<thead>
											<tr>
												<th>SL NO</th>
												<th>Item Name with full Specifications</th>
												<th>IMPA Code</th>
												<th>Unit</th>
												<th>Opening Stock</th>
												<th>Quantity of Last Supply</th>
												<th>Date of Last Supply</th>
												<th>In Stock</th>
												<th>Required</th>
												<th>Office Use</th>
												<th class="action">Action</th>
											</tr>
										</thead>
										<tbody></tbody>
									</table>
								</div>
							</div>
							<div class="form-group row">
								<div class="col-md-12 text-right">
									<button type="submit" class="btn btn-success">Save &amp; Next: Review</button>
								</div>
							</div>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- Item picker modal: search folders/items by name, browse the tree, and
	 stage multiple items with their own quantities before committing them
	 all into the table above at once. -->
<div class="modal fade" id="item-picker-modal" tabindex="-1" role="dialog">
	<div class="modal-dialog modal-xl" role="document" style="max-width:95vw;">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Add Items</h5>
				<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body">
				<input type="text" id="item-picker-search" class="form-control mb-3" placeholder="Suggest Items or Folders">

				<nav aria-label="breadcrumb">
					<ol class="breadcrumb" id="item-picker-breadcrumb" style="flex-wrap:wrap;"></ol>
				</nav>

				<div id="item-picker-body">
					<div class="row">
						<div class="col-lg-4">
							<div id="item-picker-groups" class="list-group" style="height:360px; overflow-y:auto;"></div>
						</div>
						<div class="col-lg-8">
							<div id="item-picker-items" style="height:360px; overflow-y:auto;"></div>
						</div>
					</div>
				</div>
			</div>
			<div class="modal-footer">
				<div style="position:relative;">
					<a href="#" id="staged-items-toggle"><i class="fas fa-list"></i> <span id="staged-count">Added Items (0)</span></a>

					<div id="staged-items-panel" style="display:none; flex-direction:column; position:absolute; bottom:100%; right:0; width:440px; max-width:80vw; max-height:70vh; background:#fff; border:1px solid #dee2e6; border-radius:6px; box-shadow:0 2px 12px rgba(0,0,0,.18); margin-bottom:12px; z-index:1060;">
						<div class="text-right p-2 border-bottom" style="flex-shrink:0;"><a href="#" id="remove-all-staged" class="text-primary">Remove All</a></div>
						<div style="overflow-y:auto;">
							<table class="table table-sm mb-0">
								<thead>
									<tr style="background:#4a90c4;color:#fff;">
										<th>Name</th>
										<th style="width:90px;">Quantity</th>
										<th style="width:36px;"></th>
									</tr>
								</thead>
								<tbody id="staged-items-body"></tbody>
							</table>
						</div>
					</div>
				</div>
				<button type="button" class="btn btn-primary ml-3" id="commit-staged-items">Add 0 items</button>
			</div>
		</div>
	</div>
</div>
@endsection

@section('home-js')
<script>
$(function () {
	// dataForm.js already calls $('#example1').DataTable() with defaults
	// inside its own closure (assigned there to a local `orderTable` this
	// script can't see) - destroy+reinit here so this page can apply its own
	// options (no pagination/length menu - every added item should just be
	// visible in one list) and get a usable reference to the table back.
	var pageOrderTable = $('#example1').DataTable({
		destroy: true,
		paging: false,
		lengthChange: false,
		info: false,
		dom: 'frt',
	});

	// This page always starts a fresh item table - real persistence now
	// happens server-side per draft (Save & Next), so the old single-page
	// localStorage row cache would otherwise leak a previous draft's items
	// into a brand new one.
	localStorage.removeItem('orderItemRows');
	localStorage.removeItem('orderInfo');

	function esc(value) {
		return $('<div>').text(value === null || value === undefined ? '' : value).html();
	}

	/* ---------- Item picker modal: browse + search + stage + commit ---------- */

	var categoryId = '';
	var path = [];
	var staged = {}; // item_id -> {id, name, unit, article_number, qty}

	function syncCardButton(id) {
		var button = $('.item-picker-card[data-id="' + id + '"] .item-picker-add');
		if (!button.length) return;
		if (staged[id]) {
			button.text('Added').removeClass('btn-primary').addClass('btn-success');
		} else {
			button.text('Add').removeClass('btn-success').addClass('btn-primary');
		}
	}

	function renderStagedPanel() {
		var rows = Object.values(staged);
		if (rows.length === 0) {
			$('#staged-items-body').html('<tr><td colspan="3" class="text-muted text-center py-3">No items added yet.</td></tr>');
			return;
		}
		$('#staged-items-body').html(rows.map(function (item) {
			return '<tr data-id="' + item.id + '">'
				+ '<td>' + esc(item.name) + '</td>'
				+ '<td><input type="number" min="1" class="form-control form-control-sm staged-qty-edit" value="' + item.qty + '"></td>'
				+ '<td class="text-center"><a href="#" class="text-danger staged-remove"><i class="fas fa-trash-alt"></i></a></td>'
				+ '</tr>';
		}).join(''));
	}

	function refreshStagedFooter() {
		var count = Object.keys(staged).length;
		$('#staged-count').text('Added Items (' + count + ')');
		$('#commit-staged-items').text('Add ' + count + ' item' + (count === 1 ? '' : 's'));
		renderStagedPanel();
	}

	// Plain .show()/.hide()/.toggle() don't reliably restore "display:flex"
	// (the panel needs flex so its header stays put while the item list
	// scrolls beneath it), so this sets `display` explicitly both ways.
	function toggleStagedPanel(forceShow) {
		var panel = $('#staged-items-panel');
		var show = forceShow !== undefined ? forceShow : panel.css('display') === 'none';
		panel.css('display', show ? 'flex' : 'none');
	}

	$('#staged-items-toggle').on('click', function (e) {
		e.preventDefault();
		e.stopPropagation();
		toggleStagedPanel();
	});

	$(document).on('click', function (e) {
		if (!$(e.target).closest('#staged-items-panel, #staged-items-toggle').length) {
			toggleStagedPanel(false);
		}
	});

	$(document).on('change', '.staged-qty-edit', function () {
		var id = $(this).closest('tr').data('id');
		var qty = parseInt($(this).val(), 10) || 1;
		if (staged[id]) {
			staged[id].qty = qty;
		}
	});

	$(document).on('click', '.staged-remove', function (e) {
		e.preventDefault();
		var id = $(this).closest('tr').data('id');
		delete staged[id];
		syncCardButton(id);
		refreshStagedFooter();
	});

	$('#remove-all-staged').on('click', function (e) {
		e.preventDefault();
		var ids = Object.keys(staged);
		staged = {};
		ids.forEach(function (id) { syncCardButton(id); });
		refreshStagedFooter();
	});

	function renderBreadcrumb() {
		var html = '';
		path.forEach(function (crumb, i) {
			var isLast = i === path.length - 1;
			html += '<li class="breadcrumb-item' + (isLast ? ' active' : '') + '" '
				+ (isLast ? '' : 'data-index="' + i + '" style="cursor:pointer;"') + '>' + esc(crumb.name) + '</li>';
		});
		$('#item-picker-breadcrumb').html(html);
	}

	// What's already on board, shown at the point of asking for more - the
	// whole reason for tracking stock. Absent for items the vessel has never
	// stocked, in which case there's nothing useful to say.
	function stockLineHtml(item) {
		if (item.stock_qty === undefined) {
			return '';
		}

		var lastSupply = item.last_supply_date
			? 'Last supplied: ' + esc(String(item.last_supply_qty)) + ' ' + esc(item.unit) + ' on ' + esc(item.last_supply_date)
			: 'No previous supply recorded';

		var badgeClass = item.low_stock ? 'badge-warning' : 'badge-success';

		return '<div class="mb-2 small">'
			+ '<span class="badge ' + badgeClass + '">In stock: ' + esc(String(item.stock_qty)) + ' ' + esc(item.unit) + '</span> '
			+ '<span class="text-muted">' + lastSupply + '</span>'
			+ '</div>';
	}

	function itemCardHtml(item) {
		var existingQty = staged[item.id] ? staged[item.id].qty : 1;
		return '<div class="item-picker-card" data-id="' + item.id + '" data-name="' + esc(item.name) + '" data-unit="' + esc(item.unit) + '" data-article="' + esc(item.article_number) + '" '
			+ 'style="border:1px solid #dee2e6;border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:16px;margin-bottom:14px;background:#fff;">'
			+ '<h6 class="mb-2">' + esc(item.name) + '</h6>'
			+ '<div class="row small text-muted mb-2">'
			+ '<div class="col">Article no.<br><span class="text-dark">' + esc(item.article_number || 'n/a') + '</span></div>'
			+ '<div class="col">Drawing no.<br><span class="text-dark">' + esc(item.drawing_number || 'n/a') + '</span></div>'
			+ '<div class="col">Part no.<br><span class="text-dark">' + esc(item.part_number || 'n/a') + '</span></div>'
			+ '<div class="col">Manufacturer<br><span class="text-dark">' + esc(item.manufacturer || 'n/a') + '</span></div>'
			+ '</div>'
			+ stockLineHtml(item)
			+ '<div class="row align-items-end">'
			+ '<div class="col-4"><label class="mb-0 small">Quantity (' + esc(item.unit) + ')</label>'
			+ '<input type="number" min="1" class="form-control item-picker-qty" value="' + existingQty + '"></div>'
			+ '<div class="col-8 text-right"><button type="button" class="btn btn-sm ' + (staged[item.id] ? 'btn-success' : 'btn-primary') + ' item-picker-add">'
			+ (staged[item.id] ? 'Added' : 'Add') + '</button></div>'
			+ '</div></div>';
	}

	function renderItems(items) {
		if (items.length === 0) {
			$('#item-picker-items').html('<p class="text-muted p-2">No items here.</p>');
			return;
		}
		$('#item-picker-items').html(items.map(itemCardHtml).join(''));
	}

	function loadGroups(parentId) {
		$('#item-picker-groups').html('<p class="text-muted p-2">Loading…</p>');

		$.getJSON('{{url("/catalog/browse/children")}}/' + (parentId || ''), { category_id: categoryId }, function (groups) {
			var html = '';
			groups.forEach(function (g) {
				var badge = g.children_count > 0
					? '<span class="badge badge-secondary float-right">' + g.children_count + '</span>'
					: '<span class="badge badge-info float-right">' + g.items_count + ' items</span>';
				html += '<a href="#" class="list-group-item list-group-item-action picker-group-link" data-id="' + g.id + '" data-name="' + esc(g.name) + '">'
					+ esc(g.name) + badge + '</a>';
			});
			$('#item-picker-groups').html(html || '<p class="text-muted p-2">No sub-folders here.</p>');

			if (groups.length === 0) {
				loadItemsForGroup(parentId);
			} else {
				$('#item-picker-items').html('<p class="text-muted p-2">Select a folder to see its items.</p>');
			}
		});
	}

	function loadItemsForGroup(groupId) {
		$('#item-picker-items').html('<p class="text-muted p-2">Loading items…</p>');
		$.getJSON('{{url("/catalog/browse/items")}}/' + groupId, function (items) { renderItems(items); });
	}

	function runSearch(term) {
		$.getJSON('{{url("/catalog/browse/search")}}', { category_id: categoryId, q: term }, function (result) {
			var html = '';
			result.groups.forEach(function (g) {
				html += '<a href="#" class="list-group-item list-group-item-action picker-group-link" data-id="' + g.id + '" data-name="' + esc(g.name) + '"><i class="fas fa-folder text-warning mr-1"></i>' + esc(g.name) + '</a>';
			});
			$('#item-picker-groups').html(html || '<p class="text-muted p-2">No matching folders.</p>');
			renderItems(result.items);
		});
	}

	var searchTimer = null;
	$('#item-picker-search').on('keyup', function () {
		var term = $(this).val();
		clearTimeout(searchTimer);
		searchTimer = setTimeout(function () {
			if (term === '') {
				path = [{ id: '', name: $('#cate_name').children('option:selected').text() }];
				renderBreadcrumb();
				loadGroups('');
			} else {
				$('#item-picker-breadcrumb').empty();
				runSearch(term);
			}
		}, 250);
	});

	$(document).on('click', '.picker-group-link', function (e) {
		e.preventDefault();
		$('#item-picker-search').val('');
		path.push({ id: $(this).data('id'), name: $(this).data('name') });
		renderBreadcrumb();
		loadGroups($(this).data('id'));
	});

	$(document).on('click', '#item-picker-breadcrumb li[data-index]', function () {
		var index = $(this).data('index');
		path = path.slice(0, index + 1);
		renderBreadcrumb();
		loadGroups(path[path.length - 1].id);
	});

	$(document).on('click', '.item-picker-add', function () {
		var card = $(this).closest('.item-picker-card');
		var id = card.data('id');
		var qty = parseInt(card.find('.item-picker-qty').val(), 10) || 1;

		staged[id] = { id: id, name: card.data('name'), unit: card.data('unit'), article_number: card.data('article'), qty: qty };
		$(this).text('Added').removeClass('btn-primary').addClass('btn-success');
		refreshStagedFooter();
	});

	$('#item-picker-modal').on('show.bs.modal', function () {
		categoryId = $('#cate_name').val();
		staged = {};
		refreshStagedFooter();
		toggleStagedPanel(false);
		$('#item-picker-search').val('');
		path = [{ id: '', name: $('#cate_name').children('option:selected').text() }];
		renderBreadcrumb();
		loadGroups('');
	});

	$('#commit-staged-items').on('click', function () {
		Object.values(staged).forEach(function (item) {
			var existingRow = pageOrderTable.row('#row_ordered_item-' + item.id);
			if (existingRow.any()) {
				existingRow.remove();
			}

			// .rows().count() (the DataTables API) reflects the true total
			// regardless of pagination/DOM state - a raw $('tbody tr').length
			// DOM query doesn't (that was the earlier bug behind duplicate
			// serial numbers when adding more than one item at once).
			var idx = pageOrderTable.rows().count() + 1;
			// Column order matches the paper-form layout (SL NO / Item Name /
			// IMPA Code / Unit / Opening Stock / Qty of Last Supply / Date of
			// Last Supply / In Stock / Required / Office Use / Action) - the
			// stock-history columns have no data source yet, so they render
			// blank for now. Article Number stands in for IMPA Code since
			// imported items always carry the '-' placeholder for the latter.
			pageOrderTable.row.add([
				'<b class="serial">' + idx + '</b>',
				esc(item.name) + '<input type="hidden" name="item_id[]" value="' + item.id + '">',
				esc(item.article_number),
				esc(item.unit),
				'', '', '', '',
				'<span class="added_qty">' + item.qty + '</span><input type="hidden" name="item_qty[]" value="' + item.qty + '">',
				'',
				'<button type="button" class="btn btn-danger btn-sm delete-order-item-row"><i class="fas fa-trash-alt"></i></button>',
			]).draw().node().id = 'row_ordered_item-' + item.id;
		});

		$('#item-picker-modal').modal('hide');
	});

	$(document).on('click', '.delete-order-item-row', function () {
		pageOrderTable.row($(this).closest('tr')).remove().draw();
	});

	/* ---------- Category select: toggle catalog-modal vs legacy dropdown ---------- */

	$('select#cate_name').on('change', function () {
		var isCatalog = $(this).children('option:selected').data('catalog') == 1;

		if (isCatalog) {
			$('#catalog-add-wrapper').show();
			$('#legacy-add-wrapper').hide();
		} else {
			$('#catalog-add-wrapper').hide();
			$('#legacy-add-wrapper').show();
		}
	});
});
</script>
@endsection
