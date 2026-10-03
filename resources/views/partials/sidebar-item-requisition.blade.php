{{--
	The "Item Requisition" dropdown: every list/queue/entry point for item
	requisitions grouped under one collapsible heading (service requisitions
	stay outside it, they're a different module). Takes $mode:
	  'ship'  - the lifecycle pages ship roles get (plus Add Requisition for the
	            two officers who raise them, My Approvals for Master/Chief Eng.)
	  'shore' - the action queues shore roles get (My Approvals for every role
	            that approves or delegates)
	Opens on its own when you're on one of its pages (the shared script in
	sidebar-group-script remembers a manual open/close).
--}}
@php
	$role = auth()->user()->role->role ?? null;
	$childUris = ['pending/requisition', 'my/approvals', 'approved/requisition', 'received/requisition', 'order/detail/{order_id}'];
	$groupActive = in_array(Route::current()->uri(), $childUris, true) || Route::is('requisition.*');
	$link = fn ($uris) => in_array(Route::current()->uri(), (array) $uris, true) ? 'active' : '';
@endphp
<div class="srd-nav-group {{ $groupActive ? 'is-open has-active' : '' }}" id="nav-item-requisition">
	<button type="button" class="srd-nav-item srd-nav-group-toggle" aria-expanded="{{ $groupActive ? 'true' : 'false' }}" aria-controls="nav-item-requisition-sub">
		<i class="fas fa-list-alt"></i>Item Requisition<i class="fas fa-chevron-down srd-nav-caret"></i>
	</button>
	<div class="srd-nav-sub" id="nav-item-requisition-sub">
		@if($mode === 'ship')
		@if(in_array($role, ['chief-officer', 'second-engineer']))
		<a href="{{ route('requisition.step1') }}" class="srd-nav-item {{ Route::is('requisition.*') ? 'active' : '' }}"><i class="fas fa-plus"></i>Add Requisition</a>
		@endif
		@endif
		<a href="{{ url('/pending/requisition') }}" class="srd-nav-item {{ $link('pending/requisition') }}"><i class="fas fa-hourglass-half"></i>Pending Requisition</a>
		@if($mode === 'ship' ? in_array($role, ['master', 'chief-engineer']) : in_array($role, ['gm-srd', 'dgm-srd', 'agm-srd', 'am-srd', 'superintendent-srd', 'dgm-ssm', 'agm-ssm', 'am-ssm', 'superintendent-ssm']))
		<a href="{{ route('my.approvals') }}" class="srd-nav-item {{ $link('my/approvals') }}"><i class="fas fa-check-circle"></i>My Approvals</a>
		@endif
		<a href="{{ url('/approved/requisition') }}" class="srd-nav-item {{ $link('approved/requisition') }}"><i class="fas fa-clipboard"></i>Approved Requisition</a>
		<a href="{{ url('/received/requisition') }}" class="srd-nav-item {{ $link('received/requisition') }}"><i class="fas fa-inbox"></i>{{ $mode === 'ship' ? 'Delivered' : 'Received' }} Requisition</a>
	</div>
</div>
