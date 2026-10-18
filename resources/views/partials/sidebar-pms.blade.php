{{-- The PMS dropdown. Shown to every ship role (their own vessel's tree)
	 and to the shore roles PmsAccess lets view any vessel; Master Data only
	 to super-admin, who maintains it. --}}
@php
	$pmsRole = auth()->user()->role->role ?? null;
	$pmsShip = (auth()->user()->role->user_type ?? null) === 'ship';
	$pmsFleet = in_array($pmsRole, [
		'super-admin', 'gm-srd', 'dgm-srd', 'agm-srd', 'am-srd', 'superintendent-srd',
		'technical-superintendent', 'marine-superintendent',
	], true);
	$pmsGroup = Route::is('pms.*');
@endphp
@if($pmsShip || $pmsFleet)
<div class="srd-nav-label">Maintenance</div>
<div class="srd-nav-group {{ $pmsGroup ? 'is-open has-active' : '' }}" id="nav-pms">
	<button type="button" class="srd-nav-item srd-nav-group-toggle" aria-expanded="{{ $pmsGroup ? 'true' : 'false' }}" aria-controls="nav-pms-sub">
		<i class="fas fa-cogs"></i>PMS<i class="fas fa-chevron-down srd-nav-caret"></i>
	</button>
	<div class="srd-nav-sub" id="nav-pms-sub">
		<a href="{{ route('pms.components.index') }}" class="srd-nav-item {{ Route::is('pms.components.*', 'pms.readings.*') ? 'active' : '' }}"><i class="fas fa-sitemap"></i>Components</a>
		<a href="{{ route('pms.jobs.index') }}" class="srd-nav-item {{ Route::is('pms.jobs.*') ? 'active' : '' }}"><i class="fas fa-wrench"></i>Jobs</a>
		<a href="{{ route('pms.counters.index') }}" class="srd-nav-item {{ Route::is('pms.counters.*') ? 'active' : '' }}"><i class="fas fa-stopwatch"></i>Counters</a>
		<a href="{{ route('pms.parts.index') }}" class="srd-nav-item {{ Route::is('pms.parts.*') ? 'active' : '' }}"><i class="fas fa-cubes"></i>Required Parts</a>
		@if($pmsRole === 'super-admin')
		<a href="{{ route('pms.master.index', 'locations') }}" class="srd-nav-item {{ Route::is('pms.master.*') ? 'active' : '' }}"><i class="fas fa-list-ul"></i>Master Data</a>
		@endif
	</div>
</div>
@endif
