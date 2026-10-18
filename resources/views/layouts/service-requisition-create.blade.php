@extends('layouts.admin-master')
@section('main-content')
<style>
	/* The equipment search results used to be a normal block element, so
	   opening it pushed Due Date/Description/the buttons down the page and
	   closing it snapped them back up - a jarring jump on every keystroke.
	   Floating it over the page instead (like a real autocomplete) keeps the
	   rest of the form still.

	   Positioned relative to .rs-search-wrap, NOT the column
	   (#shore-repair-section) - a Bootstrap column has its own 15px side
	   padding, and an absolutely positioned child's containing block is the
	   PADDING box of its positioned ancestor, so anchoring to the column
	   itself made the dropdown 15px wider than the input on both sides. This
	   inner wrapper has no padding of its own, so left:0/right:0 on it lines
	   up exactly with the input's real edges. */
	.rs-search-wrap{ position: relative; }
	#equipment-results{
		position: absolute; top: 100%; left: 0; right: 0; z-index: 30;
		margin-top: 2px; background: #fff; border: 1px solid #ddd; border-radius: 6px;
		box-shadow: 0 8px 24px rgba(0,0,0,.14);
	}
	#equipment-results:empty{ display: none; border: none; box-shadow: none; }
</style>
<div class="order-section container">
	<div class="row">
		<div class="col-xl-12">
			<div class="card order-card">
				<div class="card-header first">
					<strong class="pptitle">New service requisition for &nbsp;
						<span style="color:red;">{{auth()->user()->role->vessel->name}}</span>
					</strong>
				</div>
				<div class="card-body">
					{{-- Submitted over AJAX (see the JS at the foot of this file) so
						 validation errors land back in a dialog instead of throwing
						 the half-filled line list away on a redirect. --}}

					<form id="service-requisition-form" method="POST" action="{{ route('service-requisition.store') }}"
						data-reviewer="{{ auth()->user()->role->role === 'second-engineer' ? 'Chief Engineer' : 'Master' }}">
						@csrf

						<div class="form-group row">
							<div class="col-md-4">
								<label for="service_type">Service type <span class="text-danger">*</span></label>
								<select class="form-control" name="service_type" id="service_type" required>
									<option value="">Choose…</option>
									@foreach($types as $value => $label)
									<option value="{{ $value }}">{{ $label }}</option>
									@endforeach
								</select>
							</div>
							{{-- Service budgets, not the stores ones an item requisition
								 draws on - both live in budget_groups, told apart by kind
								 (see App\BudgetGroup). --}}
							<div class="col-md-4">
								<label for="budget_group_id">Budget group <span class="text-danger">*</span></label>
								<select class="form-control" name="budget_group_id" id="budget_group_id" required>
									<option value="">Choose…</option>
									@foreach($budgetGroups as $group)
									<option value="{{ $group->id }}">{{ $group->name }}</option>
									@endforeach
								</select>
							</div>
							<div class="col-md-4">
								<label for="due_date">Due date</label>
								<input type="text" class="form-control date" name="due_date" id="due_date" autocomplete="off" placeholder="Click to choose a date">
							</div>
						</div>

						<div class="form-group row">
							{{-- Shore Repair identifies what needs work through the equipment
								 lines below, not a free-text title - each line already names
								 the equipment and its maker. Shown only for Shore Repair so
								 a future Renewal option (see ServiceRequisitionController::TYPES)
								 can use a different shape here without this markup in the way. --}}
							<div class="col-md-12" id="shore-repair-section" style="display:none;">
								<label>Equipment <span class="text-danger">*</span></label>
								<div class="rs-search-wrap">
									<input type="text" class="form-control" id="equipment-search" placeholder="Start typing an equipment name or maker" autocomplete="off">
									<div id="equipment-results" class="list-group" style="max-height:260px; overflow-y:auto;"></div>
								</div>
							</div>
							{{-- Renewal picks from the vessel's own certificates instead:
								 choose the category, then the certificate within it. Only
								 categories this vessel actually holds certificates in are
								 listed (see ServiceRequisitionController::create). --}}
							<div class="col-md-4" id="renewal-category-col" style="display:none;">
								<label for="certificate_category_id">Certificate category <span class="text-danger">*</span></label>
								<select class="form-control" id="certificate_category_id">
									<option value="">Choose…</option>
									@foreach($certificateCategories as $category)
									<option value="{{ $category->id }}">{{ $category->name }}</option>
									@endforeach
								</select>
								@if($certificateCategories->isEmpty())
								<small class="form-text text-muted">No certificates recorded for this vessel yet.</small>
								@endif
							</div>
							<div class="col-md-8" id="renewal-certificate-col" style="display:none;">
								<label for="certificate_pick">Certificate <span class="text-danger">*</span></label>
								<div class="d-flex">
									<select class="form-control mr-2" id="certificate_pick" disabled>
										<option value="">Choose a category first…</option>
									</select>
									<button type="button" class="btn btn-info" id="add-certificate-line">Add</button>
								</div>
							</div>
						</div>

						{{-- Each certificate is renewed once, so there's no quantity to
							 set - the line carries a fixed 1. --}}
						<div class="form-group row" id="renewal-lines-wrap" style="display:none;">
							<div class="col-md-12">
								<table class="table table-bordered" id="renewal-lines-table" style="display:none;">
									<thead>
										<tr>
											<th>Certificate Title</th>
											<th>Category</th>
											<th>Expiry</th>
											<th style="width:100px;">Quantity</th>
											<th style="width:60px;"></th>
										</tr>
									</thead>
									<tbody></tbody>
								</table>
							</div>
						</div>

						<div class="form-group row" id="equipment-lines-wrap" style="display:none;">
							<div class="col-md-12">
								<table class="table table-bordered" id="equipment-lines-table" style="display:none;">
									<thead>
										<tr>
											<th>Equipment Name</th>
											<th>Maker</th>
											<th style="width:140px;">Quantity</th>
											<th style="width:60px;"></th>
										</tr>
									</thead>
									<tbody></tbody>
								</table>
							</div>
						</div>

						<div class="form-group row">
							<div class="col-md-11">
								<label for="description">Description <span class="text-danger">*</span></label>
								<textarea class="form-control" name="description" id="description" rows="4" placeholder="Fault description / scope of the work - what's wrong, what needs doing, and why" required></textarea>
							</div>
						</div>

						<div class="form-group row">
							<div class="col-md-11">
								<label for="attachments">Attachments</label>
								<input type="file" class="form-control-file" name="attachments[]" id="attachments" multiple>
								<small class="form-text text-muted">e.g. a photo of the fault, a defect report.</small>
							</div>
						</div>

						<div class="form-group row">
							<div class="col-md-11 text-right">
								<a href="{{ url('/home') }}" class="btn btn-outline-secondary">Cancel</a>
								<button type="submit" class="btn btn-success">Submit Service Requisition</button>
							</div>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>
@endsection

@section('home-js')
<script>
$(function () {
	var pickedIds = {};

	function renderResults(list) {
		if (list.length === 0) {
			$('#equipment-results').html('<p class="text-muted p-2">No matching equipment on your vessel\'s list.</p>');
			return;
		}
		var html = '';
		list.forEach(function (eq) {
			var label = $('<div>').text(eq.name + (eq.maker ? ' — ' + eq.maker : '')).html();
			html += '<a href="#" class="list-group-item list-group-item-action equipment-pick" '
				+ 'data-id="' + eq.id + '" data-name="' + $('<div>').text(eq.name).html() + '" '
				+ 'data-maker="' + $('<div>').text(eq.maker || '').html() + '">' + label + '</a>';
		});
		$('#equipment-results').html(html);
	}

	var searchTimer = null;
	$('#equipment-search').on('focus keyup', function () {
		var term = $(this).val();
		clearTimeout(searchTimer);
		searchTimer = setTimeout(function () {
			$.getJSON('{{ route("service-requisition.search-equipment") }}', { q: term }, renderResults);
		}, 200);
	});

	$(document).on('click', '.equipment-pick', function (e) {
		e.preventDefault();
		var id = $(this).data('id');
		var name = $(this).data('name');
		var maker = $(this).data('maker');

		if (pickedIds[id]) {
			swal('Already added', 'This equipment is already on the list below.', 'warning');
			return;
		}
		pickedIds[id] = true;

		var row = $('<tr data-id="' + id + '">'
			+ '<td>' + $('<div>').text(name).html() + '<input type="hidden" name="equipment_id[]" value="' + id + '"></td>'
			+ '<td>' + $('<div>').text(maker).html() + '</td>'
			+ '<td><input type="number" min="1" class="form-control" name="equipment_qty[]" value="1" required></td>'
			+ '<td><button type="button" class="btn btn-danger btn-sm remove-equipment-line"><i class="fas fa-trash-alt"></i></button></td>'
			+ '</tr>');
		$('#equipment-lines-table tbody').append(row);
		$('#equipment-lines-table').show();
		$('#equipment-lines-wrap').show();

		$('#equipment-search').val('');
		$('#equipment-results').empty();
	});

	$(document).on('click', '.remove-equipment-line', function () {
		var row = $(this).closest('tr');
		delete pickedIds[row.data('id')];
		row.remove();
		if ($('#equipment-lines-table tbody tr').length === 0) {
			$('#equipment-lines-table').hide();
			$('#equipment-lines-wrap').hide();
		}
	});

	$(document).on('click', function (e) {
		if (!$(e.target).closest('#equipment-search, #equipment-results').length) {
			$('#equipment-results').empty();
		}
	});

	// ---- Renewal: category -> that category's certificates ----
	$('#certificate_category_id').on('change', function () {
		var categoryId = $(this).val();
		var $pick = $('#certificate_pick');

		if (!categoryId) {
			$pick.prop('disabled', true).html('<option value="">Choose a category first…</option>');
			return;
		}

		$pick.prop('disabled', true).html('<option value="">Loading…</option>');
		$.getJSON('{{ route("service-requisition.certificates") }}', { category_id: categoryId }, function (list) {
			if (!list.length) {
				$pick.prop('disabled', true).html('<option value="">No certificates in this category</option>');
				return;
			}
			var html = '<option value="">Choose…</option>';
			list.forEach(function (c) {
				html += '<option value="' + c.id + '" data-title="' + $('<div>').text(c.title).html() + '"'
					+ ' data-expiry="' + $('<div>').text(c.expiry || '').html() + '">'
					+ $('<div>').text(c.title).html() + '</option>';
			});
			$pick.prop('disabled', false).html(html);
		});
	});

	$('#add-certificate-line').on('click', function () {
		var $opt = $('#certificate_pick option:selected');
		var id = $opt.val();

		if (!id) {
			swal('Choose a certificate', 'Pick a certificate from the list before adding it.', 'warning');
			return;
		}
		if (pickedIds['cert-' + id]) {
			swal('Already added', 'This certificate is already on the list below.', 'warning');
			return;
		}
		pickedIds['cert-' + id] = true;

		var categoryName = $('#certificate_category_id option:selected').text();
		// Quantity is always 1 - a certificate is renewed once, so it's shown
		// as fixed text with the value carried in a hidden input rather than
		// an editable box someone could put 2 in.
		var row = $('<tr data-id="cert-' + id + '">'
			+ '<td>' + $('<div>').text($opt.data('title')).html() + '<input type="hidden" name="certificate_id[]" value="' + id + '"></td>'
			+ '<td>' + $('<div>').text(categoryName).html() + '</td>'
			+ '<td>' + $('<div>').text($opt.data('expiry') || '—').html() + '</td>'
			+ '<td>1<input type="hidden" name="certificate_qty[]" value="1"></td>'
			+ '<td><button type="button" class="btn btn-danger btn-sm remove-renewal-line"><i class="fas fa-trash-alt"></i></button></td>'
			+ '</tr>');
		$('#renewal-lines-table tbody').append(row);
		$('#renewal-lines-table').show();
		$('#renewal-lines-wrap').show();
		$('#certificate_pick').val('');
	});

	$(document).on('click', '.remove-renewal-line', function () {
		var row = $(this).closest('tr');
		delete pickedIds[row.data('id')];
		row.remove();
		if ($('#renewal-lines-table tbody tr').length === 0) {
			$('#renewal-lines-table').hide();
			$('#renewal-lines-wrap').hide();
		}
	});

	// ---- Type switching ----
	var SHORE = '{{ \App\Http\Controllers\ServiceRequisitionController::TYPE_SHORE_REPAIR }}';
	var RENEWAL = '{{ \App\Http\Controllers\ServiceRequisitionController::TYPE_RENEWAL }}';
	var previousType = $('#service_type').val();

	function lineCount() {
		return $('#equipment-lines-table tbody tr').length + $('#renewal-lines-table tbody tr').length;
	}

	function clearAllLines() {
		$('#equipment-lines-table tbody, #renewal-lines-table tbody').empty();
		$('#equipment-lines-table, #equipment-lines-wrap, #renewal-lines-table, #renewal-lines-wrap').hide();
		pickedIds = {};
	}

	function applyType(type) {
		$('#shore-repair-section').toggle(type === SHORE);
		$('#renewal-category-col, #renewal-certificate-col').toggle(type === RENEWAL);

		$('#equipment-lines-wrap').toggle(type === SHORE && $('#equipment-lines-table tbody tr').length > 0);
		$('#renewal-lines-wrap').toggle(type === RENEWAL && $('#renewal-lines-table tbody tr').length > 0);

		// Clear the pickers so a half-finished selection doesn't linger
		// behind a type the user has moved away from.
		$('#equipment-search').val('');
		$('#equipment-results').empty();
		$('#certificate_category_id').val('');
		$('#certificate_pick').prop('disabled', true).html('<option value="">Choose a category first…</option>');
	}

	// One requisition is Shore Repair OR Renewal, never both - its lines come
	// from two different sources, so switching type after adding any means
	// starting the list over. Asked rather than silently discarded.
	$('#service_type').on('change', function () {
		var newType = $(this).val();

		if (newType === previousType) { return; }

		if (lineCount() === 0) {
			previousType = newType;
			applyType(newType);
			return;
		}

		var $select = $(this);
		swal({
			title: 'Change service type?',
			text: 'The ' + lineCount() + ' item(s) already added belong to the other type and will be removed.',
			type: 'warning',
			showCancelButton: true,
			confirmButtonColor: '#d33',
			confirmButtonText: 'Yes, start the list again',
			cancelButtonText: 'No, keep them',
		}).then(function (result) {
			if (result === true || (result && result.value)) {
				clearAllLines();
				previousType = newType;
				applyType(newType);
			} else {
				$select.val(previousType);
			}
		});
	});

	applyType($('#service_type').val());

	// Submitting is the raising officer's own sign-off, so it goes straight to
	// the Master/Chief Engineer - said plainly here so nobody expects a draft
	// stage that doesn't exist.
	$('#service-requisition-form').on('submit', function (e) {
		e.preventDefault();

		var type = $('#service_type').val();

		if (type === SHORE && $('#equipment-lines-table tbody tr').length === 0) {
			swal('Add at least one equipment', 'Search for and add at least one piece of equipment before submitting.', 'warning');
			return;
		}
		if (type === RENEWAL && $('#renewal-lines-table tbody tr').length === 0) {
			swal('Add at least one certificate', 'Choose a category and add at least one certificate before submitting.', 'warning');
			return;
		}

		var $button = $(this).find('button[type="submit"]');

		swal({
			title: 'Submit this requisition?',
			text: 'It goes to your ' + ($('#service_type').closest('form').data('reviewer') || 'Master / Chief Engineer') + ' for review. You cannot edit it afterwards.',
			type: 'question',
			showCancelButton: true,
			confirmButtonText: 'Yes, submit',
			cancelButtonText: 'Not yet',
		}).then(function (result) {
			if (!(result === true || (result && result.value))) { return; }

			$button.prop('disabled', true).text('Submitting…');

			$.post('{{ route("service-requisition.store") }}', $('#service-requisition-form').serialize())
				.done(function (res) {
					swal('Submitted', res.message, 'success').then(function () {
						window.location.href = res.redirect;
					});
				})
				.fail(function (xhr) {
					$button.prop('disabled', false).text('Submit Service Requisition');

					var msg = 'Something went wrong - nothing was saved.';
					if (xhr.responseJSON) {
						// Laravel's validation errors come back keyed by field;
						// showing them beats a generic failure message.
						msg = xhr.responseJSON.errors
							? $.map(xhr.responseJSON.errors, function (v) { return v[0]; }).join('\n')
							: (xhr.responseJSON.message || msg);
					}
					swal('Not submitted', msg, 'error');
				});
		});
	});
});
</script>
@endsection
