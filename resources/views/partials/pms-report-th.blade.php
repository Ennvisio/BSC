{{-- A sortable column heading. Takes $col, $label, $sort, $dir, and optional $class. --}}
@php
	$active = $sort === $col;
	$nextDir = $active && $dir === 'asc' ? 'desc' : ($active ? 'asc' : ($defaultDir ?? 'asc'));
@endphp
<th class="{{ $class ?? '' }}" @if($active) aria-sort="{{ $dir === 'asc' ? 'ascending' : 'descending' }}" @endif>
	<a href="{{ request()->fullUrlWithQuery(['sort' => $col, 'dir' => $nextDir, 'page' => null]) }}">{{ $label }}
		<i class="fas {{ $active ? ($dir === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort' }}"></i></a>
</th>
