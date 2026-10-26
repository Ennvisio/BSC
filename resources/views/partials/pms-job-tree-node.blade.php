{{-- One component in the jobs page tree; counts are filled in by the page script. --}}
@php $children = $childrenByParent->get($node->id, collect()); @endphp
<li data-id="{{ $node->id }}" class="{{ $children->isNotEmpty() ? 'collapsed' : '' }}">
	<div class="pms-jt-row">
		<button type="button" class="pms-jt-caret {{ $children->isEmpty() ? 'leaf' : '' }}" aria-label="Expand or collapse" tabindex="-1"><i class="fas fa-chevron-down"></i></button>
		<button type="button" class="pms-jt-label" data-id="{{ $node->id }}" title="{{ $node->name }}">
			<span class="pms-jt-name">{{ $node->name }}</span>
			<span class="pms-jt-badges">
				<span class="pms-jt-badge pms-jt-badge-resched zero" title="Rescheduled jobs">0</span>
				<span class="pms-jt-badge pms-jt-badge-duesoon zero" title="Due soon">0</span>
				<span class="pms-jt-badge pms-jt-badge-overdue zero" title="Overdue">0</span>
			</span>
			<span class="pms-jt-count zero">0</span>
		</button>
	</div>
	@if($children->isNotEmpty())
	<ul>
		@foreach($children as $child)
		@include('partials.pms-job-tree-node', ['node' => $child])
		@endforeach
	</ul>
	@endif
</li>
