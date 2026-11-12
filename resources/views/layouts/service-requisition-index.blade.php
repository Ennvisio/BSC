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
			@isset($filters)
			@php
				$activeFilters = array_filter($filters);
				$shipName = !empty($filters['ship_id']) ? optional($filterVessels->firstWhere('id', $filters['ship_id']))->name : null;
				$found = $serverPaged ? $requisitions->total() : count($requisitions);
			@endphp
			{{-- Same panel as the item requisition list. A GET to this same list,
				 so the dashboard's ?status=, the text search and "Show entries"
				 all ride along with it. Dates match on the requisition date: both
				 given = that range, inclusive; one given = that exact day. --}}
			<form id="service_search_form" class="req-filter" method="get" action="{{ url()->current() }}">
				@if(request()->filled('status'))<input type="hidden" name="status" value="{{ request()->query('status') }}">@endif
				@if(!empty($q))<input type="hidden" name="q" value="{{ $q }}">@endif
				@if(!empty($perPageChoice) && $perPageChoice !== '15')<input type="hidden" name="per_page" value="{{ $perPageChoice }}">@endif
				<div class="req-filter-fields">
					<div class="req-filter-field">
						<label for="srv_ship_id">Vessel</label>
						<select name="ship_id" class="form-control" id="srv_ship_id">
							<option value="">All vessels</option>
							@foreach($filterVessels as $vessel)
							<option value="{{ $vessel->id }}" {{ ($filters['ship_id'] ?? null) == $vessel->id ? 'selected' : '' }}>{{ $vessel->name }}</option>
							@endforeach
						</select>
					</div>
					<div class="req-filter-field">
						<label for="srv_service_type">Type</label>
						<select name="service_type" class="form-control" id="srv_service_type">
							<option value="">All types</option>
							@foreach($serviceTypes as $value => $label)
							<option value="{{ $value }}" {{ ($filters['service_type'] ?? null) === $value ? 'selected' : '' }}>{{ $label }}</option>
							@endforeach
						</select>
					</div>
					<div class="req-filter-field req-filter-date">
						<label for="srv_from_date">From date</label>
						<div class="req-filter-input">
							<input type="text" id="srv_from_date" class="form-control date" value="{{ $filters['from_date'] ?? '' }}" name="from_date" placeholder="YYYY-MM-DD" autocomplete="off">
						</div>
					</div>
					<div class="req-filter-field req-filter-date">
						<label for="srv_end_date">To date</label>
						<div class="req-filter-input">
							<input type="text" id="srv_end_date" class="form-control date" value="{{ $filters['end_date'] ?? '' }}" name="end_date" placeholder="YYYY-MM-DD" autocomplete="off">
						</div>
					</div>
					<div class="req-filter-actions">
						<button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
						@if($activeFilters)
						<a href="{{ url()->current() }}{{ request()->filled('status') ? '?status='.urlencode(request()->query('status')) : '' }}" class="btn btn-outline-secondary">Clear</a>
						@endif
					</div>
				</div>
				@if($activeFilters)
				<div class="req-filter-summary">
					<strong>{{ $found }}</strong> {{ $found === 1 ? 'requisition' : 'requisitions' }} found
					@if($shipName)<span class="req-chip">{{ $shipName }}</span>@endif
					@if(!empty($filters['service_type']))<span class="req-chip">{{ $serviceTypes[$filters['service_type']] }}</span>@endif
					@if(!empty($filters['from_date']) && !empty($filters['end_date']))<span class="req-chip">{{ $filters['from_date'] }} &rarr; {{ $filters['end_date'] }}</span>
					@elseif(!empty($filters['from_date']) || !empty($filters['end_date']))<span class="req-chip">on {{ $filters['from_date'] ?: $filters['end_date'] }}</span>@endif
				</div>
				@endif
			</form>
			@include('partials.req-filter-styles')
			@endisset
			@if($serverPaged)@include('partials.server-list-controls', ['orders' => $requisitions, 'position' => 'top', 'q' => $q ?? '', 'perPage' => $perPageChoice ?? '15', 'keep' => array_filter(($filters ?? []) + ['status' => request()->query('status')])])@endif
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
