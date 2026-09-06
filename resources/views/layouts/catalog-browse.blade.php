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

		$.getJSON('{{url("/catalog/browse/children")}}/' + (parentId || ''), { category_id: categoryId }, function (groups) {
			if (groups.length === 0) {
				$('#catalog-groups').html('<p class="text-muted p-3">No sub-categories here — showing its items on the right. &rarr;</p>');
				loadItems(parentId);
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

	function loadItems(groupId) {
		$('#catalog-items-wrapper').html('<p class="text-muted p-2">Loading items…</p>');

		$.getJSON('{{url("/catalog/browse/items")}}/' + groupId, function (items) {
			if (items.length === 0) {
				$('#catalog-items-wrapper').html('<p class="text-muted">No items in this category.</p>');
				return;
			}

			// Stock is only ever meaningful for a ship user looking at their own
			// vessel - the API only sends it in that case.
			var hasStock = items.length > 0 && items[0].stock_qty !== undefined;
			var canEditStock = hasStock && items[0].can_edit_stock;

			var html = '<div class="table-responsive"><table id="catalog-items-table" class="table table-sm table-bordered" style="width:100%;">'
				+ '<thead><tr><th>Article #</th><th>Name</th><th>Unit</th>'
				+ (hasStock ? '<th>Opening Stock</th><th>In Stock</th>' : '')
				+ '<th>Account #</th><th>Description</th>'
				+ '<th>Part #</th><th>Drawing #</th><th>HS Code</th><th>Manufacturer</th><th>Vessels</th></tr></thead><tbody>';
			items.forEach(function (i) {
				var vessels = (i.vessels || []).map(function (v) { return esc(v.name); }).join(', ') || '<span class="text-muted">none</span>';
				html += '<tr>'
					+ '<td>' + esc(i.article_number) + '</td>'
					+ '<td>' + esc(i.name) + '</td>'
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
					+ '<td>' + vessels + '</td>'
					+ '</tr>';
			});
			html += '</tbody></table></div>';

			if (itemsTable) { itemsTable.destroy(); itemsTable = null; }
			$('#catalog-items-wrapper').html(html);

			itemsTable = $('#catalog-items-table').DataTable({
				pageLength: 25,
				order: [[1, 'asc']]
			});
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
