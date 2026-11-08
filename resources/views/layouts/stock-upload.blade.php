@extends('layouts.admin-master')
@section('main-content')
<div class="col-lg-6 col-xl-12">
	<div class="card">
		<div class="card-header pv-card-hader">
			<strong class="pptitle">Update Stock &mdash; {{ $vessel->name ?? '' }}</strong>
			<div class="right-buttons">
				<a href="{{ route('stock.history') }}" class="btn btn-sm btn-info"><i class="fas fa-history"></i> Upload History</a>
			</div>
		</div>
		<div class="card-body">
			@if(session('message'))
			<div class="alert alert-info">{{ session('message') }}</div>
			@endif

			@if($errors->any())
			<div class="alert alert-danger">
				<ul class="mb-0">
					@foreach($errors->all() as $error)
					<li>{{ $error }}</li>
					@endforeach
				</ul>
			</div>
			@endif

			<ul class="nav nav-tabs mb-3" role="tablist">
				<li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#su-tab-sheet" role="tab"><i class="fas fa-upload"></i> Upload Sheet</a></li>
				<li class="nav-item"><a class="nav-link" data-toggle="tab" href="#su-tab-manual" role="tab"><i class="fas fa-edit"></i> Enter Manually</a></li>
			</ul>

			<div class="tab-content">
				<div class="tab-pane fade show active" id="su-tab-sheet" role="tabpanel">
					<div class="row">
						<div class="col-md-6 border-right">
							<h6 class="mb-2">Step 1 &mdash; Download the sheet</h6>
							<p class="text-muted small">
								Pick a category and download the sheet. It comes pre-filled with every item in your
								vessel's catalog for that category, along with the stock figure currently on record.
							</p>
							<form action="{{ route('stock.template') }}" method="GET">
								<div class="form-group">
									<label>Category</label>
									<select name="category_id" class="form-control" required>
										<option value="">Select category</option>
										@foreach($categories as $category)
										<option value="{{$category->id}}">{{$category->name}}</option>
										@endforeach
									</select>
								</div>
								<button type="submit" class="btn btn-sm btn-info"><i class="fas fa-download"></i> Download Sheet</button>
							</form>
						</div>

						<div class="col-md-6">
							<h6 class="mb-2">Step 2 &mdash; Fill in and upload</h6>
							<p class="text-muted small">
								Enter what is physically on board in the <b>Stock Qty</b> column. Use
								<b>Opening Stock</b> for the vessel's declared starting balance &mdash; it is a
								separate figure that stays put as stock moves, and it is what prints in the
								Opening Stock column of a requisition. <b>Minimum Qty</b> is optional and warns
								you when an item runs low.
								<b>Any cell you leave blank is not changed</b> &mdash; each column is judged on its
								own, so you can fill in only what you have counted and upload the sheet as many
								times as you like.
							</p>
							<form action="{{ route('stock.upload.store') }}" method="POST" enctype="multipart/form-data">
								@csrf
								<div class="form-group">
									<label>Category <span class="text-muted small">(optional, for your own records)</span></label>
									<select name="category_id" class="form-control">
										<option value="">Not specified</option>
										@foreach($categories as $category)
										<option value="{{$category->id}}">{{$category->name}}</option>
										@endforeach
									</select>
								</div>
								<div class="form-group">
									<label>Filled-in sheet (.xlsx, .xls, .csv)</label>
									<input type="file" name="stock_file" class="form-control-file" accept=".xlsx,.xls,.csv" required>
								</div>
								<button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-upload"></i> Upload Stock</button>
							</form>
						</div>
					</div>
				</div>

				<div class="tab-pane fade" id="su-tab-manual" role="tabpanel">
					<p class="text-muted small">
						For a handful of items, search your vessel's catalog below and type the figures
						straight in instead of filling in a spreadsheet. Same rules as the upload: a
						blank box is left unchanged, and <b>Same as Opening</b> fills Current Stock from
						Opening Stock as you type it &mdash; handy the first time you set up an item, when
						both figures start out equal.
					</p>
					<div class="form-group" style="max-width:480px; position:relative;">
						<label for="manual-item-search">Search items in your vessel's catalog</label>
						<input type="text" id="manual-item-search" class="form-control" placeholder="Item name or article number…" autocomplete="off">
						<div id="manual-item-results" class="list-group" style="position:absolute; z-index:20; width:100%; max-height:280px; overflow:auto; display:none;"></div>
					</div>

					<div class="table-responsive">
						<table class="table table-sm table-bordered" id="manual-entry-table" style="display:none;">
							<thead>
								<tr>
									<th>Item</th>
									<th>Article #</th>
									<th>Unit</th>
									<th style="width:110px;">Opening Stock</th>
									<th style="width:110px;">Current Stock</th>
									<th style="width:90px;">Same as<br>Opening</th>
									<th style="width:100px;">Min Qty</th>
									<th></th>
								</tr>
							</thead>
							<tbody id="manual-entry-rows"></tbody>
						</table>
						<p class="text-muted small" id="manual-entry-empty">No items added yet &mdash; search above and pick some.</p>
					</div>

					<button type="button" id="manual-entry-save" class="btn btn-sm btn-primary" disabled><i class="fas fa-save"></i> Save</button>
					<span class="text-muted small ml-2">Up to 200 items per save.</span>
				</div>
			</div>

			<hr>
			<p class="text-muted small mb-0">
				Stock is also topped up automatically whenever the officer who raised a delivered
				requisition confirms its receipt &mdash; you only need this screen for opening balances and corrections.
			</p>
		</div>
	</div>
</div>
@endsection

@section('home-js')
<script>
$(function () {
	var staged = {}; // item_id -> {id, name, article_number, unit, opening_stock, stock_qty, min_qty}

	function escapeHtml(text) {
		return $('<div>').text(text === null || text === undefined ? '' : text).html();
	}

	function figureOrBlank(v) {
		return (v === null || v === undefined) ? '' : v;
	}

	function rowHtml(item) {
		return '<tr data-item-id="' + item.id + '">'
			+ '<td>' + escapeHtml(item.name) + '</td>'
			+ '<td>' + escapeHtml(item.article_number || '—') + '</td>'
			+ '<td>' + escapeHtml(item.unit) + '</td>'
			+ '<td><input type="number" min="0" step="1" class="form-control form-control-sm me-opening" value="' + figureOrBlank(item.opening_stock) + '" placeholder="' + figureOrBlank(item.opening_stock) + '"></td>'
			+ '<td><input type="number" min="0" step="1" class="form-control form-control-sm me-stock" value="' + figureOrBlank(item.stock_qty) + '" placeholder="' + figureOrBlank(item.stock_qty) + '"></td>'
			+ '<td class="text-center"><input type="checkbox" class="me-same-as-opening"></td>'
			+ '<td><input type="number" min="0" step="1" class="form-control form-control-sm me-min" value="' + figureOrBlank(item.min_qty) + '" placeholder="' + figureOrBlank(item.min_qty) + '"></td>'
			+ '<td><button type="button" class="btn btn-sm btn-outline-danger me-remove"><i class="fas fa-trash-alt"></i></button></td>'
			+ '</tr>';
	}

	function refreshTableVisibility() {
		var count = Object.keys(staged).length;
		$('#manual-entry-table').toggle(count > 0);
		$('#manual-entry-empty').toggle(count === 0);
		$('#manual-entry-save').prop('disabled', count === 0);
	}

	var searchTimer = null;
	$('#manual-item-search').on('keyup', function () {
		var term = $(this).val();
		clearTimeout(searchTimer);

		if (term.trim() === '') {
			$('#manual-item-results').hide().empty();
			return;
		}

		searchTimer = setTimeout(function () {
			$.getJSON('{{ route('stock.manual.search-items') }}', { q: term }, function (items) {
				if (!items.length) {
					$('#manual-item-results').html('<span class="list-group-item">No matching items.</span>').show();
					return;
				}
				$('#manual-item-results').html(items.map(function (i) {
					var already = staged[i.id] ? ' <span class="badge badge-secondary">added</span>' : '';
					return '<a class="list-group-item list-group-item-action manual-item-pick" data-item=\'' + JSON.stringify(i).replace(/'/g, '&#39;') + '\'>'
						+ escapeHtml(i.name) + ' <span class="text-muted">(' + escapeHtml(i.article_number || '—') + ')</span>' + already + '</a>';
				}).join('')).show();
			});
		}, 250);
	});

	$(document).on('click', '.manual-item-pick', function () {
		var item = $(this).data('item');
		if (staged[item.id]) {
			// Already added - just reveal the existing row rather than duplicate it.
			$('#manual-item-results').hide();
			$('#manual-item-search').val('');
			return;
		}

		staged[item.id] = item;
		$('#manual-entry-rows').append(rowHtml(item));
		$('#manual-item-results').hide();
		$('#manual-item-search').val('');
		refreshTableVisibility();
	});

	$(document).on('click', function (e) {
		if (!$(e.target).closest('#manual-item-search, #manual-item-results').length) {
			$('#manual-item-results').hide();
		}
	});

	$(document).on('click', '.me-remove', function () {
		var $row = $(this).closest('tr');
		delete staged[$row.data('item-id')];
		$row.remove();
		refreshTableVisibility();
	});

	// "Same as Opening": mirrors Opening Stock into Current Stock live while
	// checked, and lets Current Stock be typed independently again once
	// unchecked - it never touches what's already saved, only what you're
	// about to submit.
	$(document).on('change', '.me-same-as-opening', function () {
		var $row = $(this).closest('tr');
		var checked = $(this).is(':checked');
		$row.find('.me-stock').prop('disabled', checked);
		if (checked) {
			$row.find('.me-stock').val($row.find('.me-opening').val());
		}
	});
	$(document).on('input', '.me-opening', function () {
		var $row = $(this).closest('tr');
		if ($row.find('.me-same-as-opening').is(':checked')) {
			$row.find('.me-stock').val($(this).val());
		}
	});

	$('#manual-entry-save').on('click', function () {
		var $btn = $(this).prop('disabled', true);
		var rows = [];
		$('#manual-entry-rows tr').each(function () {
			var $row = $(this);
			rows.push({
				item_id: $row.data('item-id'),
				opening_stock: $row.find('.me-opening').val(),
				stock_qty: $row.find('.me-stock').val(),
				min_qty: $row.find('.me-min').val(),
			});
		});

		$.ajax({
			url: '{{ route('stock.manual.store') }}',
			method: 'POST',
			data: { _token: $('meta[name="csrf-token"]').attr('content'), rows: rows },
		}).done(function (response) {
			window.location = response.redirect || '{{ route('stock.history') }}';
		}).fail(function (xhr) {
			$btn.prop('disabled', false);
			var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Could not save those figures.';
			if (typeof swal === 'function') { swal('Not saved', msg, 'error'); } else { alert(msg); }
		});
	});
});
</script>
@endsection
