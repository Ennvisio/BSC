@extends('layouts.admin-master')
@section('main-content')
<div class="col-lg-12">
	<div class="card">
		<div class="card-header pv-card-hader">
			<strong class="pptitle">Browse Catalog{{ $vessel ? ' — '.$vessel->name : '' }}</strong>
		</div>
		<div class="card-body">
			<div class="row mb-3">
				<div class="col-md-4"><div class="alert alert-secondary mb-0 text-center"><b>{{$groupCount}}</b> categories/folders</div></div>
				<div class="col-md-4"><div class="alert alert-secondary mb-0 text-center"><b>{{$itemCount}}</b> items</div></div>
				<div class="col-md-4"><div class="alert alert-secondary mb-0 text-center">
					@if($vessel)
					Catalog for <b>{{$vessel->name}}</b>
					@else
					<b>{{$vesselCount}}</b> vessels
					@endif
				</div></div>
			</div>

			<div class="form-group row">
				{{-- Shore/admin only - a ship user's vessel is already fixed,
					 so there's nothing to pick. Vessel comes first: it has to
					 be chosen before Category can mean anything (the tree and
					 item list below are both scoped to whichever vessel is
					 selected, same as a ship user already sees automatically -
					 see CatalogController::resolveVesselId()). Left on
					 "fleet-wide" (blank), every category/folder/item still
					 shows the same unscoped list as before. --}}
				@if(isset($vessels))
				<div class="col-md-4">
					<label for="catalog-vessel">Vessel</label>
					<select id="catalog-vessel" class="form-control">
						<option value="">-- Fleet-wide (all vessels) --</option>
						@foreach($vessels as $v)
						<option value="{{$v->id}}">{{$v->name}}</option>
						@endforeach
					</select>
				</div>
				@endif
				<div class="col-md-4">
					<label for="catalog-category">Category</label>
					<select id="catalog-category" class="form-control">
						<option value="">-- Choose category --</option>
						@foreach($categories as $category)
						<option value="{{$category->id}}">{{$category->name}}</option>
						@endforeach
					</select>
				</div>
			</div>

			<nav aria-label="breadcrumb">
				<ol class="breadcrumb" id="catalog-breadcrumb" style="flex-wrap:wrap;"></ol>
			</nav>

			<div class="row" id="catalog-tree-wrapper" style="display:none;">
				<div class="col-lg-4">
					<input type="text" id="catalog-group-filter" class="form-control mb-2" placeholder="Filter this list…">
					<div id="catalog-groups" class="list-group" style="height:calc(100vh - 320px); min-height:360px; overflow-y:auto;"></div>
				</div>
				<div class="col-lg-8">
					<div id="catalog-items-wrapper"></div>
				</div>
			</div>
		</div>
	</div>
</div>
@endsection

@section('home-js')
<script>
$(function () {
	// path[0] is always the synthetic root (the chosen category's own name, id '').
	var path = [];
	var itemsTable = null;
	var categoryId = '';
	// '' = fleet-wide. Only ever set client-side for shore/admin - a ship
	// user has no #catalog-vessel select at all, so this just stays '' and
	// the server resolves their own vessel on its own (see
	// CatalogController::resolveVesselId()).
	var vesselId = '';
	// A ship user's own vessel, already resolved server-side - the item
	// stock card link below needs SOME vessel id to point at even though
	// there's no #catalog-vessel select to read one from in that case.
	var lockedVesselId = {{ $vessel->id ?? 'null' }};

	function itemStockCardUrl(itemId) {
		var vid = vesselId || lockedVesselId;
		return vid ? '{{ url('/inventory/stock') }}/' + itemId + '?vessel_id=' + encodeURIComponent(vid) : null;
	}

	function esc(value) {
		return $('<div>').text(value === null || value === undefined ? '' : value).html();
	}

	function renderBreadcrumb() {
		var html = '';
		path.forEach(function (crumb, i) {
			var isLast = i === path.length - 1;
			html += '<li class="breadcrumb-item' + (isLast ? ' active' : '') + '" '
				+ (isLast ? '' : 'data-index="' + i + '" style="cursor:pointer;"') + '>' + esc(crumb.name) + '</li>';
		});
		$('#catalog-breadcrumb').html(html);
	}

	function loadGroups(parentId) {
		$('#catalog-group-filter').val('');
		$('#catalog-groups').html('<p class="text-muted p-2">Loading…</p>');

		$.getJSON('{{url("/catalog/browse/children")}}/' + (parentId || ''), { category_id: categoryId, vessel_id: vesselId }, function (groups) {
			if (groups.length === 0) {
				$('#catalog-groups').html('<p class="text-muted p-3">No sub-categories here — showing its items on the right. &rarr;</p>');
				if (parentId) {
					// A leaf folder - its own items live right here.
					loadItems(parentId);
				} else {
					// The category itself has no folders at all - asking
					// items() for group id '' silently becomes
					// "item_group_id = ''" (matching nothing, or even 404ing,
					// since the route requires a {groupId} segment) instead
					// of the category's own items.
					loadCategoryItems();
				}
				return;
			}

			var html = '';
			groups.forEach(function (g) {
				var badge = g.children_count > 0
					? '<span class="badge badge-secondary float-right">' + g.children_count + ' sub-categories</span>'
					: '<span class="badge badge-info float-right">' + g.items_count + ' items</span>';
				html += '<a href="#" class="list-group-item list-group-item-action catalog-group-link" data-id="' + g.id + '" data-name="' + esc(g.name) + '">'
					+ '<span class="catalog-group-name">' + esc(g.name) + '</span>' + badge + '</a>';
			});
			$('#catalog-groups').html(html);
			$('#catalog-items-wrapper').html('<p class="text-muted mt-2">Select a category on the left to drill down, or one already showing an items count to see its items here.</p>');
		}).fail(function () {
			$('#catalog-groups').html('<p class="text-danger p-2">Could not load folders. Please try again.</p>');
			$('#catalog-items-wrapper').html('<p class="text-danger p-2">Could not load items. Please try again.</p>');
		});
	}

	// One figure cell: plain text for everyone, an editable box for the Master
	// (the only role allowed to state what's in the ship's store). The same
	// function serves Opening Stock and In Stock - which column an input
	// belongs to travels with it in data-figure, so one save handler covers
	// both and the server is told exactly which figure changed.
	function stockCellHtml(item, figure, canEdit) {
		var raw = item[figure];
		var value = (raw === null || raw === undefined) ? '' : String(raw);

		// The low-stock flag belongs to the live figure, not the opening one.
		var low = (figure === 'stock_qty' && item.low_stock)
			? ' <span class="badge badge-warning" title="At or below minimum">low</span>'
			: '';

		if (!canEdit) {
			return '<span class="stock-value">' + esc(value === '' ? '—' : value) + '</span>' + low;
		}

		return '<input type="number" min="0" class="form-control form-control-sm stock-input" '
			+ 'data-figure="' + figure + '" '
			+ 'style="width:80px; display:inline-block;" value="' + esc(value) + '" '
			+ 'data-original="' + esc(value) + '">' + low;
	}

	function renderItemsTable(items, emptyMessage) {
		if (items.length === 0) {
			$('#catalog-items-wrapper').html('<p class="text-muted">' + emptyMessage + '</p>');
			return;
		}

		// Stock is only ever meaningful when one particular vessel is in view
		// (a ship user's own, or a shore/admin user's chosen one) - the API
		// only sends it in that case.
		var hasStock = items.length > 0 && items[0].stock_qty !== undefined;
		var canEditStock = hasStock && items[0].can_edit_stock;
		// Which vessels stock an item is fleet information - the API only
		// sends it when browsing fleet-wide, so the column only exists then.
		var hasVessels = items.length > 0 && items[0].vessels !== undefined;

		var html = '<div class="table-responsive"><table id="catalog-items-table" class="table table-sm table-bordered" style="width:100%;">'
			+ '<thead><tr><th>Article #</th><th>Name</th><th>Unit</th>'
			+ (hasStock ? '<th>Opening Stock</th><th>In Stock</th>' : '')
			+ '<th>Account #</th><th>Description</th>'
			+ '<th>Part #</th><th>Drawing #</th><th>HS Code</th><th>Manufacturer</th>'
			+ (hasVessels ? '<th>Vessels</th>' : '') + '</tr></thead><tbody>';
		items.forEach(function (i) {
			var cardUrl = hasStock ? itemStockCardUrl(i.id) : null;
			var nameCell = cardUrl ? '<a href="' + cardUrl + '">' + esc(i.name) + '</a>' : esc(i.name);

			html += '<tr>'
				+ '<td>' + esc(i.article_number) + '</td>'
				+ '<td>' + nameCell + '</td>'
				+ '<td>' + esc(i.unit) + '</td>'
				+ (hasStock
					? '<td class="stock-cell" data-item-id="' + i.id + '">' + stockCellHtml(i, 'opening_stock', canEditStock) + '</td>'
					+ '<td class="stock-cell" data-item-id="' + i.id + '">' + stockCellHtml(i, 'stock_qty', canEditStock) + '</td>'
					: '')
				+ '<td>' + esc(i.account_number) + '</td>'
				+ '<td>' + esc(i.description) + '</td>'
				+ '<td>' + esc(i.part_number) + '</td>'
				+ '<td>' + esc(i.drawing_number) + '</td>'
				+ '<td>' + esc(i.hs_code) + '</td>'
				+ '<td>' + esc(i.manufacturer) + '</td>'
				+ (hasVessels
					? '<td>' + ((i.vessels || []).map(function (v) { return esc(v.name); }).join(', ')
						|| '<span class="text-muted">none</span>') + '</td>'
					: '')
				+ '</tr>';
		});
		html += '</tbody></table></div>';

		if (itemsTable) { itemsTable.destroy(); itemsTable = null; }
		$('#catalog-items-wrapper').html(html);

		itemsTable = $('#catalog-items-table').DataTable({
			pageLength: 25,
			order: [[1, 'asc']]
		});
	}

	function loadItems(groupId) {
		$('#catalog-items-wrapper').html('<p class="text-muted p-2">Loading items…</p>');

		$.getJSON('{{url("/catalog/browse/items")}}/' + groupId, { vessel_id: vesselId }, function (items) {
			renderItemsTable(items, 'No items in this category.');
		}).fail(function () {
			$('#catalog-items-wrapper').html('<p class="text-danger p-2">Could not load items. Please try again.</p>');
		});
	}

	function loadCategoryItems() {
		$('#catalog-items-wrapper').html('<p class="text-muted p-2">Loading items…</p>');

		$.getJSON('{{url("/catalog/browse/category-items")}}/' + categoryId, { vessel_id: vesselId }, function (items) {
			renderItemsTable(items, vesselId ? 'No items in this category for this vessel.' : 'No items in this category.');
		}).fail(function () {
			$('#catalog-items-wrapper').html('<p class="text-danger p-2">Could not load items. Please try again.</p>');
		});
	}

	// Inline stock correction (Master only - the server enforces that too).
	// Saves on blur rather than per keystroke, and only when the figure
	// actually changed, so tabbing through the table doesn't fire writes.
	$(document).on('change blur', '.stock-input', function () {
		var input = $(this);
		var newValue = input.val();
		var itemId = input.closest('.stock-cell').data('item-id');
		var figure = input.data('figure');

		if (newValue === '' || newValue === input.data('original')) {
			return;
		}

		input.prop('disabled', true);

		// Only the figure this box owns is sent - the server leaves every
		// other one untouched, so editing In Stock never disturbs the
		// Opening Stock sitting next to it.
		var payload = {
			_token: $('meta[name="csrf-token"]').attr('content'),
			item_id: itemId
		};
		payload[figure] = newValue;

		$.ajax({
			url: '{{ route("stock.update") }}',
			method: 'POST',
			data: payload
		}).done(function (response) {
			var saved = response[figure];
			input.data('original', String(saved));
			input.val(saved);
			input.removeClass('is-invalid').addClass('is-valid');
			// The low-stock badge lives on the In Stock cell only.
			input.closest('tr').find('.badge').toggle(!!response.low);
		}).fail(function (xhr) {
			// Put the previous figure back - a stock number that silently
			// failed to save is worse than one that visibly didn't change.
			input.val(input.data('original'));
			input.removeClass('is-valid').addClass('is-invalid');
			var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Could not update stock.';
			if (typeof swal === 'function') { swal('Not saved', msg, 'error'); } else { alert(msg); }
		}).always(function () {
			input.prop('disabled', false);
		});
	});

	$(document).on('click', '.catalog-group-link', function (e) {
		e.preventDefault();
		path.push({ id: $(this).data('id'), name: $(this).data('name') });
		renderBreadcrumb();
		loadGroups($(this).data('id'));
	});

	$(document).on('click', '#catalog-breadcrumb li[data-index]', function () {
		var index = $(this).data('index');
		path = path.slice(0, index + 1);
		renderBreadcrumb();
		loadGroups(path[path.length - 1].id);
	});

	$(document).on('keyup', '#catalog-group-filter', function () {
		var term = $(this).val().toLowerCase();
		$('#catalog-groups .catalog-group-link').each(function () {
			var name = $(this).data('name').toLowerCase();
			$(this).toggle(name.indexOf(term) !== -1);
		});
	});

	$('#catalog-vessel').on('change', function () {
		vesselId = $(this).val();
		var vesselName = $(this).children('option:selected').text();
		$('.pptitle').text('Browse Catalog' + (vesselId ? ' — ' + vesselName : ''));

		// Already drilled into a category - the counts and item lists below
		// are all vessel-scoped, so switching vessels starts that category
		// over from its root rather than leaving stale, wrongly-scoped
		// groups on screen.
		if (categoryId) {
			path = [{ id: '', name: $('#catalog-category').children('option:selected').text() }];
			renderBreadcrumb();
			loadGroups('');
		}
	});

	$('#catalog-category').on('change', function () {
		categoryId = $(this).val();
		var categoryName = $(this).children('option:selected').text();

		if (!categoryId) {
			$('#catalog-tree-wrapper').hide();
			path = [];
			renderBreadcrumb();
			return;
		}

		$('#catalog-tree-wrapper').show();
		path = [{ id: '', name: categoryName }];
		renderBreadcrumb();
		loadGroups('');
	});
});
</script>
@endsection
