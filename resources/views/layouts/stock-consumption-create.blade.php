@extends('layouts.admin-master')
@section('main-content')
<div class="order-section container">
	<div class="row">
		<div class="col-xl-12">
			<div class="card order-card">
				<div class="card-header first">
					<strong class="pptitle">Log stock consumption for &nbsp;
						<span style="color:red;">{{ $vessel->name }}</span>
					</strong>
					<div class="right-button"><a href="{{ route('stock-consumption.index') }}">View Consumption Log</a></div>
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
					<div id="consumption-error" class="alert alert-danger" style="display:none;"></div>

					<form id="consumption-form">
						@csrf

						<div class="form-group row">
							<div class="col-md-8">
								<label>Item <span class="text-danger">*</span></label>
								<input type="text" class="form-control" id="item-search" placeholder="Start typing an item already on board" autocomplete="off">
								<input type="hidden" name="item_id" id="item_id">
								<div id="item-results" class="list-group" style="max-height:260px; overflow-y:auto; position:relative; z-index:5;"></div>
								<small class="form-text text-muted" id="item-stock-hint"></small>
							</div>
						</div>

						<div class="form-group row justify-content-between">
							<div class="col-md-5">
								<label for="consumption_type">Consumption type <span class="text-danger">*</span></label>
								<select class="form-control" name="consumption_type" id="consumption_type" required>
									<option value="">Choose…</option>
									@foreach($types as $value => $label)
									<option value="{{ $value }}">{{ $label }}</option>
									@endforeach
								</select>
							</div>
							<div class="col-md-5">
								<label for="qty">Quantity <span class="text-danger">*</span></label>
								<input type="number" min="1" class="form-control" name="qty" id="qty" required>
							</div>
						</div>

						<div class="form-group row justify-content-between">
							<div class="col-md-5">
								<label for="consumed_on">Date consumed <span class="text-danger">*</span></label>
								<input type="text" class="form-control date" name="consumed_on" id="consumed_on" value="{{ date('Y-m-d') }}" autocomplete="off" required>
							</div>
							<div class="col-md-5">
								<label for="department">Department</label>
								<select class="form-control" name="department" id="department">
									<option value="">Choose…</option>
									<option value="Deck" {{ $defaultDepartment === 'Deck' ? 'selected' : '' }}>Deck</option>
									<option value="Engine" {{ $defaultDepartment === 'Engine' ? 'selected' : '' }}>Engine</option>
								</select>
							</div>
						</div>

						<div class="form-group row">
							<div class="col-md-11">
								<label for="purpose">Purpose / reason <span class="text-danger">*</span></label>
								<textarea class="form-control" name="purpose" id="purpose" rows="3" placeholder="e.g. Used during main engine overhaul, damaged during handling, expired before use" required></textarea>
							</div>
						</div>

						<div class="form-group row">
							<div class="col-md-8">
								<label for="order_id">Originally requisitioned under</label>
								<select class="form-control" name="order_id" id="order_id">
									<option value="">Not linked to a specific requisition</option>
									@foreach($recentOrders as $order)
									<option value="{{ $order->id }}">{{ $order->req_no ?: ('Requisition #'.$order->id) }} — {{ $order->req_date }}</option>
									@endforeach
								</select>
							</div>
						</div>

						<div class="form-group row">
							<div class="col-md-11">
								<label for="remarks">Remarks</label>
								<textarea class="form-control" name="remarks" id="remarks" rows="2" placeholder="Optional - anything else worth recording"></textarea>
							</div>
						</div>

						<div class="form-group row">
							<div class="col-md-11">
								<label for="attachments">Attachments</label>
								<input type="file" class="form-control-file" name="attachments[]" id="attachments" multiple accept="image/*,application/pdf">
								<small class="form-text text-muted">e.g. a photo of the damaged item, an expiry-date label, a defect report.</small>
							</div>
						</div>

						<div class="form-group row">
							<div class="col-md-11 text-right">
								<a href="{{ url('/home') }}" class="btn btn-outline-secondary">Cancel</a>
								<button type="submit" class="btn btn-success" id="consumption-submit">Log Consumption</button>
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
	var selectedStock = null;

	function renderItems(list) {
		if (list.length === 0) {
			$('#item-results').html('<p class="text-muted p-2">No matching items on board.</p>');
			return;
		}
		var html = '';
		list.forEach(function (it) {
			var label = $('<div>').text(it.name + (it.article_number ? ' (' + it.article_number + ')' : '')).html();
			html += '<a href="#" class="list-group-item list-group-item-action item-pick" '
				+ 'data-id="' + it.id + '" data-name="' + $('<div>').text(it.name).html() + '" '
				+ 'data-unit="' + $('<div>').text(it.unit || '').html() + '" data-stock="' + it.stock_qty + '">'
				+ label + ' <span class="text-muted">— ' + it.stock_qty + ' ' + (it.unit || '') + ' in stock</span></a>';
		});
		$('#item-results').html(html);
	}

	var searchTimer = null;
	$('#item-search').on('focus keyup', function () {
		var term = $(this).val();
		clearTimeout(searchTimer);
		searchTimer = setTimeout(function () {
			$.getJSON('{{ route("stock-consumption.search-items") }}', { q: term }, renderItems);
		}, 200);
	});

	$(document).on('click', '.item-pick', function (e) {
		e.preventDefault();
		var $this = $(this);
		$('#item_id').val($this.data('id'));
		$('#item-search').val($this.data('name'));
		$('#item-results').empty();
		selectedStock = parseInt($this.data('stock'), 10);
		$('#item-stock-hint').text('Currently ' + selectedStock + ' ' + $this.data('unit') + ' in stock.');
		$('#qty').attr('max', selectedStock);
	});

	$(document).on('click', function (e) {
		if (!$(e.target).closest('#item-search, #item-results').length) {
			$('#item-results').empty();
		}
	});

	$('#consumption-form').on('submit', function (e) {
		e.preventDefault();

		if (!$('#item_id').val()) {
			swal('Choose an item', 'Please pick an item from the list before logging consumption.', 'warning');
			return;
		}
		if (selectedStock !== null && parseInt($('#qty').val(), 10) > selectedStock) {
			swal('Not enough in stock', 'Only ' + selectedStock + ' currently in stock - cannot log consuming more than that.', 'warning');
			return;
		}

		$('#consumption-error').hide().text('');
		$('#consumption-submit').prop('disabled', true).text('Saving…');

		var formData = new FormData(this);

		$.ajax({
			url: '{{ route("stock-consumption.store") }}',
			type: 'POST',
			data: formData,
			processData: false,
			contentType: false,
			dataType: 'json'
		})
		.done(function (response) {
			swal('Logged!', response.message, 'success').then(function () {
				window.location.href = response.redirect || '{{ route("stock-consumption.index") }}';
			});
		})
		.fail(function (xhr) {
			var message = (xhr.responseJSON && xhr.responseJSON.message) || 'Something went wrong.';
			var errors = (xhr.responseJSON && xhr.responseJSON.errors) || null;
			if (errors) {
				message = Object.values(errors).map(function (e) { return e[0]; }).join(' ');
			}
			$('#consumption-error').text(message).show();
			$('#consumption-submit').prop('disabled', false).text('Log Consumption');
		});
	});
});
</script>
@endsection
