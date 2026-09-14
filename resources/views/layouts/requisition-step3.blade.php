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
						<div class="col-md-9"><strong>Reason of Requisition:</strong><br>{{ $order->reason }}</div>
					</div>

					<hr>

					{{-- Same columns as step 2's Add Items table, minus the inputs -
						 this is the same requisition, just no longer editable here
						 (Back still reopens step 2 for that). --}}
					<div class="table-responsive">
					<table class="table table-striped table-bordered" style="width:100%">
						<thead>
							<tr>
								<th>#</th>
								<th>Article #</th>
								<th>Item Name with full Specifications</th>
								<th>Unit</th>
								<th>Opening Stock</th>
								<th>Quantity of Last Supply</th>
								<th>Date of Last Supply</th>
								<th>In Stock</th>
								<th>Required</th>
								<th>Attachments</th>
							</tr>
						</thead>
						<tbody>
							@forelse($order->orderItems as $orderItem)
							<tr>
								<td>{{ $loop->iteration }}</td>
								<td>{{ $orderItem->item->article_number ?? $orderItem->item->impa_code }}</td>
								<td>{{ $orderItem->item->name }}</td>
								<td>{{ $orderItem->item->unit }}</td>
								<td>{{ $orderItem->opening_stock ?? '—' }}</td>
								<td>{{ $orderItem->last_supply_qty ?? '—' }}</td>
								<td>{{ $orderItem->last_supply_date ? \Carbon\Carbon::parse($orderItem->last_supply_date)->format('d M Y') : '—' }}</td>
								<td>{{ $liveStock[$orderItem->item_id]['stock_qty'] ?? 0 }}</td>
								<td>{{ $orderItem->item_qty }}</td>
								<td>
									@if($orderItem->attachments->isNotEmpty())
									<button type="button" class="btn btn-link p-0 see-attachments-link" data-toggle="modal" data-target="#view-attachments-modal"
										data-item-name="{{ $orderItem->item->name }}"
										data-attachments="{{ $orderItem->attachments->map(fn($a) => ['title'=>$a->title,'kind'=>$a->kind,'uploaded_by'=>$a->uploader->name ?? '','uploaded_at'=>optional($a->pivot->created_at)->diffForHumans() ?? $a->created_at->diffForHumans(),'view_url'=>url('/attachments/'.$a->id.'/view')])->toJson() }}">
										See attachments ({{ $orderItem->attachments->count() }})
									</button>
									@else
									<span class="text-muted" style="font-size:12px;">&mdash;</span>
									@endif
								</td>
							</tr>
							@empty
							<tr><td colspan="10" class="text-center">No items added.</td></tr>
							@endforelse
						</tbody>
					</table>
					</div>

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

<!-- See Attachments modal - same pattern as the approver's order-detail page,
	 populated straight from the clicked link's data-attachments attribute. -->
<div class="modal fade" id="view-attachments-modal" tabindex="-1" role="dialog">
	<div class="modal-dialog" role="document" style="max-width:460px;">
		<div class="modal-content">
			<div class="modal-header">
				<div>
					<div style="font-size:11.5px;color:#6b7a82;font-weight:600;margin-bottom:2px;">Attachments for</div>
					<h5 class="modal-title" id="view-attachments-item-name">&nbsp;</h5>
				</div>
				<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body" id="view-attachments-list" style="display:flex; flex-direction:column; gap:8px;"></div>
		</div>
	</div>
</div>
<style>
	.att-list-row{
		display:flex; align-items:center; gap:12px; border:1px solid #e3e8e7; border-radius:10px;
		padding:10px 12px; background:#fff; text-decoration:none; width:100%; text-align:left;
	}
	.att-list-row:hover{ border-color:#7fb8c2; background:#f4f6f6; text-decoration:none; }
	.att-list-thumb{
		width:38px; height:38px; border-radius:8px; display:flex; align-items:center; justify-content:center;
		color:#fff; font-size:9px; font-weight:800; flex-shrink:0;
	}
	.att-list-row .info{ flex:1; min-width:0; }
	.att-list-row .t{ font-size:13.5px; font-weight:600; color:#17242b; }
	.att-list-row .m{ font-size:11.5px; color:#6b7a82; margin-top:1px; }
	.att-list-row .view{ font-size:12px; font-weight:700; color:#005866; flex-shrink:0; }
</style>
@endsection

@section('home-js')
<script>
$(function () {
	function esc(value) {
		return $('<div>').text(value === null || value === undefined ? '' : value).html();
	}

	var thumbColor = { image: '#2f6fed', pdf: '#e5486b', doc: '#5b4fd6' };

	$(document).on('click', '.see-attachments-link', function () {
		$('#view-attachments-item-name').text($(this).data('item-name'));
		var attachments = $(this).data('attachments') || [];
		$('#view-attachments-list').html(attachments.map(function (a) {
			var label = a.kind === 'image' ? 'IMG' : a.kind.toUpperCase();
			return '<a class="att-list-row" href="' + a.view_url + '" target="_blank" rel="noopener">'
				+ '<div class="att-list-thumb" style="background:' + thumbColor[a.kind] + ';">' + label + '</div>'
				+ '<div class="info"><div class="t">' + esc(a.title) + '</div>'
				+ '<div class="m">Attached by ' + esc(a.uploaded_by) + ' &middot; ' + esc(a.uploaded_at) + '</div></div>'
				+ '<span class="view">View</span>'
				+ '</a>';
		}).join(''));
	});

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
