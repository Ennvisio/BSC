{{-- "Showing x–y of n" + pager. Takes $rows (LengthAwarePaginator) and $noun. --}}
<div class="pms-report-footer">
	<div>
		@if($rows->total() > 0)
		Showing <strong>{{ number_format($rows->firstItem()) }}</strong>&ndash;<strong>{{ number_format($rows->lastItem()) }}</strong> of <strong>{{ number_format($rows->total()) }}</strong> {{ \Illuminate\Support\Str::plural($noun, $rows->total()) }}
		@endif
	</div>
	@if($rows->hasPages())
	{{ $rows->links('pagination::bootstrap-4') }}
	@endif
</div>
