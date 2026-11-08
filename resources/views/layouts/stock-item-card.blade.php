@extends('layouts.admin-master')
@section('main-content')
<div class="col-lg-6 col-xl-12">
	<div class="card">
		<div class="card-header pv-card-hader">
			<strong class="pptitle">{{ $item->name }}{{ $vessel ? ' — '.$vessel->name : '' }}</strong>
			<div class="right-buttons">
				<a href="{{ route('inventory.stock.item.export', array_merge(['item' => $item->id], request()->only('vessel_id', 'from', 'to'))) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-file-excel"></i> Export</a>
				<a href="{{ route('inventory.stock.item.pdf', array_merge(['item' => $item->id], request()->only('vessel_id', 'from', 'to'))) }}" class="btn btn-outline-secondary btn-sm" target="_blank"><i class="fas fa-file"></i> PDF</a>
			</div>
		</div>
		<div class="card-body">
			<div class="row mb-3">
				<div class="col-md-3"><div class="alert alert-secondary mb-0 text-center"><b>{{ $vesselItem->opening_stock ?? '—' }}</b><div style="font-size:12px;">Opening</div></div></div>
				<div class="col-md-3"><div class="alert alert-secondary mb-0 text-center"><b>{{ $vesselItem->stock_qty ?? 0 }}</b><div style="font-size:12px;">Current Stock</div></div></div>
				<div class="col-md-3"><div class="alert {{ $vesselItem && $vesselItem->isLowStock() ? 'alert-warning' : 'alert-secondary' }} mb-0 text-center"><b>{{ $vesselItem->min_qty ?? '—' }}</b><div style="font-size:12px;">Min Qty{{ $vesselItem && $vesselItem->isLowStock() ? ' (LOW)' : '' }}</div></div></div>
				<div class="col-md-3"><div class="alert alert-secondary mb-0 text-center"><b>{{ $vesselItem->last_supply_qty ?? '—' }}</b><div style="font-size:12px;">Last Supply{{ $vesselItem && $vesselItem->last_supply_date ? ' ('.$vesselItem->last_supply_date->format('d M Y').')' : '' }}</div></div></div>
			</div>
			<div class="row mb-2" style="font-size:12.5px; color:#6b7877;">
				<div class="col-12">
					Article #: <b>{{ $item->article_number ?: '—' }}</b>
					&nbsp;·&nbsp; IMPA: <b>{{ $item->impa_code ?: '—' }}</b>
					&nbsp;·&nbsp; Unit: <b>{{ $item->unit }}</b>
				</div>
			</div>

			<form method="GET" class="form-inline mb-3">
				<input type="hidden" name="vessel_id" value="{{ $vessel->id ?? '' }}">
				<label class="mr-2" for="sc_from">From</label>
				<input type="date" id="sc_from" name="from" class="form-control form-control-sm mr-3" value="{{ $from }}">
				<label class="mr-2" for="sc_to">To</label>
				<input type="date" id="sc_to" name="to" class="form-control form-control-sm mr-3" value="{{ $to }}">
				<button type="submit" class="btn btn-sm btn-primary">Filter</button>
			</form>

			<div class="table-responsive">
				<table class="table table-bordered table-sm">
					<thead>
						<tr>
							<th>Date</th>
							<th>Type</th>
							<th class="text-right">Qty</th>
							<th class="text-right">Balance After</th>
							<th>Source</th>
							<th>Remarks</th>
							<th>By</th>
						</tr>
					</thead>
					<tbody>
						@forelse($movements as $m)
						<tr>
							<td>{{ $m->moved_on->format('d M Y') }}</td>
							<td><span class="badge badge-{{ $m->type === 'consumption' ? 'danger' : ($m->type === 'receipt' ? 'success' : 'secondary') }}">{{ $m->typeLabel() }}</span></td>
							<td class="text-right">{{ $m->qty > 0 ? '+' : '' }}{{ $m->qty }}</td>
							<td class="text-right">{{ $m->balance_after }}</td>
							<td>
								@if($m->order)
								<a href="{{ route('view.order.detail', $m->order_id) }}">{{ $m->order->req_no ?: '#'.$m->order_id }}</a>
								@elseif($m->stockConsumption)
								{{ $m->stockConsumption->typeLabel() }}: {{ \Illuminate\Support\Str::limit($m->stockConsumption->purpose, 50) }}
								@if($m->stockConsumption->department)<br><span class="text-muted" style="font-size:11.5px;">{{ $m->stockConsumption->department }}</span>@endif
								@if($m->pmsJobId)
								<br><a href="{{ route('pms.jobs.show', $m->pmsJobId) }}">View PMS job</a>
								@endif
								@if($m->stockConsumption->attachments->isNotEmpty())
								<br>
								@foreach($m->stockConsumption->attachments as $a)
								<a href="{{ url('/attachments/'.$a->id.'/view') }}" target="_blank" title="{{ $a->title }}"><i class="fas fa-paperclip"></i></a>
								@endforeach
								@endif
								@elseif($m->stockImport)
								<a href="{{ route('stock.history') }}">{{ $m->stockImport->filename }}</a>
								@else
								—
								@endif
							</td>
							<td>{{ \Illuminate\Support\Str::limit($m->remarks, 60) }}</td>
							<td>{{ $m->user->name ?? '—' }}</td>
						</tr>
						@empty
						<tr><td colspan="7" class="text-center">No movements in this date range.</td></tr>
						@endforelse
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>
@endsection
