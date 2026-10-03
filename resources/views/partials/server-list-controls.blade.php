{{--
	Search box (top) and pager (bottom) for a requisition list that is
	paginated on the server instead of by DataTables. Takes $orders (a
	LengthAwarePaginator), $position ('top' | 'bottom') and optionally $q.
	The search is a plain GET (?q=) so it matches across ALL pages, which
	DataTables' own box couldn't once only 15 rows are on the page.
--}}
@if($position === 'top')
<form method="get" action="{{ url()->current() }}" class="srv-list-search">
	{{-- $withSearch = false where the page already has its own filter panel with
		a Search button above (All Requisitions): only "Show entries" is left. --}}
	@if($withSearch ?? true)
	<div class="srv-list-search-box">
		<i class="fas fa-search"></i>
		<input type="search" name="q" value="{{ $q ?? '' }}" class="form-control" placeholder="Search req. no, category, vessel, port, created by…" autocomplete="off">
	</div>
	@elseif(!empty($q))
	<input type="hidden" name="q" value="{{ $q }}">
	@endif
	{{-- Filters already applied (vessel, category, dates) ride along so a text
		search or a new page size narrows them rather than dropping them. --}}
	@foreach(($keep ?? []) as $name => $value)
	<input type="hidden" name="{{ $name }}" value="{{ $value }}">
	@endforeach
	@if($withSearch ?? true)
	<button type="submit" class="btn btn-primary">Search</button>
	@endif
	@if(($withSearch ?? true) && !empty($q))
	<a href="{{ url()->current() }}?{{ http_build_query(array_merge($keep ?? [], ['per_page' => $perPage ?? '15'])) }}" class="btn btn-outline-secondary">Clear</a>
	@endif
	{{-- Rows per page. Changing it re-submits the form, so the search text
		 stays and the list starts again from page 1. --}}
	<label class="srv-list-size">Show
		<select name="per_page" class="form-control" onchange="this.form.submit()">
			@foreach(['15' => '15', '25' => '25', '50' => '50', '100' => '100', '200' => '200', 'all' => 'All'] as $value => $label)
			<option value="{{ $value }}" {{ (string) ($perPage ?? '15') === (string) $value ? 'selected' : '' }}>{{ $label }}</option>
			@endforeach
		</select> entries
	</label>
</form>
<style>
.srv-list-search{ display:flex; gap:8px; align-items:center; margin-bottom:14px; flex-wrap:wrap; }
.srv-list-search-box{ position:relative; flex:1 1 280px; max-width:480px; }
.srv-list-search-box i{ position:absolute; left:11px; top:50%; transform:translateY(-50%); color:#93a0a0; font-size:13px; pointer-events:none; }
.srv-list-search-box .form-control{ padding-left:32px; height:38px; }
.srv-list-search .btn{ height:38px; display:inline-flex; align-items:center; }
.srv-list-size{ margin:0 0 0 auto; display:flex; align-items:center; gap:8px; font-size:14px; color:#44524f; white-space:nowrap; }
.srv-list-size .form-control{ width:auto; height:38px; padding:0 28px 0 10px; }
.srv-list-footer{ display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-top:14px; font-size:13.5px; color:#44524f; }
.srv-list-footer .pagination{ margin:0; }
</style>
@else
<div class="srv-list-footer">
	<div>
		@if($orders->total() > 0)
		Showing <strong>{{ $orders->firstItem() }}</strong>&ndash;<strong>{{ $orders->lastItem() }}</strong> of <strong>{{ $orders->total() }}</strong> requisitions
		@else
		No requisitions found.
		@endif
	</div>
	@if($orders->hasPages())
	{{ $orders->links('pagination::bootstrap-4') }}
	@endif
</div>
@endif
