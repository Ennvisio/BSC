@extends('layouts.admin-master')
@section('main-content')
<div class="order-section container">
	<div class="row">
		<div class="col-xl-12">
			<div class="card order-card">
				<div class="card-header first">
					<strong class="pptitle">Review &amp; Submit &nbsp;
						<span style="color:red;">{{ $order->vessel->name }}</span>
					</strong>
					<div class="right-button">Step 3 of 3 — Review</div>
				</div>
				<div class="card-body">
					@if(session('message'))
					<div class="alert alert-info">{{ session('message') }}</div>
					@endif

					<div class="row mb-3">
						<div class="col-md-3"><strong>Requisition No:</strong><br>Assigned on submit</div>
						<div class="col-md-3"><strong>Title:</strong><br>{{ $order->title }}</div>
						<div class="col-md-3"><strong>Budget Group:</strong><br>{{ $order->budgetGroup->name ?? '' }}</div>
						<div class="col-md-3"><strong>Department:</strong><br>{{ $order->department }}</div>
					</div>
					<div class="row mb-3">
						<div class="col-md-3"><strong>Category:</strong><br>{{ $order->category->name ?? '' }}</div>
						<div class="col-md-3"><strong>Port:</strong><br>{{ $order->port_name }}</div>
						<div class="col-md-3"><strong>ETA:</strong><br>{{ $order->eta ?? '—' }}</div>
						<div class="col-md-3"><strong>ETD:</strong><br>{{ $order->etd ?? '—' }}</div>
					</div>
					<div class="row mb-3">
						<div class="col-md-3">
							<strong>High Priority:</strong><br>
							@if($order->high_priority)
							<span class="badge badge-danger">Yes</span>
							@else
							<span class="badge badge-secondary">No</span>
							@endif
						</div>
						<div class="col-md-9"><strong>Remarks:</strong><br>{{ $order->remarks ?? '—' }}</div>
					</div>

					<hr>

					<table class="table table-striped table-bordered" style="width:100%">
						<thead>
							<tr>
								<th>#</th>
								<th>Article #</th>
								<th>Item Name</th>
								<th>Unit</th>
								<th>Req. Quantity</th>
							</tr>
						</thead>
						<tbody>
							@forelse($order->orderItems as $orderItem)
							<tr>
								<td>{{ $loop->iteration }}</td>
								<td>{{ $orderItem->item->article_number ?? $orderItem->item->impa_code }}</td>
								<td>{{ $orderItem->item->name }}</td>
								<td>{{ $orderItem->item->unit }}</td>
								<td>{{ $orderItem->item_qty }}</td>
							</tr>
							@empty
							<tr><td colspan="5" class="text-center">No items added.</td></tr>
							@endforelse
						</tbody>
					</table>

					<form method="POST" action="{{ route('requisition.submit', $order) }}" id="requisition-submit-form">
						@csrf
						<div class="form-group row">
							<div class="col-md-12 text-right">
								<a href="{{ route('requisition.step2', $order) }}" class="btn btn-srd-outline"><i class="fas fa-arrow-left"></i> Back</a>
								<button type="submit" class="btn btn-success"><i class="fas fa-check-circle"></i> Submit for Approval</button>
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
	// Submitting is the point of no return - the requisition leaves the vessel
	// and enters the approval chain, and the wizard can't be reopened
	// afterwards. Confirm first, then let the form through.
	var confirmed = false;

	$('#requisition-submit-form').on('submit', function (e) {
		if (confirmed) {
			return true;
		}

		e.preventDefault();
		var form = this;

		swal({
			title: 'Submit this requisition?',
			text: 'It will be sent for approval and can no longer be edited.',
			type: 'warning',
			showCancelButton: true,
			confirmButtonColor: '#28a745',
			cancelButtonColor: '#6c757d',
			confirmButtonText: 'Yes, submit it',
			cancelButtonText: 'No, keep editing',
		}).then(function (result) {
			// SweetAlert2 resolves on dismiss as well as on confirm, so check
			// which it was - otherwise cancelling would submit anyway.
			var accepted = result === true || (result && result.value);

			if (accepted) {
				confirmed = true;
				form.submit();
			}
		});

		return false;
	});
});
</script>
@endsection
