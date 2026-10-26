{{-- Which half of the app you're in - shown in both the main app's topbar
	 and the PMS layout's own header, so it reads correctly wherever it's
	 included. $canViewPms is supplied by the view composer on this partial
	 (see AppServiceProvider::boot()). --}}
<div class="srd-module-switch" role="group" aria-label="Module">
	<a href="{{ route('home') }}" class="srd-module-tab {{ Route::is('pms.*') ? '' : 'active' }}">
		<i class="fas fa-ship"></i><span>Ship Repair</span>
	</a>
	@if($canViewPms ?? false)
	<a href="{{ route('pms.dashboard') }}" class="srd-module-tab {{ Route::is('pms.*') ? 'active' : '' }}">
		<i class="fas fa-wrench"></i><span>PMS</span>
	</a>
	@endif
</div>
