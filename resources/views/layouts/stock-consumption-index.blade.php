@extends('layouts.admin-master')
@section('main-content')
<div class="col-lg-6 col-xl-12">
	<div class="card">
		<div class="card-header pv-card-hader">
			<strong class="pptitle">Stock Consumption Log</strong>
			@if(in_array(auth()->user()->role->role, ['chief-officer', 'second-engineer']))
			<div class="right-buttons">
				<a href="{{ route('stock-consumption.create') }}" class="btn btn-sm btn-primary"><i class="fas fa-wrench"></i> Log Consumption</a>
			</div>
			@endif
		</div>
		<div class="card-body">
			@php
				// All of these deduct stock identically - see
				// StockService::consume() - this is purely a visual cue for
				// which entries are ordinary use versus a write-off worth a
				// second look.
				$typeBadge = [
					\App\StockConsumption::TYPE_USED => 'badge-success',
					\App\StockConsumption::TYPE_DAMAGED => 'badge-danger',
					\App\StockConsumption::TYPE_EXPIRED => 'badge-warning',
					\App\StockConsumption::TYPE_LOST => 'badge-dark',
					\App\StockConsumption::TYPE_OTHER => 'badge-secondary',
				];
			@endphp
			<table class="table table-bordered dt-responsive" style="width:100%;">
				<thead>
					<th>Date</th>
					<th>Item</th>
					<th>Type</th>
					<th>Qty</th>
					<th>Department</th>
					<th>Purpose</th>
					<th>Requisition</th>
					<th>Recorded by</th>
					<th>Attachments</th>
				</thead>
				<tbody>
					@forelse($consumptions as $c)
					<tr>
						<td>{{ $c->consumed_on->format('d M Y') }}</td>
						<td>{{ $c->item->name ?? '—' }}</td>
						<td><span class="badge {{ $typeBadge[$c->consumption_type] ?? 'badge-secondary' }}">{{ $c->typeLabel() }}</span></td>
						<td>{{ $c->qty }} {{ $c->item->unit ?? '' }}</td>
						<td>{{ $c->department ?: '—' }}</td>
						<td>{{ \Illuminate\Support\Str::limit($c->purpose, 80) }}</td>
						<td>
							@if($c->order)
							<a href="{{ route('view.order.detail', $c->order->id) }}">{{ $c->order->req_no ?: ('#'.$c->order->id) }}</a>
							@else
							—
							@endif
						</td>
						<td>{{ $c->recordedBy->name ?? '—' }}</td>
						<td>
							@forelse($c->attachments as $a)
							<a href="{{ url('/attachments/'.$a->id.'/view') }}" target="_blank" title="{{ $a->title }}"><i class="fas fa-paperclip"></i></a>
							@empty
							—
							@endforelse
						</td>
					</tr>
					@empty
					<tr><td colspan="9" class="text-center">No consumption logged yet.</td></tr>
					@endforelse
				</tbody>
			</table>
			{{ $consumptions->links() }}
		</div>
	</div>
</div>
@endsection
