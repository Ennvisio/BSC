{{-- One line's correction history for a single quantity column, as a
	 strikethrough diff - same convention Cloud Fleet Manager uses. Takes
	 $changes (OrderItem::quantityChangesFor($field), oldest first) and
	 $current (the column's live value). Renders nothing when the line has
	 never been corrected - most never are. --}}
@if($changes->isNotEmpty())
@php
	$first = $changes->first();
	$last = $changes->last();
	$history = $changes->map(function ($c) {
		$who = $c->changed_by_name ?: 'Unknown';
		$role = $c->changed_by_role ? ' ('.\App\OrderItemQuantityChange::roleLabel($c->changed_by_role).')' : '';
		return $c->old_qty.' → '.$c->new_qty.' by '.$who.$role.', '.$c->created_at->format('d M Y, h:i A');
	})->implode("\n");
@endphp
<div class="qty-diff" title="{{ $history }}">
	<del>{{ $first->old_qty }}</del> <span class="qty-diff-new">{{ $current }}</span>
	<div class="qty-diff-by">
		by {{ $last->changed_by_name ?: 'Unknown' }}
		@if($changes->count() > 1)
		<span class="qty-diff-count">(+{{ $changes->count() - 1 }} more)</span>
		@endif
	</div>
</div>
@endif
