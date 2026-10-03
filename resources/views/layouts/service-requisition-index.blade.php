@extends('layouts.admin-master')
@section('main-content')
<div class="col-lg-6 col-xl-12">
	<div class="card">
		<div class="card-header pv-card-hader">
			<strong class="pptitle">{{ $listTitle ?? 'Service Requisitions' }}</strong>
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

			@php $serverPaged = $requisitions instanceof \Illuminate\Pagination\LengthAwarePaginator; @endphp
			@if($serverPaged)@include('partials.server-list-controls', ['orders' => $requisitions, 'position' => 'top', 'q' => $q ?? '', 'perPage' => $perPageChoice ?? '15'])@endif
			{{-- data-server-paginated: paged and searched by the server, so DataTables
				 keeps its hands off paging/search/sort (see print-pdf-custom.js). --}}
			<table id="example" class="table table-bordered dt-responsive" style="width:100%;" @if($serverPaged) data-server-paginated="1" @endif>
				<thead>
					<th>#</th>
					<th>Req. No</th>
					<th>Type</th>
					<th>Vessel</th>
					<th>Items</th>
					<th>Raised By</th>
					<th>Date</th>
					<th>Stage</th>
					@if(!empty($showRejection))
					<th>Rejected By</th>
					<th>Reason</th>
					@endif
				</thead>
				<tbody>
					@forelse($requisitions as $requisition)
					<tr>
						<td><b class="serial">{{ $serverPaged ? $requisitions->firstItem() + $loop->index : $loop->iteration }}</b></td>
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
							@else
							{{-- Same teal badge as the item requisition lists, for everyone: it used to turn yellow for whoever had
								 an action waiting, so the same requisition looked different
								 to the officer who raised it. --}}
							<span class="badge badge-info">{{ $requisition->currentStageLabel() }}</span>
							@endif
						</td>
						@if(!empty($showRejection))
						<td>{{ $requisition->rejectedBy->name ?? '' }}@if($requisition->rejected_at)<br><small class="text-muted">{{ $requisition->rejected_at->format('d M Y') }}</small>@endif</td>
						<td>{{ $requisition->rejection_reason }}</td>
						@endif
					</tr>
					@empty
					{{-- No placeholder row: a colspan row breaks DataTables' column
						 count, which is what shows "No data available" itself. --}}
					@endforelse
				</tbody>
			</table>
			@if($serverPaged)@include('partials.server-list-controls', ['orders' => $requisitions, 'position' => 'bottom'])@endif
		</div>
	</div>
</div>
@endsection

@section('home-js')
<script>
// DataTables writes its own "No data available in table" into an empty list -
// swap in this list's wording once it has initialised.
$(window).on('load', function () {
	$('#example td.dataTables_empty').text(@json($emptyText ?? 'No service requisitions yet.'));
});
</script>
@endsection
