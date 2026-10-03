@extends('layouts.admin-master')
@section('main-content') 
<style>
.item_name_wrapper {
	position: relative;
}

.item_name_wrapper img.field-loader {
position: absolute;
    width: 25px;
    height: auto;
    right: 3px;
    display: none; 
    top: 50%;
    margin-top: -12.5px;
    padding: 0;
}
</style>
{{-- Only present when HomeController@index built the SSM dashboard - the
	 Pending/Approved/My Approvals pages themselves reuse this same view
	 without passing $stats, so they render exactly as before. Same
	 @isset($stats) guard layouts/ship-home.blade.php uses for its own
	 dashboard row. --}}
@isset($stats)
@include('partials.requisition-stat-cards')
@endisset
{{-- The dashboard (HomeController@index, $stats passed) is stat cards only -
	 no list underneath. Every other page reusing this view (Pending/
	 Approved/My Approvals) never passes $stats, so they're unaffected. --}}
@unless(isset($stats))
<div class="col-lg-6 col-xl-12">
	<div class="card">
		<div class="card-header pv-card-hader">
			{{-- Was comparing the Role MODEL to a string ("role==") instead of
				 role->role, so this was always false and the vessel name never
				 rendered - every ship user silently fell to the plain title
				 below. --}}
			@if(auth()->user()->role->role=='master'||
			auth()->user()->role->role=='chief-officer'||
			auth()->user()->role->role=='chief-engineer'||
			auth()->user()->role->role=='second-engineer')
						<strong class="pptitle">
				Requisition List of <span style="color:red;display: inline-block;padding-left: 5px;"> {{auth()->user()->role->vessel->name}} </span>
			</strong>
			@else
			<strong class="pptitle">
				{{ $listTitle ?? 'Requisition List' }}
			</strong>
			@endif

			<div class="right-buttons">
				
				<button class="btn btn-info btn-bvprint" onClick="order_list();">
					<i class="fa fa-print"></i>  Print
				</button>
			</div>

		</div>
		<!-- card-hader -->
		<!-- card-body -->
		<div class="card-body">
			@php $serverPaged = $orders instanceof \Illuminate\Pagination\LengthAwarePaginator; @endphp
			@if(auth()->user()->role->vessel_id==null)
			@php
				$shipName = !empty($ship_id) ? optional($vessels->firstWhere('id', $ship_id))->name : null;
				$filtersActive = !empty($ship_id) || !empty($cat_id) || !empty($from_date) || !empty($end_date);
			@endphp
			{{-- Search the requisition list. Dates match on the requisition date
				 (the "Req. Date" column): both given = that range, inclusive; one
				 given = that exact day. --}}
			{{-- A GET to the list itself when the list is server-paginated, so the
				 filters, the text search and "Show entries" all combine in one URL;
				 the older POST route is only still used by lists paged in the browser. --}}
			<form id="order_search_form" class="req-filter" @if($serverPaged) method="get" action="{{ url()->current() }}" @else method="post" action="{{url('/search/order')}}" @endif>
				@if(! $serverPaged)@csrf @else
				@if(!empty($q))<input type="hidden" name="q" value="{{ $q }}">@endif
				@if(!empty($perPageChoice) && $perPageChoice !== '15')<input type="hidden" name="per_page" value="{{ $perPageChoice }}">@endif
				@endif
				<div class="req-filter-fields">
					<div class="req-filter-field">
						<label for="ship_name">Vessel</label>
						<select name="ship_id" class="form-control" id="ship_name">
							<option value="">All vessels</option>
							@if(!empty($vessels))
							@foreach($vessels as $vessel)
							<option value="{{$vessel->id}}" {{(!empty($ship_id) && $vessel->id == $ship_id) ?'selected':''}} >{{$vessel->name}}</option>
							@endforeach
							@endif
						</select>
					</div>
					<div class="req-filter-field">
						<label for="cate_name">Category</label>
						<select name="cat_id" class="form-control" id="cate_name">
							<option value="">All categories</option>
							@if(!empty($categories))
							{{-- Only catalog categories (is_catalog = 1) - the same list Browse Catalog offers. --}}
							@foreach($categories->where('is_catalog', true) as $cat)
							<option value="{{$cat->id}}" {{(!empty($cat_id)&&$cat->id==$cat_id)?'selected':''}}>{{$cat->name}}</option>
							@endforeach
							@endif
						</select>
					</div>
					<div class="req-filter-field req-filter-date">
						<label for="from_date">From date</label>
						<div class="req-filter-input">
							<input type="text" id="from_date" class="form-control date" value="{{!empty($from_date)?$from_date:''}}" name="from_date" placeholder="YYYY-MM-DD" autocomplete="off">
						</div>
					</div>
					<div class="req-filter-field req-filter-date">
						<label for="end_date">To date</label>
						<div class="req-filter-input">
							<input type="text" id="end_date" value="{{!empty($end_date)?$end_date:''}}" class="form-control date" name="end_date" placeholder="YYYY-MM-DD" autocomplete="off">
						</div>
					</div>
					<div class="req-filter-actions">
						<button type="submit" class="btn btn-primary bsc-search"><i class="fas fa-search"></i> Search</button>
						@if($filtersActive)
						<a href="{{ url()->current() }}" class="btn btn-outline-secondary">Clear</a>
						@endif
					</div>
				</div>
				@if($filtersActive)
				<div class="req-filter-summary">
					<strong>{{ count($orders) }}</strong> {{ count($orders) === 1 ? 'requisition' : 'requisitions' }} found
					@if($shipName)<span class="req-chip">{{ $shipName }}</span>@endif
					@if(!empty($cat_id) && !empty($category))<span class="req-chip">{{ $category->name }}</span>@endif
					@if(!empty($from_date) && !empty($end_date))<span class="req-chip">{{ $from_date }} &rarr; {{ $end_date }}</span>
					@elseif(!empty($from_date) || !empty($end_date))<span class="req-chip">on {{ $from_date ?: $end_date }}</span>@endif
				</div>
				@endif
			</form>
			<style>
			.req-filter{ background:#f6f9f9; border:1px solid #dfe5e4; border-radius:12px; padding:16px 18px; margin-bottom:18px; }
			.req-filter-fields{ display:flex; flex-wrap:wrap; align-items:flex-end; gap:14px 16px; }
			.req-filter-field{ flex:1 1 200px; min-width:160px; }
			.req-filter-date{ flex:0 1 170px; min-width:150px; }
			.req-filter-field label{ display:block; margin:0 0 5px; font-size:11px; font-weight:600; letter-spacing:.07em; text-transform:uppercase; color:#6b7877; }
			.req-filter .form-control{ height:38px; font-size:14px; background-color:#fff; }
			.req-filter-input{ position:relative; }
			.req-filter-actions{ display:flex; gap:8px; margin-left:auto; }
			.req-filter-actions .btn{ height:38px; padding:0 18px; display:inline-flex; align-items:center; justify-content:center; gap:6px; }
			.req-filter-summary{ margin-top:14px; padding-top:12px; border-top:1px dashed #d3dcdb; font-size:13.5px; color:#44524f; display:flex; flex-wrap:wrap; align-items:center; gap:6px 8px; }
			.req-chip{ background:#e3f1f1; color:#0a5d57; border-radius:999px; padding:2px 11px; font-size:12.5px; font-weight:500; }
			@media (max-width: 575px){ .req-filter-field, .req-filter-date{ flex:1 1 100%; } .req-filter-actions{ width:100%; margin-left:0; } .req-filter-actions .btn{ flex:1; } }
			</style>
			@endif

			@if($serverPaged)@include('partials.server-list-controls', ['orders' => $orders, 'position' => 'top', 'q' => $q ?? '', 'perPage' => $perPageChoice ?? '15', 'withSearch' => auth()->user()->role->vessel_id !== null, 'keep' => array_filter(['ship_id' => $ship_id ?? null, 'cat_id' => $cat_id ?? null, 'from_date' => $from_date ?? null, 'end_date' => $end_date ?? null])])@endif
			{{-- data-server-paginated: rows are paged by the server, so DataTables
				 must leave this table alone (see admin-master / dataForm.js). --}}
			<table id="example" class="table table-bordered dt-responsive" style="width: 100%;" @if($serverPaged) data-server-paginated="1" @endif>
				<thead>
					<th>#</th>
					<th>Req. No</th>
					<th>Category</th>
					<th>Vessel Name</th>
					<th>Req. Date</th>
					<th>Port</th>
					<th>Stage</th>
					<th>Created By</th>
					<!-- <th class="action">Action</th> -->
				</thead>
				<tbody>
					@if(!empty($orders))
					@foreach($orders as $order)
					<tr id="order-{{$order->id}}">
						<td class="sl_no"> <b class="serial"> {{ $serverPaged ? $orders->firstItem() + $loop->index : $loop->iteration }}</b> </td>
						<td>
							<a href="{{url('/order/detail/'.$order->id)}}" class="req_no_link">
								{{!empty($order->req_no)?$order->req_no:''}}
							</a>
						</td>
						<td>{{!empty($order->category->name)?$order->category->name:''}}</td>
						<td>{{!empty($order->vessel->name)?$order->vessel->name:''}}</td>
						<td>{{!empty($order->req_date)?$order->req_date:''}}</td>
						<td>{{!empty($order->port_name)?$order->port_name:''}}</td>
						<td><span class="badge badge-info">{{ $order->currentStageLabel() }}</span></td>
						<td>{{ $order->creator->name ?? '' }}</td>
						<!-- <td class="action">
							<button class="btn btn-info edit-order" data-id="{{$order->id}}" data-name="{{$order->name}}" data-toggle="modal" data-target="#edit_template_modal"><i class="fas fa-edit"></i></button>
							<button class="btn btn-danger delete-order" data-id="{{$order->id}}" data-toggle="modal" data-target="#delete_template_modal"><i class="fas fa-trash-alt"></i></button>
						</td> -->
					</tr>
					@endforeach
					@endif
				</tbody>
			</table>
			@if($serverPaged)@include('partials.server-list-controls', ['orders' => $orders, 'position' => 'bottom'])@endif
		</div>
	</div>
</div>
@endunless

<!-- Edit order Template Modal -->
<div class="modal fade" id="edit_template_modal" tabindex="-1" role="dialog" aria-labelledby="" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header" style="background-color: #579eb9; padding: 10px 0;">
				<legend style="color:#fff; text-align: center; margin-bottom:0;"><i class="far fa-edit"></i> &nbsp; Update order </legend>
				<button style="color: #fff;" type="button" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true" style="padding:10px 10px 0 0;">&times;</span>
				</button>
			</div>
			<form id="order_edit_form" class="form">
				@csrf
				<!-- Modal body -->
				<div class="modal-body">
					<div class="row justify-content-center form-group">
						<div class="col-md-11 alert alert-danger alert-dismissible fade show form_error" style="display:none" role="alert">
							<strong>Error Submission!!</strong> Please correct following info and resubmit. 
							<label>    </label>
							<button type="button" class="close close_error_alert">
								<span aria-hidden="true">&times;</span>
							</button>
						</div>
					</div>

					<div class="row justify-content-center form-group">
						<div class="col-md-3">
							<label for="Category_Name">Category Name: </label>
						</div>
						<div class="col-md-7">
							<select class="form-control Category_Name" name="Category_Name">
								<option selected="" value="" class='cat_opt'>-- Choose Category --</option>
								@if(!empty($categories))
								@foreach($categories as $category)
								<option value="{{$category->id}}" class='cat_opt'>{{$category->name}}</option>
								@endforeach
								@endif
							</select>
						</div>
					</div>

					<div class="row justify-content-center form-group">
						<div class="col-md-3">
							<label for="order_Name">order Name: </label>
						</div>
						<div class="col-md-7">
							<input type="text" class="form-control order_Name" name="order_Name">
						</div>
					</div>
					<div class="row justify-content-center form-group">
						<div class="col-md-3">
							<label for="impa_code">Impa Code No: </label>
						</div>
						<div class="col-md-7">
							<input type="number" class="form-control impa_code" name="Impa_Code_No">
						</div>
					</div>
					<div class="row justify-content-center form-group">
						<div class="col-md-3">
							<label for="measurement_unit">Measurement Unit: </label>
						</div>
						<div class="col-md-7">
							<input type="text" class="form-control measurement_unit" name="Measurement_Unit">
							<input type="hidden" class="form-control order_id" name="order_id">
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-danger" data-dismiss="modal">Cancel</button>
					<button type="submit" class="btn btn-primary">Update order</button>
				</div>
			</form>
		</div>
	</div>
</div>

<!-- logo-base64 for pdf page -->
@include('pdf.logo-base64')
<!-- logo-base64 for pdf page -->
@endsection