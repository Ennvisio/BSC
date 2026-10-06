{{--
	The "Service Requisition" dropdown, sibling of the Item Requisition one.
	Takes $mode:
	  'ship'  - Add Service Requisition (for the two officers who raise them),
	            then Pending / Approved / Rejected
	  'shore' - SRD-level officers: Pending / My Approvals / Approved / Rejected
	Pending = not yet received on board; Approved = the vessel has confirmed the
	service was received; My Approvals = what this SRD officer personally approved.
--}}
@php
	$role = auth()->user()->role->role ?? null;
	$group = Route::is('service-requisition.*');
@endphp
<div class="srd-nav-group {{ $group ? 'is-open has-active' : '' }}" id="nav-service-requisition">
	<button type="button" class="srd-nav-item srd-nav-group-toggle" aria-expanded="{{ $group ? 'true' : 'false' }}" aria-controls="nav-service-requisition-sub">
		{{-- fa-wrench, not fa-tools: this app loads Font Awesome 5.0.6, where fa-tools doesn't exist. --}}
		<i class="fas fa-wrench"></i>Service Requisition<i class="fas fa-chevron-down srd-nav-caret"></i>
	</button>
	<div class="srd-nav-sub" id="nav-service-requisition-sub">
		@if($mode === 'ship' && in_array($role, ['chief-officer', 'second-engineer']))
		<a href="{{ route('service-requisition.create') }}" class="srd-nav-item {{ Route::is('service-requisition.create') ? 'active' : '' }}"><i class="fas fa-plus"></i>Add Service Requisition</a>
		@endif
		<a href="{{ route('service-requisition.pending') }}" class="srd-nav-item {{ Route::is('service-requisition.pending') ? 'active' : '' }}"><i class="fas fa-hourglass-half"></i>Pending Requisition</a>
		@if($mode === 'shore')
		<a href="{{ route('service-requisition.my-approvals') }}" class="srd-nav-item {{ Route::is('service-requisition.my-approvals') ? 'active' : '' }}"><i class="fas fa-check-circle"></i>My Approvals</a>
		@endif
		<a href="{{ route('service-requisition.approved') }}" class="srd-nav-item {{ Route::is('service-requisition.approved') ? 'active' : '' }}"><i class="fas fa-clipboard"></i>Approved Requisition</a>
		<a href="{{ route('service-requisition.rejected') }}" class="srd-nav-item {{ Route::is('service-requisition.rejected') ? 'active' : '' }}"><i class="fas fa-times-circle"></i>Rejected Requisition</a>
	</div>
</div>
