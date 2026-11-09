{{--
	The "Inventory and Stock" dropdown: everything about a vessel's stock AND
	catalog in one place - Current Stock (opening/received/consumed/
	adjustments/current, from the stock_movements ledger - see
	App\Support\InventoryGate), the older Stock Report, Catalog Import (not
	role-restricted beyond auth - every mode sees it; Browse Catalog is
	deliberately left out of this menu on request), and (ship side only)
	the Master's own maintenance tools and consumption
	logging. Takes $mode:
	  'ship'  - every ship role sees Current Stock; Stock Report stays
	            Master/Chief Engineer only (unchanged, not widened); Update
	            Stock is Master/chief-officer/second-engineer; Log Consumption
	            is chief-officer/second-engineer only; Consumption Log is
	            every ship role.
	  'shore' - read-only only (Current Stock + Stock Report) - shore never
	            writes to a vessel's stock. Used both by the fleet-wide SRD/
	            SSM roles and by super-admin/GM (SRD) up in the admin block,
	            since their access is identical here.
	Opens on its own when you're on one of its pages (the shared script in
	sidebar-group-script remembers a manual open/close).
--}}
@php
	$role = auth()->user()->role->role ?? null;
	$childUris = ['inventory/stock', 'inventory/movements', 'stock/report', 'stock/upload', 'stock/history', 'stock/consumption/create', 'stock/consumption', 'catalog/import', 'catalog/import/history'];
	$groupActive = in_array(Route::current()->uri(), $childUris, true);
	$link = fn ($uris) => in_array(Route::current()->uri(), (array) $uris, true) ? 'active' : '';
@endphp
<div class="srd-nav-group {{ $groupActive ? 'is-open has-active' : '' }}" id="nav-inventory">
	<button type="button" class="srd-nav-item srd-nav-group-toggle" aria-expanded="{{ $groupActive ? 'true' : 'false' }}" aria-controls="nav-inventory-sub">
		<i class="fas fa-archive"></i>Inventory and Stock<i class="fas fa-chevron-down srd-nav-caret"></i>
	</button>
	<div class="srd-nav-sub" id="nav-inventory-sub">
		<a href="{{ route('inventory.stock') }}" class="srd-nav-item {{ $link('inventory/stock') }}"><i class="fas fa-list-alt"></i>Current Stock</a>
		<a href="{{ route('inventory.movements') }}" class="srd-nav-item {{ $link('inventory/movements') }}"><i class="fas fa-chart-pie"></i>Movements Report</a>
		@if($mode !== 'ship' || in_array($role, ['master', 'chief-engineer']))
		<a href="{{ route('stock.report') }}" class="srd-nav-item {{ $link('stock/report') }}"><i class="fas fa-chart-bar"></i>Stock Report</a>
		@endif
		<a href="{{ url('/catalog/import') }}" class="srd-nav-item {{ $link(['catalog/import', 'catalog/import/history']) }}"><i class="fas fa-upload"></i>Catalog Import</a>
		@if($mode === 'ship')
		@if(in_array($role, ['master', 'chief-officer', 'second-engineer']))
		<a href="{{ url('/stock/upload') }}" class="srd-nav-item {{ $link(['stock/upload', 'stock/history']) }}"><i class="fas fa-archive"></i>Update Stock</a>
		@endif
		@if(in_array($role, ['chief-officer', 'second-engineer']))
		<a href="{{ route('stock-consumption.create') }}" class="srd-nav-item {{ $link('stock/consumption/create') }}"><i class="fas fa-wrench"></i>Log Consumption</a>
		@endif
		<a href="{{ route('stock-consumption.index') }}" class="srd-nav-item {{ $link('stock/consumption') }}"><i class="fas fa-list"></i>Consumption Log</a>
		@endif
	</div>
</div>
