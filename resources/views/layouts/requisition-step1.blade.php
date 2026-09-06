@extends('layouts.admin-master')
@section('main-content')
<div class="order-section container">
	<div class="row">
		<div class="col-xl-12">
			<div class="card order-card">
				<div class="card-header first">
					<strong class="pptitle">New requisition for &nbsp;
						<span style="color:red;">{{auth()->user()->role->vessel->name}}</span>
					</strong>
					<div class="right-button">Step 1 of 3 — Details</div>
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

					<form method="POST" action="{{ route('requisition.step1.store') }}">
						@csrf

						<div class="form-group row">
							<div class="col-md-11">
								<label for="title">Requisition title <span class="text-danger">*</span></label>
								<input type="text" class="form-control" name="title" id="title" value="{{ old('title') }}" required>
							</div>
						</div>

						<div class="form-group row justify-content-between">
							<div class="col-md-5">
								<label>Budget group <span class="text-danger">*</span></label>
								<input type="text" class="form-control" id="budget-group-search" placeholder="Start typing to get suggestions" autocomplete="off">
								<input type="hidden" name="budget_group_id" id="budget_group_id" value="{{ old('budget_group_id') }}">
								<div id="budget-group-results" class="list-group" style="max-height:220px; overflow-y:auto; position:relative; z-index:5;"></div>
							</div>
							<div class="col-md-5">
								<label for="department">Department <span class="text-danger">*</span></label>
								<select class="form-control" name="department" id="department" required>
									<option value="">Choose…</option>
									<option value="Deck">Deck</option>
									<option value="Engine">Engine</option>
								</select>
							</div>
						</div>

						<div class="form-group row justify-content-between">
							<div class="col-md-5">
								<label for="Port_Name">Port <span class="text-danger">*</span></label>
								<input type="text" class="form-control" name="port_name" id="Port_Name" value="{{ old('port_name') }}" placeholder="Click to choose a port" readonly required style="background:#fff;cursor:pointer;" data-toggle="modal" data-target="#port-picker-modal">
							</div>
						</div>

						<div class="modal fade" id="port-picker-modal" tabindex="-1" role="dialog">
							<div class="modal-dialog" role="document">
								<div class="modal-content">
									<div class="modal-header">
										<h5 class="modal-title">Port</h5>
										<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
									</div>
									<div class="modal-body">
										<input type="text" id="port-picker-search" class="form-control mb-2" placeholder="Start typing to get suggestions">
										<div id="port-picker-results" class="list-group" style="max-height:320px; overflow-y:auto;"></div>
									</div>
								</div>
							</div>
						</div>

						<div class="form-group row justify-content-between">
							<div class="col-md-5">
								<label for="eta">ETA</label>
								<input type="text" class="form-control date" name="eta" id="eta" value="{{ old('eta') }}" autocomplete="off">
							</div>
							<div class="col-md-5">
								<label for="etd">ETD</label>
								<input type="text" class="form-control date" name="etd" id="etd" value="{{ old('etd') }}" autocomplete="off">
							</div>
						</div>

						<div class="form-group row">
							<div class="col-md-11">
								<label for="remarks">Remarks</label>
								<textarea class="form-control" name="remarks" id="remarks" rows="4">{{ old('remarks') }}</textarea>
							</div>
						</div>

						<div class="form-group row">
							<div class="col-md-11">
								<div class="form-check">
									<input type="checkbox" class="form-check-input" name="high_priority" id="high_priority" value="1" {{ old('high_priority') ? 'checked' : '' }}>
									<label class="form-check-label" for="high_priority">High priority</label>
								</div>
							</div>
						</div>

						<div class="form-group row">
							<div class="col-md-11 text-right">
								<button type="submit" class="btn btn-success">Save &amp; Next: Add Items</button>
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
	var budgetGroups = {!! $budgetGroups->map(fn($g) => ['id' => $g->id, 'name' => $g->name])->values() !!};

	function renderBudgetGroups(list) {
		if (list.length === 0) {
			$('#budget-group-results').html('<p class="text-muted p-2">No matches.</p>');
			return;
		}
		var html = '';
		list.forEach(function (g) {
			html += '<a href="#" class="list-group-item list-group-item-action budget-group-pick" data-id="' + g.id + '" data-name="' + $('<div>').text(g.name).html() + '">' + $('<div>').text(g.name).html() + '</a>';
		});
		$('#budget-group-results').html(html);
	}

	$('#budget-group-search').on('focus keyup', function () {
		var term = $(this).val().toLowerCase();
		var filtered = budgetGroups.filter(function (g) { return g.name.toLowerCase().indexOf(term) !== -1; });
		renderBudgetGroups(filtered);
	});

	$(document).on('click', '.budget-group-pick', function (e) {
		e.preventDefault();
		$('#budget_group_id').val($(this).data('id'));
		$('#budget-group-search').val($(this).data('name'));
		$('#budget-group-results').empty();
	});

	$(document).on('click', function (e) {
		if (!$(e.target).closest('#budget-group-search, #budget-group-results').length) {
			$('#budget-group-results').empty();
		}
	});

	// Port picker - same search-as-you-type pattern used by Browse Catalog
	// and the catalog item picker, backed by /ports/search (17,500+ seaports,
	// too many for a plain <select>).
	var portSearchTimer = null;

	function loadPorts(term) {
		$('#port-picker-results').html('<p class="text-muted p-2">Loading…</p>');

		$.getJSON('{{url("/ports/search")}}', { q: term }, function (ports) {
			if (ports.length === 0) {
				$('#port-picker-results').html('<p class="text-muted p-2">No matching ports.</p>');
				return;
			}

			var html = '';
			ports.forEach(function (p) {
				html += '<a href="#" class="list-group-item list-group-item-action port-pick" data-label="'
					+ $('<div>').text(p.label).html() + '">' + $('<div>').text(p.label).html() + '</a>';
			});
			$('#port-picker-results').html(html);
		});
	}

	$('#port-picker-modal').on('shown.bs.modal', function () {
		$('#port-picker-search').val('').trigger('focus');
		loadPorts('');
	});

	$('#port-picker-search').on('keyup', function () {
		var term = $(this).val();
		clearTimeout(portSearchTimer);
		portSearchTimer = setTimeout(function () { loadPorts(term); }, 250);
	});

	$(document).on('click', '.port-pick', function (e) {
		e.preventDefault();
		$('#Port_Name').val($(this).data('label'));
		$('#port-picker-modal').modal('hide');
	});
});
</script>
@endsection
