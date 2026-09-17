@extends('layouts.admin-master')
@section('main-content')
<style>
	/* ---- Attachments column: chips on a saved line, an Add trigger, a
		 quiet hint on a line that hasn't been saved yet. ---- */
	.attachments-cell{ display:flex; flex-wrap:wrap; align-items:center; gap:6px; min-width:150px; }
	.att-chip{
		display:inline-flex; align-items:center; gap:5px; border-radius:7px; padding:4px 6px 4px 5px;
		font-size:11.5px; font-weight:600; white-space:nowrap;
	}
	.att-chip .ico{
		width:17px; height:17px; border-radius:4px; display:flex; align-items:center; justify-content:center;
		font-size:8.5px; font-weight:800; color:#fff; flex-shrink:0;
	}
	.att-chip.image{ background:#e6f0fe; color:#2f6fed; } .att-chip.image .ico{ background:#2f6fed; }
	.att-chip.pdf{ background:#fde9ec; color:#e5486b; } .att-chip.pdf .ico{ background:#e5486b; }
	.att-chip.doc{ background:#eceafd; color:#5b4fd6; } .att-chip.doc .ico{ background:#5b4fd6; }
	.att-chip-title{ max-width:90px; overflow:hidden; text-overflow:ellipsis; cursor:pointer; }
	.att-chip-remove{
		appearance:none; border:none; background:none; font:inherit; line-height:1; cursor:pointer;
		color:inherit; opacity:.55; padding:0 0 0 2px; font-size:13px;
	}
	.att-chip-remove:hover{ opacity:1; }
	.add-att-btn{
		display:inline-flex; align-items:center; gap:5px; font-size:11.5px; font-weight:600;
		color:#005866; background:#e3f2f4; border:1px dashed rgba(0,113,132,.4);
		border-radius:7px; padding:5px 8px 5px 6px; cursor:pointer;
	}
	.add-att-btn:hover{ background:#d3ecee; }
	.add-att-btn svg{ width:11px; height:11px; }

	/* ---- Add Attachment modal ---- */
	#attach-modal .item-tag{ font-size:11.5px; color:#6b7a82; font-weight:600; margin-bottom:2px; }
	#attach-modal .tabs{ display:flex; gap:2px; padding:0 1.5rem; border-bottom:1px solid #edf1f0; }
	#attach-modal .tab-btn{
		appearance:none; border:none; background:none; font:inherit; cursor:pointer;
		font-size:13.5px; font-weight:600; color:#6b7a82; padding:9px 4px 12px; margin-right:22px;
		border-bottom:2px solid transparent;
	}
	#attach-modal .tab-btn.active{ color:#005866; border-bottom-color:#007184; }
	#attach-modal .tab-panel{ display:none; }
	#attach-modal .tab-panel.active{ display:block; }
	#attach-modal .dropzone{
		border:1.5px dashed #e3e8e7; border-radius:10px; padding:26px 18px; text-align:center;
		background:#f4f6f6; transition:border-color .15s, background .15s; cursor:pointer;
	}
	#attach-modal .dropzone.drag{ border-color:#007184; background:#e3f2f4; }
	#attach-modal .dropzone svg{ width:26px; height:26px; color:#6b7a82; margin-bottom:8px; }
	#attach-modal .dropzone p{ margin:0 0 3px; font-size:13.5px; font-weight:600; color:#3d4b52; }
	#attach-modal .dropzone span{ font-size:12px; color:#6b7a82; }
	#attach-modal .browse-link{ color:#005866; font-weight:700; text-decoration:underline; }
	#attach-modal .picked-file{ font-size:12.5px; color:#3d4b52; margin-top:10px; }
	#attach-modal .search-row{ margin-bottom:12px; }
	#attach-modal .search-row input{ font-size:13px; }
	#attach-modal .file-grid{ display:grid; grid-template-columns:repeat(3,1fr); gap:9px; max-height:260px; overflow-y:auto; padding-right:2px; }
	#attach-modal .file-card{
		border:1.5px solid #e3e8e7; border-radius:9px; padding:9px; cursor:pointer; text-align:left;
		background:#fff; position:relative;
	}
	#attach-modal .file-card:hover{ border-color:#7fb8c2; }
	#attach-modal .file-card.selected{ border-color:#007184; background:#e3f2f4; }
	#attach-modal .file-thumb{
		height:44px; border-radius:6px; display:flex; align-items:center; justify-content:center;
		font-size:9px; font-weight:800; color:#fff; margin-bottom:7px;
	}
	#attach-modal .file-card .fname{ font-size:11.5px; font-weight:600; color:#17242b; line-height:1.3; }
	#attach-modal .file-card .fmeta{ font-size:10px; color:#6b7a82; margin-top:2px; }
	#attach-modal .file-check{
		position:absolute; top:6px; right:6px; width:15px; height:15px; border-radius:4px;
		border:1.5px solid #e3e8e7; background:#fff; display:flex; align-items:center; justify-content:center;
	}
	#attach-modal .file-card.selected .file-check{ background:#007184; border-color:#007184; color:#fff; }
	#attach-modal .file-check svg{ width:9px; height:9px; display:none; }
	#attach-modal .file-card.selected .file-check svg{ display:block; }
	#attach-modal .file-delete{
		position:absolute; top:5px; left:5px; width:17px; height:17px; border-radius:4px;
		border:none; background:rgba(197,48,48,.08); color:#c0392b; display:flex; align-items:center;
		justify-content:center; cursor:pointer; opacity:0; transition:opacity .1s;
	}
	#attach-modal .file-card:hover .file-delete{ opacity:1; }
	#attach-modal .file-delete:hover{ background:#c0392b; color:#fff; }
	#attach-modal .file-delete svg{ width:9px; height:9px; }
	#attach-modal .empty-files{ font-size:13px; color:#6b7a82; padding:20px 0; text-align:center; }
</style>
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
								{{-- Pre-select the category already saved on this draft. Without
									 it, returning to step 2 reset the dropdown to blank and the
									 required rule then blocked Save & Next ("Please select an item
									 in the list") even when nothing had been changed. --}}
								@php $selectedCategory = old('Category_Name', $order->category_id); @endphp
								<select class="form-control Category_Name" id="cate_name" name="Category_Name" required>
									<option value="" {{ $selectedCategory ? '' : 'selected' }} class='cat_opt'>-- Choose Category --</option>
									@foreach($categories as $category)
									<option value="{{$category->id}}" class='cat_opt' data-catalog="{{$category->is_catalog ? 1 : 0}}" {{ (string) $selectedCategory === (string) $category->id ? 'selected' : '' }}>{{$category->name}}</option>
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
								{{-- This table has 12 columns once Attachments is in the mix -
									 wide enough that forcing it to 100% squeezed every header
									 into an unreadable wrap ("Quantit/y of Last/Supply") and
									 pushed the whole page wider than the viewport. Let it take
									 the width its content actually needs and scroll horizontally
									 inside this box instead - never the page itself. --}}
								<div class="table-responsive">
									<table id="example1" class="table table-striped table-bordered orderedItemTable" style="width:auto; min-width:100%;">
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
												<th>Attachments</th>
												<th>Office Use</th>
												<th class="action">Action</th>
											</tr>
										</thead>
										<tbody>
											{{-- Lines already saved on this draft. Rendered server-side so
												 DataTables picks them up on init, and carrying the same row
												 id and hidden-input shape the picker's JS produces, so
												 removing or re-adding afterwards behaves identically whether
												 a row came from here or from the modal. --}}
											@foreach($order->orderItems as $orderItem)
											{{-- data-saved marks a line that already exists on the draft, so
												 the delete icon knows to remove it server-side rather than
												 only from the page. --}}
											<tr id="row_ordered_item-{{ $orderItem->item_id }}" data-saved="1" data-item-id="{{ $orderItem->item_id }}">
												<td><b class="serial">{{ $loop->iteration }}</b></td>
												<td>{{ $orderItem->item->name ?? '' }}<input type="hidden" name="item_id[]" value="{{ $orderItem->item_id }}"></td>
												<td>{{ $orderItem->item->article_number ?? '' }}</td>
												<td>{{ $orderItem->item->unit ?? '' }}</td>
												<td>{{ $orderItem->opening_stock ?? '' }}</td>
												<td>{{ $orderItem->last_supply_qty ?? '' }}</td>
												<td>{{ $orderItem->last_supply_date ? \Carbon\Carbon::parse($orderItem->last_supply_date)->format('d M Y') : '' }}</td>
												<td>{{ $liveStock[$orderItem->item_id]['stock_qty'] ?? '' }}</td>
												{{-- Editable, so a quantity can be corrected without deleting the
													 line and adding it back. One visible input replaces the old
													 span + hidden pair: it still submits as item_qty[] in row
													 order alongside item_id[]. --}}
												<td><input type="number" min="1" class="form-control form-control-sm required-qty" name="item_qty[]" style="width:80px;" value="{{ $orderItem->item_qty }}" data-original="{{ $orderItem->item_qty }}" required></td>
												{{-- Hidden attachment_ids[itemId][] inputs mirror the visible chips
													 exactly - kept in sync by renderAttachmentsCell() on every
													 add/remove. Save & Next resubmits this whole draft's items
													 (see storeStep2's delete-and-recreate), which would otherwise
													 cascade-delete every item's attachment links on ANY save, not
													 just the row that changed - these inputs are what lets
													 storeStep2() re-link exactly what was already here. --}}
												<td class="attachments-cell" data-item-id="{{ $orderItem->item_id }}">
													@foreach($orderItem->attachments as $att)
													<span class="att-chip {{ $att->kind }}" data-id="{{ $att->id }}" data-view-url="{{ url('/attachments/'.$att->id.'/view') }}" title="{{ $att->title }}">
														<span class="ico">{{ strtoupper($att->kind === 'image' ? 'img' : $att->kind) }}</span><span class="att-chip-title">{{ \Illuminate\Support\Str::limit($att->title, 16) }}</span>
														<button type="button" class="att-chip-remove" data-id="{{ $att->id }}" title="Remove">&times;</button>
														<input type="hidden" name="attachment_ids[{{ $orderItem->item_id }}][]" value="{{ $att->id }}">
													</span>
													@endforeach
													<button type="button" class="add-att-btn" data-item-id="{{ $orderItem->item_id }}" data-item-name="{{ $orderItem->item->name ?? '' }}">
														<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M12 5v14M5 12h14"/></svg>Add
													</button>
												</td>
												<td></td>
												<td><button type="button" class="btn btn-danger btn-sm delete-order-item-row"><i class="fas fa-trash-alt"></i></button></td>
											</tr>
											@endforeach
										</tbody>
									</table>
								</div>
							</div>
						</div>
						<div class="form-group row">
							<div class="col-md-12 text-right">
									{{-- Back to step 1 for THIS draft, not to a blank new-requisition
										 form - that would strand this draft and its items. --}}
									<a href="{{ route('requisition.step1.edit', $order) }}" class="btn btn-srd-outline"><i class="fas fa-arrow-left"></i> Back</a>
									<button type="submit" class="btn btn-success">Save &amp; Next: Review <i class="fas fa-arrow-right"></i></button>
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

<!-- Add Attachment modal: upload a new file, or reuse one already in the
	 officer's personal library. Reused for whichever row's "Add" button was
	 clicked - see openAttachModal() below. -->
<div class="modal fade" id="attach-modal" tabindex="-1" role="dialog">
	<div class="modal-dialog" role="document" style="max-width:600px;">
		<div class="modal-content">
			<div class="modal-header" style="display:block; padding-bottom:0;">
				<button type="button" class="close" data-dismiss="modal" style="position:absolute; right:1.2rem; top:1rem;"><span>&times;</span></button>
				<div class="item-tag">Attaching to</div>
				<h5 class="modal-title" id="attach-modal-item-name">&nbsp;</h5>
				<div class="tabs" style="margin:0 -1.5rem;">
					<button type="button" class="tab-btn active" id="attach-tab-upload-btn">Upload new</button>
					<button type="button" class="tab-btn" id="attach-tab-files-btn">Choose from my files</button>
				</div>
			</div>
			<div class="modal-body">
				<div class="tab-panel active" id="attach-tab-upload">
					<div class="dropzone" id="attach-dropzone">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 15V3m0 0L7 8m5-5l5 5"/><path d="M4 15v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/></svg>
						<p>Drop an image, PDF or document here</p>
						<span>or <span class="browse-link">browse your device</span> &middot; up to 15MB</span>
						{{-- position:absolute + 1px, not display:none - some browsers refuse
							 to open the file picker on a programmatic click if the input
							 was never actually rendered. --}}
						<input type="file" id="attach-file-input" style="position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); border:0;" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt">
						<div class="picked-file" id="attach-picked-file" style="display:none;"></div>
					</div>
					<div class="form-group mt-3 mb-0">
						<label for="attach-title" style="font-size:12.5px; font-weight:700; color:#3d4b52;">Title</label>
						<input type="text" class="form-control" id="attach-title" placeholder="What is this file?">
						<small class="form-text text-muted">Shown to every approver next to the item &mdash; keep it short and specific.</small>
					</div>
				</div>
				<div class="tab-panel" id="attach-tab-files">
					<div class="search-row">
						<input type="text" class="form-control" id="attach-files-search" placeholder="Search your files&hellip;">
					</div>
					<div class="file-grid" id="attach-files-grid"></div>
				</div>
			</div>
			<div class="modal-footer">
				<span class="text-muted mr-auto" style="font-size:12.5px; font-weight:600;" id="attach-sel-count"></span>
				<button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
				<button type="button" class="btn btn-primary" id="attach-submit-btn">Upload &amp; attach</button>
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
		// Sorting by any column made no sense here anyway (SL NO is just the
		// order items were added in, not a stable id to sort by) - and with
		// 12 columns already tight for space, cramming a sort-icon glyph into
		// every header on top of that was the other half of why they were
		// wrapping unreadably ("Quantit / y of Last / Supply").
		ordering: false,
		dom: 'frt',
	});

	// Persistence for this page is server-side per draft (Save & Next), so the
	// draft's own saved lines - rendered into the table above - are the only
	// rows that belong here.
	//
	// dataForm.js runs first and appends anything still sitting in the old
	// single-page localStorage row cache straight into this table, which would
	// either leak another draft's items in or duplicate the ones just
	// rendered. Drop the cache, then drop any row it managed to add: the
	// server's list is authoritative.
	var savedRowIds = {!! json_encode($order->orderItems->pluck('item_id')->map(fn ($id) => 'row_ordered_item-'.$id)->all()) !!};

	localStorage.removeItem('orderItemRows');
	localStorage.removeItem('orderInfo');

	pageOrderTable.rows(function (idx, data, node) {
		return savedRowIds.indexOf(node.id) === -1;
	}).remove().draw();

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

		return '<div class="mb-2" style="font-size:14px;">'
			+ '<span class="badge ' + badgeClass + '" style="font-size:13px;padding:6px 10px;">In stock: ' + esc(String(item.stock_qty)) + ' ' + esc(item.unit) + '</span> '
			+ '<span class="text-muted">' + lastSupply + '</span>'
			+ '</div>';
	}

	function itemCardHtml(item) {
		var existingQty = staged[item.id] ? staged[item.id].qty : 1;
		// Stock figures ride along as data attributes so the "Add" click below
		// can carry them into the staged item, and from there into the table
		// row - without this, In Stock stayed blank for anything added through
		// the picker even though the card right above it showed the figure.
		return '<div class="item-picker-card" data-id="' + item.id + '" data-name="' + esc(item.name) + '" data-unit="' + esc(item.unit) + '" data-article="' + esc(item.article_number) + '" '
			+ 'data-stock-qty="' + (item.stock_qty === undefined ? '' : item.stock_qty) + '" '
			+ 'data-opening-stock="' + (item.opening_stock === undefined || item.opening_stock === null ? '' : item.opening_stock) + '" '
			+ 'data-last-supply-qty="' + (item.last_supply_qty === undefined || item.last_supply_qty === null ? '' : item.last_supply_qty) + '" '
			+ 'data-last-supply-date="' + (item.last_supply_date === undefined || item.last_supply_date === null ? '' : esc(item.last_supply_date)) + '" '
			+ 'style="border:1px solid #dee2e6;border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:16px;margin-bottom:14px;background:#fff;">'
			+ '<h5 class="mb-2" style="font-weight:600;">' + esc(item.name) + '</h5>'
			+ '<div class="row mb-2" style="font-size:14px;color:#6c757d;">'
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

		staged[id] = {
			id: id, name: card.data('name'), unit: card.data('unit'), article_number: card.data('article'), qty: qty,
			stockQty: card.data('stock-qty'), openingStock: card.data('opening-stock'),
			lastSupplyQty: card.data('last-supply-qty'), lastSupplyDate: card.data('last-supply-date')
		};
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
			var orBlank = function (value) {
				return (value === undefined || value === null || value === '') ? '' : esc(String(value));
			};
			// Column order matches the paper-form layout (SL NO / Item Name /
			// IMPA Code / Unit / Opening Stock / Qty of Last Supply / Date of
			// Last Supply / In Stock / Required / Attachments / Office Use /
			// Action). The four stock figures come from the same data the
			// picker card showed - carried onto the card as data attributes,
			// then into `staged` on Add (see itemCardHtml / .item-picker-add).
			// Article Number stands in for IMPA Code since imported items
			// always carry the '-' placeholder for the latter.
			//
			// A staged row's own order_items id doesn't exist until Save & Next,
			// so a file can't be LINKED yet - but it can still be UPLOADED right
			// now (see uploadToLibrary()), and carried forward as a hidden
			// attachment_ids[itemId][] input for storeStep2() to link once the
			// row is real. attachmentsCellHtml (defined below) builds the same
			// chip+hidden-input markup this cell will be re-rendered with.
			var stagedRow = pageOrderTable.row.add([
				'<b class="serial">' + idx + '</b>',
				esc(item.name) + '<input type="hidden" name="item_id[]" value="' + item.id + '">',
				esc(item.article_number),
				esc(item.unit),
				orBlank(item.openingStock),
				orBlank(item.lastSupplyQty),
				orBlank(item.lastSupplyDate),
				orBlank(item.stockQty),
				'<input type="number" min="1" class="form-control form-control-sm required-qty" name="item_qty[]" style="width:80px;" value="' + item.qty + '" data-original="' + item.qty + '" required>',
				attachmentsCellHtml(item.id, [], esc(item.name)),
				'',
				'<button type="button" class="btn btn-danger btn-sm delete-order-item-row"><i class="fas fa-trash-alt"></i></button>',
			]).draw().node();
			stagedRow.id = 'row_ordered_item-' + item.id;

			// row.add() builds the <td> wrappers itself, so the cell contents
			// above can't carry the class and data-item-id that
			// renderAttachmentsCell() looks the cell up by - without this,
			// attaching to a staged row silently wrote to nothing, because
			// '.attachments-cell[data-item-id=N]' matched only the
			// server-rendered rows that ship with that wrapper already on
			// them. Found via .add-att-btn rather than a column index so it
			// survives a column being inserted before this one.
			$(stagedRow).find('.add-att-btn').closest('td')
				.addClass('attachments-cell')
				.attr('data-item-id', item.id);
		});

		$('#item-picker-modal').modal('hide');
	});

	// Required quantity is editable in place. Rows already on the draft save
	// the change straight away so it survives a reload; rows staged in this
	// session just carry their value through to Save & Next.
	$(document).on('change blur', '.required-qty', function () {
		var input = $(this);
		var row = input.closest('tr');
		var value = input.val();

		if (!row.data('saved') || value === '' || value === String(input.data('original'))) {
			return;
		}

		if (parseInt(value, 10) < 1) {
			input.val(input.data('original'));
			return;
		}

		$.ajax({
			url: '{{ url("/requisition/".$order->id."/items") }}/' + row.data('item-id') + '/qty',
			method: 'POST',
			data: { _token: $('meta[name="csrf-token"]').attr('content'), item_qty: value }
		}).done(function (response) {
			input.data('original', String(response.item_qty));
			input.removeClass('is-invalid').addClass('is-valid');
		}).fail(function (xhr) {
			// Put the old figure back rather than leave the page showing a
			// quantity the requisition doesn't actually carry.
			input.val(input.data('original'));
			input.removeClass('is-valid').addClass('is-invalid');
			var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Could not update that quantity.';
			if (typeof swal === 'function') { swal('Not saved', msg, 'error'); } else { alert(msg); }
		});
	});

	$(document).on('click', '.delete-order-item-row', function () {
		var row = $(this).closest('tr');

		// Rows added in this session aren't on the draft yet, so there's
		// nothing to delete server-side - just drop them from the table.
		if (!row.data('saved')) {
			pageOrderTable.row(row).remove().draw();
			return;
		}

		// Lines already saved on the draft are removed for real, otherwise the
		// row reappears on the next page load and the delete looks like it
		// silently failed.
		var button = $(this).prop('disabled', true);

		$.ajax({
			url: '{{ url("/requisition/".$order->id."/items") }}/' + row.data('item-id') + '/remove',
			method: 'POST',
			data: { _token: $('meta[name="csrf-token"]').attr('content') }
		}).done(function () {
			pageOrderTable.row(row).remove().draw();
		}).fail(function (xhr) {
			button.prop('disabled', false);
			var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Could not remove that item.';
			if (typeof swal === 'function') { swal('Not removed', msg, 'error'); } else { alert(msg); }
		});
	});

	/* ---------- Category select: toggle catalog-modal vs legacy dropdown ---------- */

	$('select#cate_name').on('change', function () {
		var selected = $(this).children('option:selected');
		var isCatalog = selected.data('catalog') == 1;

		// Nothing chosen yet - neither Add control makes sense.
		if (!$(this).val()) {
			$('#catalog-add-wrapper').hide();
			$('#legacy-add-wrapper').hide();
			return;
		}

		if (isCatalog) {
			$('#catalog-add-wrapper').show();
			$('#legacy-add-wrapper').hide();
		} else {
			$('#catalog-add-wrapper').hide();
			$('#legacy-add-wrapper').show();
		}
	});

	// Returning to step 2 arrives with the draft's category already selected,
	// and a pre-selected <select> fires no change event - without this the
	// right "Add Item" control would stay hidden and no more items could be
	// added to an existing requisition.
	$('select#cate_name').trigger('change');

	/* ---------- Attachments: upload new, or reuse from "my files" ---------- */

	var attachItemId = null;   // which row's "Add" button opened the modal
	var attachItemName = '';
	var attachRowSaved = false;   // is that row already an order_items row on the server?
	var attachSelectedFiles = {}; // my-files tab: id -> {id, title, kind}, for the multi-select
	var attachPickedFile = null;  // upload tab: the File object staged for upload
	var attachTitleAutoFilled = true; // false once the officer actually types into Title
	var myFilesLoaded = false;

	function attachBaseUrl(itemId) {
		return '{{ url("/requisition/".$order->id."/items") }}/' + itemId + '/attachments';
	}
	function attachUrl(action) {
		return attachBaseUrl(attachItemId) + (action ? '/' + action : '');
	}
	function fileViewUrl(id) {
		return '{{ url("/attachments") }}/' + id + '/view';
	}

	// A hidden attachment_ids[itemId][] input rides along with every chip -
	// on a saved row it's a mirror of what the AJAX calls already did for
	// real; on an unsaved row it's the ONLY record of the link until Save &
	// Next creates the row and RequisitionController::storeStep2() reads it.
	function attChipHtml(itemId, a) {
		var icoLabel = a.kind === 'image' ? 'IMG' : a.kind.toUpperCase();
		return '<span class="att-chip ' + a.kind + '" data-id="' + a.id + '" data-view-url="' + a.view_url + '" title="' + esc(a.title) + '">'
			+ '<span class="ico">' + icoLabel + '</span>'
			+ '<span class="att-chip-title">' + esc(a.title) + '</span>'
			+ '<button type="button" class="att-chip-remove" data-id="' + a.id + '" title="Remove">&times;</button>'
			+ '<input type="hidden" name="attachment_ids[' + itemId + '][]" value="' + a.id + '">'
			+ '</span>';
	}

	function attachmentsCellHtml(itemId, attachments, itemName) {
		var html = attachments.map(function (a) { return attChipHtml(itemId, a); }).join('');
		html += '<button type="button" class="add-att-btn" data-item-id="' + itemId + '" data-item-name="' + esc(itemName) + '">'
			+ '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M12 5v14M5 12h14"/></svg>Add</button>';
		return html;
	}

	// Re-renders one row's whole Attachments cell - from a fresh server
	// payload on a saved row (so the page shows exactly what's actually
	// linked, not a guess), or from the cell's own current chips plus
	// whatever just changed on an unsaved one (there is no server copy yet).
	function renderAttachmentsCell(itemId, attachments, itemName) {
		$('.attachments-cell[data-item-id="' + itemId + '"]').html(attachmentsCellHtml(itemId, attachments, itemName));
	}

	// Reads what a cell's chips already show, straight from the DOM - the
	// only source of truth for an unsaved row, since nothing has a
	// server-side record yet.
	function currentAttachmentsFromCell(cell) {
		var kinds = ['image', 'pdf', 'doc'];
		return cell.find('.att-chip').map(function () {
			var el = $(this);
			var kind = kinds.filter(function (k) { return el.hasClass(k); })[0];
			return { id: el.data('id'), kind: kind, title: el.attr('title'), view_url: el.data('view-url') };
		}).get();
	}

	function resetAttachModal() {
		$('#attach-tab-upload-btn').addClass('active');
		$('#attach-tab-files-btn').removeClass('active');
		$('#attach-tab-upload').addClass('active');
		$('#attach-tab-files').removeClass('active');
		$('#attach-title').val('');
		$('#attach-file-input').val('');
		$('#attach-picked-file').hide().text('');
		$('#attach-dropzone').removeClass('drag');
		attachPickedFile = null;
		attachTitleAutoFilled = true;
		attachSelectedFiles = {};
		$('#attach-sel-count').text('');
		myFilesLoaded = false;
	}

	$(document).on('click', '.add-att-btn', function () {
		attachItemId = $(this).data('item-id');
		attachItemName = $(this).data('item-name');
		attachRowSaved = !!$(this).closest('tr').data('saved');
		resetAttachModal();
		$('#attach-modal-item-name').text(attachItemName);
		$('#attach-modal').modal('show');
	});

	$('#attach-tab-upload-btn').on('click', function () {
		$(this).addClass('active');
		$('#attach-tab-files-btn').removeClass('active');
		$('#attach-tab-upload').addClass('active');
		$('#attach-tab-files').removeClass('active');
	});

	$('#attach-tab-files-btn').on('click', function () {
		$(this).addClass('active');
		$('#attach-tab-upload-btn').removeClass('active');
		$('#attach-tab-files').addClass('active');
		$('#attach-tab-upload').removeClass('active');
		if (!myFilesLoaded) {
			loadMyFiles('');
		}
	});

	// Dropzone: click-to-browse, or drag-and-drop. Either way the filename
	// (minus its extension) becomes the default title - the officer can still
	// change it, but a title is required either way.
	//
	// The file input is INSIDE the dropzone, so a real click on it would
	// bubble straight back into this same handler - guard against re-opening
	// the picker on top of itself. And this calls the native .click() rather
	// than jQuery's .trigger('click'): jQuery dispatches through its own
	// event system first, which is one more layer between the user gesture
	// and the browser's file-picker permission check than is worth risking.
	$('#attach-dropzone').on('click', function (e) {
		if (e.target.id === 'attach-file-input') {
			return;
		}
		document.getElementById('attach-file-input').click();
	});
	$('#attach-dropzone').on('dragover', function (e) {
		e.preventDefault();
		$(this).addClass('drag');
	});
	$('#attach-dropzone').on('dragleave drop', function (e) {
		e.preventDefault();
		$(this).removeClass('drag');
	});
	$('#attach-dropzone').on('drop', function (e) {
		var files = e.originalEvent.dataTransfer.files;
		if (files && files.length) {
			pickAttachFile(files[0]);
		}
	});
	$('#attach-file-input').on('change', function () {
		if (this.files && this.files.length) {
			pickAttachFile(this.files[0]);
		}
	});

	function pickAttachFile(file) {
		attachPickedFile = file;
		$('#attach-picked-file').show().text(file.name + ' (' + Math.round(file.size / 1024) + ' KB)');
		// Re-derives the title from whichever file is picked NOW, as long as
		// the officer hasn't typed a title of their own - .val() alone can't
		// tell "still showing the last auto-fill" apart from "the officer
		// deliberately kept this exact text", so picking a second file used
		// to leave the first file's title behind instead of updating it.
		if (attachTitleAutoFilled) {
			$('#attach-title').val(file.name.replace(/\.[^.]+$/, ''));
		}
	}

	// A real user keystroke is the only thing that should ever mark the title
	// as "theirs" - setting .val() programmatically (as pickAttachFile does)
	// never fires 'input', so this can't misfire from our own auto-fill.
	$('#attach-title').on('input', function () {
		attachTitleAutoFilled = false;
	});

	var myFilesSearchTimer = null;
	$('#attach-files-search').on('keyup', function () {
		var term = $(this).val();
		clearTimeout(myFilesSearchTimer);
		myFilesSearchTimer = setTimeout(function () { loadMyFiles(term); }, 250);
	});

	function fileThumbHtml(kind) {
		var label = kind === 'image' ? 'IMG' : kind.toUpperCase();
		var bg = kind === 'image' ? '#2f6fed' : (kind === 'pdf' ? '#e5486b' : '#5b4fd6');
		return '<div class="file-thumb" style="background:' + bg + ';">' + label + '</div>';
	}

	function loadMyFiles(term) {
		$('#attach-files-grid').html('<div class="empty-files">Loading&hellip;</div>');
		$.getJSON('{{ route("attachments.my-files") }}', { q: term }, function (files) {
			myFilesLoaded = true;
			if (files.length === 0) {
				$('#attach-files-grid').html('<div class="empty-files">' + (term ? 'No files match that search.' : "You haven't uploaded any files yet.") + '</div>');
				return;
			}
			// A plain div, not a button: it needs to contain a real delete
			// <button> of its own, and a button can't nest inside a button.
			$('#attach-files-grid').html(files.map(function (f) {
				var checked = attachSelectedFiles[f.id] ? ' selected' : '';
				return '<div class="file-card' + checked + '" data-id="' + f.id + '" data-title="' + esc(f.title) + '" data-kind="' + f.kind + '" tabindex="0" role="button">'
					+ '<button type="button" class="file-delete" data-id="' + f.id + '" title="Delete this file"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M6 6l12 12M18 6L6 18"/></svg></button>'
					+ '<div class="file-check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 13l4 4L19 7"/></svg></div>'
					+ fileThumbHtml(f.kind)
					+ '<div class="fname">' + esc(f.title) + '</div>'
					+ '<div class="fmeta">' + esc(f.uploaded_at) + '</div>'
					+ '</div>';
			}).join(''));
		});
	}

	$(document).on('click', '#attach-files-grid .file-card', function () {
		var id = $(this).data('id');
		$(this).toggleClass('selected');
		if ($(this).hasClass('selected')) {
			attachSelectedFiles[id] = { id: id, title: $(this).data('title'), kind: $(this).data('kind'), view_url: fileViewUrl(id) };
		} else {
			delete attachSelectedFiles[id];
		}
		var n = Object.keys(attachSelectedFiles).length;
		$('#attach-sel-count').text(n ? (n + (n === 1 ? ' file selected' : ' files selected')) : '');
	});

	// Permanently removes a file from the officer's library - separate from
	// detaching it off one item. stopPropagation so this doesn't also toggle
	// the card's own selection (it's nested inside that same card).
	$(document).on('click', '.file-delete', function (e) {
		e.stopPropagation();
		var button = $(this);
		var card = button.closest('.file-card');
		var id = button.data('id');

		function reallyDelete() {
			button.prop('disabled', true);
			$.ajax({
				url: '{{ url("/attachments") }}/' + id + '/delete',
				method: 'POST',
				data: { _token: $('meta[name="csrf-token"]').attr('content') },
			}).done(function () {
				delete attachSelectedFiles[id];
				var n = Object.keys(attachSelectedFiles).length;
				$('#attach-sel-count').text(n ? (n + (n === 1 ? ' file selected' : ' files selected')) : '');
				card.remove();
				if (!$('#attach-files-grid .file-card').length) {
					$('#attach-files-grid').html('<div class="empty-files">You haven\'t uploaded any files yet.</div>');
				}
			}).fail(function (xhr) {
				button.prop('disabled', false);
				var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Could not delete that file.';
				if (typeof swal === 'function') { swal('Not deleted', msg, 'error'); } else { alert(msg); }
			});
		}

		if (typeof swal === 'function') {
			swal({
				title: 'Delete this file?',
				text: 'This removes it from your library for good.',
				type: 'warning',
				showCancelButton: true,
				confirmButtonColor: '#c0392b',
				confirmButtonText: 'Yes, delete it',
			}).then(function (result) {
				if (result === true || (result && result.value)) { reallyDelete(); }
			});
		} else if (confirm('Delete this file for good?')) {
			reallyDelete();
		}
	});

	$('#attach-submit-btn').on('click', function () {
		var button = $(this).prop('disabled', true);
		var uploading = $('#attach-tab-upload').hasClass('active');

		function doneFromServer(response) {
			// Saved row: the server already has the full, authoritative list -
			// show exactly that rather than guessing at what changed.
			renderAttachmentsCell(attachItemId, response, attachItemName);
			$('#attach-modal').modal('hide');
			button.prop('disabled', false);
		}
		function doneLocally(newAttachments) {
			// Unsaved row: there is no server copy of this link yet, so the
			// new full list is whatever the cell already showed plus what was
			// just added.
			var cell = $('.attachments-cell[data-item-id="' + attachItemId + '"]');
			var merged = currentAttachmentsFromCell(cell).concat(newAttachments);
			renderAttachmentsCell(attachItemId, merged, attachItemName);
			$('#attach-modal').modal('hide');
			button.prop('disabled', false);
		}
		function failed(xhr) {
			button.prop('disabled', false);
			var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Could not save that attachment.';
			if (typeof swal === 'function') { swal('Not saved', msg, 'error'); } else { alert(msg); }
		}

		if (uploading) {
			if (!attachPickedFile) {
				button.prop('disabled', false);
				return swal ? swal('Choose a file', 'Pick a file to upload first.', 'warning') : alert('Pick a file to upload first.');
			}
			var title = $('#attach-title').val().trim();
			if (!title) {
				button.prop('disabled', false);
				return swal ? swal('Title required', 'Give this file a short title.', 'warning') : alert('Give this file a short title.');
			}
			var formData = new FormData();
			formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
			formData.append('title', title);
			formData.append('file', attachPickedFile);

			// The FILE can always be uploaded right away, whether or not this
			// row is saved yet - only the link to a specific item has to wait.
			$.ajax({
				url: attachRowSaved ? attachUrl('upload') : '{{ route("attachments.upload-to-library") }}',
				method: 'POST',
				data: formData,
				processData: false,
				contentType: false,
			}).done(function (response) {
				if (attachRowSaved) { doneFromServer(response); } else { doneLocally([response]); }
			}).fail(failed);
		} else {
			var files = Object.values(attachSelectedFiles);
			if (files.length === 0) {
				button.prop('disabled', false);
				return swal ? swal('Choose a file', 'Select at least one file to attach.', 'warning') : alert('Select at least one file to attach.');
			}
			if (attachRowSaved) {
				$.ajax({
					url: attachUrl('attach'),
					method: 'POST',
					data: { _token: $('meta[name="csrf-token"]').attr('content'), attachment_ids: files.map(function (f) { return f.id; }) },
				}).done(doneFromServer).fail(failed);
			} else {
				// Nothing to link server-side yet - the files already exist in
				// the library, so this is purely a local addition to the cell.
				doneLocally(files);
			}
		}
	});

	// Remove one file from this line - the file itself stays in the officer's
	// library, only the link to this item goes away. On a saved row that's a
	// real server-side unlink; on an unsaved one there was never a link to
	// begin with, so it's just removed from the cell.
	$(document).on('click', '.att-chip-remove', function (e) {
		e.stopPropagation();
		var row = $(this).closest('tr');
		var chip = $(this).closest('.att-chip');
		var cell = chip.closest('.attachments-cell');
		var itemId = cell.data('item-id');
		var itemName = cell.find('.add-att-btn').data('item-name') || '';
		var attachmentId = $(this).data('id');

		if (!row.data('saved')) {
			chip.remove();
			return;
		}

		$.ajax({
			url: attachBaseUrl(itemId) + '/' + attachmentId + '/detach',
			method: 'POST',
			data: { _token: $('meta[name="csrf-token"]').attr('content') },
		}).done(function (response) {
			renderAttachmentsCell(itemId, response, itemName);
		}).fail(function () {
			if (typeof swal === 'function') { swal('Not removed', 'Could not remove that attachment.', 'error'); } else { alert('Could not remove that attachment.'); }
		});
	});

	// Clicking a chip's title previews the file in a new tab - it's the only
	// file on this click, so there's nothing to list first.
	$(document).on('click', '.att-chip-title', function () {
		var url = $(this).closest('.att-chip').data('view-url');
		window.open(url, '_blank');
	});
});
</script>
@endsection
