{{-- One component and, recursively, everything under it. Takes $node,
	 $childrenByParent (parent_id => children) and $canEdit. --}}
@php
	$children = $childrenByParent->get($node->id, collect());
	$conditionClass = 'pms-cond-'.\Illuminate\Support\Str::slug($node->condition->name ?? '');
	$editPayload = $node->only([
		'id', 'location_id', 'parent_id', 'name', 'type', 'category_id', 'manufacturer_id', 'serial_no', 'part_no',
		'class_code', 'internal_code', 'condition_id', 'has_counter', 'is_critical', 'is_class_relevant',
		'is_inactive', 'remark', 'sort_order',
	]) + ['installation_date' => optional($node->installation_date)->format('Y-m-d')];
	$searchText = mb_strtolower(implode(' ', array_filter([
		$node->name, $node->type, $node->serial_no, $node->part_no, $node->class_code, $node->internal_code,
		$node->manufacturer->name ?? null, $node->category->name ?? null,
	])));
@endphp
<li class="pms-node {{ $node->is_inactive ? 'inactive' : '' }}" data-search="{{ $searchText }}">
	<div class="pms-node-row">
		<button type="button" class="pms-node-toggle {{ $children->isEmpty() ? 'leaf' : '' }}" aria-label="Expand or collapse">
			<i class="fas fa-chevron-down"></i>
		</button>
		<div class="pms-node-main">
			<div class="pms-node-name">{{ $node->name }}</div>
			<div class="pms-node-meta">
				@if($node->type)<span>{{ $node->type }}</span>@endif
				<span>{{ $node->category->name ?? '—' }}</span>
				@if($node->manufacturer)<span>{{ $node->manufacturer->name }}</span>@endif
				@if($node->serial_no)<span>S/N {{ $node->serial_no }}</span>@endif
				@if($node->internal_code)<span>{{ $node->internal_code }}</span>@endif
			</div>
		</div>
		<div class="pms-node-flags">
			@if($node->is_critical)<span class="pms-flag pms-flag-critical">Critical</span>@endif
			@if($node->is_class_relevant)<span class="pms-flag pms-flag-class">Class</span>@endif
			@if($node->is_inactive)<span class="pms-flag pms-flag-inactive">Inactive</span>@endif
		</div>
		@if($node->has_counter)
		<div class="pms-node-rh" title="Latest running-hour reading">
			<i class="fas fa-stopwatch"></i>
			<a href="{{ route('pms.readings.index', $node->id) }}">
				{{ $node->latestReading ? number_format((float) $node->latestReading->value, 1).' h' : 'No reading' }}
			</a>
		</div>
		@endif
		<div class="pms-node-jobs" title="Active jobs on this component">
			<i class="fas fa-wrench"></i>
			@if($node->jobs_count)
			<a href="{{ route('pms.jobs.index', ['vessel_id' => $node->vessel_id, 'component_id' => $node->id]) }}">{{ $node->jobs_count }} {{ \Illuminate\Support\Str::plural('job', $node->jobs_count) }}</a>
			@if($node->overdue_jobs_count)<span class="pms-status pms-status-Overdue">{{ $node->overdue_jobs_count }} overdue</span>@endif
			@else
			<span class="none">No jobs</span>
			@endif
		</div>
		<span class="pms-cond {{ $conditionClass }}">{{ $node->condition->name ?? '—' }}</span>
		@if($canEdit)
		<div class="pms-node-actions">
			<button type="button" class="btn btn-outline-primary pms-add-job" data-component="{{ json_encode($node->only(['id', 'name', 'has_counter', 'is_critical', 'is_class_relevant'])) }}" title="Add a job to this component"><i class="fas fa-wrench"></i> Add Job</button>
			<button type="button" class="btn btn-outline-secondary pms-add-child" data-id="{{ $node->id }}" data-location="{{ $node->location_id }}" title="Add a sub-component"><i class="fas fa-plus"></i></button>
			<button type="button" class="btn btn-info pms-edit" data-component="{{ json_encode($editPayload) }}" title="Edit"><i class="fas fa-edit"></i></button>
			<button type="button" class="btn btn-danger pms-delete" data-id="{{ $node->id }}" data-name="{{ $node->name }}" title="Delete"><i class="fas fa-trash-alt"></i></button>
		</div>
		@endif
	</div>
	@if($children->isNotEmpty())
	<ul>
		@foreach($children as $child)
		@include('partials.pms-component-node', ['node' => $child])
		@endforeach
	</ul>
	@endif
</li>
