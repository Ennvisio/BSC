@extends('layouts.admin-master')
@section('main-content')
<div class="col-lg-6 col-xl-12">
	<div class="card">
		<div class="card-header pv-card-hader">
			<strong class="pptitle">Service Requisitions</strong>
			@if(in_array(auth()->user()->role->role, ['chief-officer', 'second-engineer']))
			<div class="right-buttons">
				<a href="{{ route('service-requisition.create') }}" class="btn btn-primary"><i class="fas fa-plus-square"></i> Add Service Requisition</a>
			</div>
			@endif
		</div>
		<div class="card-body">
			@if(session('message'))
			<div class="alert alert-info">{{ session('message') }}</div>
			@endif

			<table id="example" class="table table-bordered dt-responsive" style="width:100%;">
				<thead>
					<th>#</th>
					<th>Req. No</th>
					<th>Type</th>
					<th>Vessel</th>
					<th>Items</th>
					<th>Raised By</th>
					<th>Date</th>
					<th>Stage</th>
				</thead>
				<tbody>
					@forelse($requisitions as $requisition)
					<tr>
						<td><b class="serial">{{ $loop->iteration }}</b></td>
						{{-- The req no IS the way in - no separate View button. --}}
						<td><a href="{{ route('service-requisition.show', $requisition->id) }}">{{ $requisition->req_no }}</a></td>
						<td>{{ $requisition->typeLabel() }}</td>
						<td>{{ $requisition->vessel->name ?? '' }}</td>
						<td>{{ $requisition->items->count() }}</td>
						<td>{{ $requisition->creator->name ?? '' }}</td>
						<td>{{ optional($requisition->req_date)->format('d M Y') }}</td>
						<td>
							@if($requisition->isRejected())
							<span class="badge badge-danger">Rejected</span>
							@elseif(in_array($requisition->id, $pendingIds))
							<span class="badge badge-warning">{{ $requisition->currentStageLabel() }}</span>
							@else
							<span class="badge badge-secondary">{{ $requisition->currentStageLabel() }}</span>
							@endif
						</td>
					</tr>
					@empty
					<tr><td colspan="8" class="text-center">No service requisitions yet.</td></tr>
					@endforelse
				</tbody>
			</table>
		</div>
	</div>
</div>
@endsection
