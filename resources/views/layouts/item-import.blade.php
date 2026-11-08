@extends('layouts.admin-master')
@section('main-content')
<div class="col-lg-6 col-xl-12">
	<div class="card">
		<div class="card-header pv-card-hader">
			<strong class="pptitle">Import Catalog</strong>
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
				<li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#ii-tab-upload" role="tab"><i class="fas fa-upload"></i> Upload Sheet</a></li>
				<li class="nav-item"><a class="nav-link" data-toggle="tab" href="#ii-tab-manual" role="tab"><i class="fas fa-edit"></i> Add Item Manually</a></li>
			</ul>

			<div class="tab-content">
				<div class="tab-pane fade show active" id="ii-tab-upload" role="tabpanel">
					<p class="text-muted">
						Upload an Excel/CSV catalog for one vessel. Expected columns: <b>Item Path</b> (a "->"-delimited
						hierarchy, e.g. <code>Provisions-&gt;Beans and Peas, Dry-&gt;BEANS BROAD DRY</code>),
						<b>Item Name</b>, <b>Article Number</b>, Unit Code, Account Number, Description, Part Number,
						Drawing Number, HS Code, Manufacturer. Re-uploading the same file is safe — items are matched
						by Article Number and updated, not duplicated.
					</p>

					<form action="{{url('/catalog/import')}}" method="POST" enctype="multipart/form-data">
						@csrf
						<div class="form-group">
							<label>Category</label>
							<select name="category_id" class="form-control" required>
								<option value="">Select category</option>
								@foreach($categories as $category)
								<option value="{{$category->id}}">{{$category->name}}</option>
								@endforeach
							</select>
						</div>
						<div class="form-group">
							<label>Vessel</label>
							@if($lockedVessel)
							<input type="text" class="form-control" value="{{$lockedVessel->name}}" disabled>
							<input type="hidden" name="vessel_id" value="{{$lockedVessel->id}}">
							<small class="text-muted">Imports always apply to your own vessel.</small>
							@else
							<select name="vessel_id" class="form-control" required>
								<option value="">Select vessel</option>
								@foreach($vessels as $vessel)
								<option value="{{$vessel->id}}">{{$vessel->name}}</option>
								@endforeach
							</select>
							@endif
						</div>
						<div class="form-group">
							<label>Catalog file (.xlsx, .xls, .csv)</label>
							<input type="file" name="catalog_file" class="form-control-file" accept=".xlsx,.xls,.csv" required>
						</div>
						<button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-upload"></i> Import</button>
					</form>
				</div>

				<div class="tab-pane fade" id="ii-tab-manual" role="tabpanel">
					<p class="text-muted">
						For a single item, fill in the details below instead of building a spreadsheet row for it.
						An article number that already exists is never duplicated — you'll be offered to link your
						vessel to the existing item instead.
					</p>

					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label for="mi_vessel_id">Vessel</label>
								@if($lockedVessel)
								<input type="text" class="form-control" value="{{$lockedVessel->name}}" disabled>
								<input type="hidden" id="mi_vessel_id" value="{{$lockedVessel->id}}">
								@else
								<select id="mi_vessel_id" class="form-control" required>
									<option value="">Select vessel</option>
									@foreach($vessels as $vessel)
									<option value="{{$vessel->id}}">{{$vessel->name}}</option>
									@endforeach
								</select>
								@endif
							</div>
							<div class="form-group">
								<label for="mi_category_id">Category</label>
								<select id="mi_category_id" class="form-control" required>
									<option value="">Select category</option>
									@foreach($categories as $category)
									<option value="{{$category->id}}">{{$category->name}}</option>
									@endforeach
								</select>
							</div>
							<div class="form-group">
								<label>Item Group</label>
								<div class="mb-2">
									<div class="custom-control custom-radio custom-control-inline">
										<input type="radio" id="mi_group_mode_pick" name="mi_group_mode" value="pick" class="custom-control-input" checked>
										<label class="custom-control-label" for="mi_group_mode_pick">Pick existing</label>
									</div>
									<div class="custom-control custom-radio custom-control-inline">
										<input type="radio" id="mi_group_mode_type" name="mi_group_mode" value="type" class="custom-control-input">
										<label class="custom-control-label" for="mi_group_mode_type">Type new path</label>
									</div>
								</div>
								<div id="mi-group-pick" class="sr-tree-select" style="position:relative;">
									<button type="button" class="form-control sr-tree-field" id="mi_group_toggle" disabled
										aria-haspopup="true" aria-expanded="false">
										<span class="sr-tree-field-text">Choose vessel &amp; category first</span>
										<i class="fas fa-caret-down"></i>
									</button>
									<div class="sr-tree-panel" id="mi_group_panel" hidden>
										<ul class="sr-tree" id="mi_group_tree"></ul>
									</div>
								</div>
								<input type="text" id="mi-group-type" class="form-control" style="display:none;"
									placeholder="e.g. Provisions -> Beans and Peas, Dry -> BEANS BROAD DRY">
								<input type="hidden" id="mi_group_id" value="">
							</div>
							<div class="form-group">
								<label for="mi_article_number">Article Number <span class="text-danger">*</span></label>
								<input type="text" id="mi_article_number" class="form-control">
								<div id="mi-duplicate-warning" class="alert alert-warning mt-2" style="display:none;"></div>
							</div>
							<div class="form-group">
								<label for="mi_name">Item Name <span class="text-danger">*</span></label>
								<input type="text" id="mi_name" class="form-control">
							</div>
							<div class="form-group">
								<label for="mi_unit">Unit</label>
								<input type="text" id="mi_unit" class="form-control" value="PC">
							</div>
						</div>
						<div class="col-md-6">
							<div class="form-group"><label for="mi_account_number">Account Number</label><input type="text" id="mi_account_number" class="form-control"></div>
							<div class="form-group"><label for="mi_part_number">Part Number</label><input type="text" id="mi_part_number" class="form-control"></div>
							<div class="form-group"><label for="mi_drawing_number">Drawing Number</label><input type="text" id="mi_drawing_number" class="form-control"></div>
							<div class="form-group"><label for="mi_hs_code">HS Code</label><input type="text" id="mi_hs_code" class="form-control"></div>
							<div class="form-group"><label for="mi_manufacturer">Manufacturer</label><input type="text" id="mi_manufacturer" class="form-control"></div>
							<div class="form-group"><label for="mi_description">Description</label><textarea id="mi_description" class="form-control" rows="2"></textarea></div>
							@if((auth()->user()->role->role ?? null) === 'master')
							<hr>
							<p class="text-muted small mb-2">You're this vessel's Master, so you can also set the opening figures right here.</p>
							<div class="form-row">
								<div class="form-group col-6"><label for="mi_opening_stock">Opening Stock</label><input type="number" min="0" id="mi_opening_stock" class="form-control"></div>
								<div class="form-group col-6"><label for="mi_min_qty">Min Qty</label><input type="number" min="0" id="mi_min_qty" class="form-control"></div>
							</div>
							@endif
						</div>
					</div>

					<div id="manual-item-result" class="alert alert-success" style="display:none;"></div>
					<button type="button" id="manual-item-save" class="btn btn-sm btn-primary"><i class="fas fa-save"></i> Save</button>
					<button type="button" id="manual-item-save-another" class="btn btn-sm btn-outline-primary"><i class="fas fa-plus"></i> Save and Add Another</button>
				</div>
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
	max-height:300px; overflow:auto; background:#fff;
	border:1px solid #ced4da; border-radius:6px; box-shadow:0 8px 24px rgba(0,0,0,.12); padding:6px 0;
}
.sr-tree, .sr-tree ul{ list-style:none; margin:0; padding:0; }
.sr-tree ul{ padding-left:18px; }
.sr-tree-node{ display:flex; align-items:center; gap:4px; padding:2px 8px; }
.sr-tree-caret{ width:20px; height:22px; flex:0 0 20px; border:0; background:none; padding:0; color:#5f6b6b; }
.sr-tree-caret[disabled]{ visibility:hidden; }
.sr-tree-label{ flex:1; min-width:0; border:0; background:none; text-align:left; padding:2px 4px; border-radius:4px; font-size:13.5px; }
.sr-tree-label:hover, .sr-tree-label:focus{ background:#eef6f6; }
.sr-tree-msg{ padding:4px 12px; color:#6b7877; font-size:13px; }
</style>
<script>
$(function () {
	function escapeHtml(text) {
		return $('<div>').text(text === null || text === undefined ? '' : text).html();
	}

	// ---- Item Group tree picker (same shape as Stock Report's own) ----
	var $groupField = $('#mi_group_toggle'), $groupPanel = $('#mi_group_panel');

	function resetGroupPicker() {
		$('#mi_group_tree').empty().data('loaded', false);
		$('#mi_group_id').val('');
		var canPick = !!($('#mi_vessel_id').val() && $('#mi_category_id').val());
		$groupField.prop('disabled', !canPick)
			.find('.sr-tree-field-text').text(canPick ? 'Choose a group…' : 'Choose vessel & category first');
	}

	function loadGroups(parentId, $list) {
		$list.html('<li class="sr-tree-msg">Loading…</li>');
		$.getJSON('{{ url('/catalog/browse/children') }}/' + (parentId || ''), {
			vessel_id: $('#mi_vessel_id').val(), category_id: $('#mi_category_id').val(),
		}, function (groups) {
			if (!groups.length) {
				$list.html('<li class="sr-tree-msg">No groups here yet — type a new path instead.</li>');
				return;
			}
			$list.html($.map(groups, function (g) {
				return '<li data-id="' + g.id + '"><div class="sr-tree-node">'
					+ '<button type="button" class="sr-tree-caret" aria-label="Expand"' + (g.children_count > 0 ? '' : ' disabled') + '>'
					+ '<i class="fas fa-caret-right"></i></button>'
					+ '<button type="button" class="sr-tree-label">' + escapeHtml(g.name) + '</button>'
					+ '</div><ul hidden></ul></li>';
			}).join(''));
		}).fail(function () {
			$list.html('<li class="sr-tree-msg">Could not load groups.</li>');
		});
	}

	$groupField.on('click', function () {
		var opening = $groupPanel.prop('hidden');
		$groupPanel.prop('hidden', !opening);
		$groupField.attr('aria-expanded', opening ? 'true' : 'false');
		if (opening && !$('#mi_group_tree').data('loaded')) {
			$('#mi_group_tree').data('loaded', true);
			loadGroups(null, $('#mi_group_tree'));
		}
	});

	$('#mi_group_tree').on('click', '.sr-tree-caret', function () {
		var $li = $(this).closest('li'), $children = $li.children('ul');
		var expanding = $children.prop('hidden');
		$children.prop('hidden', !expanding);
		$(this).find('i').toggleClass('fa-caret-right', !expanding).toggleClass('fa-caret-down', expanding);
		if (expanding && !$children.data('loaded')) {
			$children.data('loaded', true);
			loadGroups($li.data('id'), $children);
		}
	});

	$('#mi_group_tree').on('click', '.sr-tree-label', function () {
		var $li = $(this).closest('li');
		$('#mi_group_id').val($li.data('id'));
		$groupField.find('.sr-tree-field-text').text($(this).text()).attr('title', $(this).text());
		$groupPanel.prop('hidden', true);
	});

	$(document).on('click', function (e) {
		if (!$(e.target).closest('#mi-group-pick').length) { $groupPanel.prop('hidden', true); }
	});

	$('#mi_vessel_id, #mi_category_id').on('change', resetGroupPicker);
	resetGroupPicker();

	$('input[name=mi_group_mode]').on('change', function () {
		var typing = $(this).val() === 'type';
		$('#mi-group-pick').toggle(!typing);
		$('#mi-group-type').toggle(typing);
	});

	// ---- Duplicate article number check ----
	var linkedExistingId = null;

	function duplicateBanner(item) {
		return 'An item with this article number already exists: <b>' + escapeHtml(item.name) + '</b>'
			+ (item.category ? ' (' + escapeHtml(item.category) + (item.group_path ? ' &rarr; ' + escapeHtml(item.group_path) : '') + ')' : '')
			+ '. <button type="button" class="btn btn-sm btn-warning ml-2" id="mi-link-existing" data-id="' + item.id + '" data-name="' + escapeHtml(item.name) + '">Link this vessel to it instead</button>';
	}

	$('#mi_article_number').on('blur', function () {
		var term = $(this).val().trim();
		if (!term) { $('#mi-duplicate-warning').hide(); return; }

		$.getJSON('{{ route('catalog.import.check-article') }}', { article_number: term }, function (res) {
			if (res.exists) {
				$('#mi-duplicate-warning').removeClass('alert-info').addClass('alert-warning').html(duplicateBanner(res.item)).show();
			} else {
				$('#mi-duplicate-warning').hide();
			}
		});
	});

	$('#mi_article_number').on('input', function () {
		if (linkedExistingId) {
			linkedExistingId = null;
			$('#mi_name').val('').prop('disabled', false);
			$('#mi-duplicate-warning').hide();
		}
	});

	$(document).on('click', '#mi-link-existing', function () {
		linkedExistingId = $(this).data('id');
		$('#mi_name').val($(this).data('name')).prop('disabled', true);
		$('#mi-duplicate-warning').removeClass('alert-warning').addClass('alert-info')
			.html('Linking your vessel to the existing item "' + escapeHtml($(this).data('name')) + '". '
				+ '<button type="button" class="btn btn-sm btn-link p-0" id="mi-cancel-link">Cancel</button>');
	});

	$(document).on('click', '#mi-cancel-link', function () {
		linkedExistingId = null;
		$('#mi_name').val('').prop('disabled', false);
		$('#mi-duplicate-warning').hide();
	});

	// ---- Save ----
	function fieldVal(id) { return $(id).length ? $(id).val().trim() : ''; }

	function collectPayload() {
		var payload = {
			vessel_id: $('#mi_vessel_id').val(),
			category_id: $('#mi_category_id').val(),
			article_number: fieldVal('#mi_article_number'),
			name: fieldVal('#mi_name'),
			unit: fieldVal('#mi_unit'),
			account_number: fieldVal('#mi_account_number'),
			part_number: fieldVal('#mi_part_number'),
			drawing_number: fieldVal('#mi_drawing_number'),
			hs_code: fieldVal('#mi_hs_code'),
			manufacturer: fieldVal('#mi_manufacturer'),
			description: fieldVal('#mi_description'),
			opening_stock: fieldVal('#mi_opening_stock'),
			min_qty: fieldVal('#mi_min_qty'),
		};

		if (linkedExistingId) {
			payload.existing_item_id = linkedExistingId;
		} else if ($('input[name=mi_group_mode]:checked').val() === 'type') {
			payload.item_group_path = fieldVal('#mi-group-type');
		} else {
			payload.item_group_id = $('#mi_group_id').val();
		}

		return payload;
	}

	function resetFormForAnother() {
		$('#mi_article_number, #mi_name, #mi_account_number, #mi_part_number, #mi_drawing_number, #mi_hs_code, #mi_manufacturer, #mi_description, #mi_opening_stock, #mi_min_qty').val('');
		$('#mi_name').prop('disabled', false);
		$('#mi_unit').val('PC');
		linkedExistingId = null;
		$('#mi-duplicate-warning').hide();
		$('#mi_article_number').focus();
	}

	function doSave(andAnother) {
		if (!$('#mi_vessel_id').val() || !$('#mi_category_id').val() || !fieldVal('#mi_article_number') || (!linkedExistingId && !fieldVal('#mi_name'))) {
			var msg = 'Fill in vessel, category, article number and item name first.';
			if (typeof swal === 'function') { swal('Missing fields', msg, 'warning'); } else { alert(msg); }
			return;
		}

		var $buttons = $('#manual-item-save, #manual-item-save-another').prop('disabled', true);

		$.ajax({
			url: '{{ route('catalog.import.manual') }}',
			method: 'POST',
			data: $.extend({ _token: $('meta[name="csrf-token"]').attr('content') }, collectPayload()),
		}).done(function (res) {
			$('#manual-item-result').text(res.message).show();
			$buttons.prop('disabled', false);
			if (andAnother) {
				resetFormForAnother();
			} else {
				window.location = '{{ route('catalog.import.history') }}';
			}
		}).fail(function (xhr) {
			$buttons.prop('disabled', false);
			if (xhr.status === 409 && xhr.responseJSON && xhr.responseJSON.existing_item) {
				$('#mi-duplicate-warning').removeClass('alert-info').addClass('alert-warning').html(duplicateBanner({
					id: xhr.responseJSON.existing_item.id, name: xhr.responseJSON.existing_item.name,
				})).show();
				return;
			}
			var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Could not save this item.';
			if (typeof swal === 'function') { swal('Not saved', msg, 'error'); } else { alert(msg); }
		});
	}

	$('#manual-item-save').on('click', function () { doSave(false); });
	$('#manual-item-save-another').on('click', function () { doSave(true); });
});
</script>
@endsection
