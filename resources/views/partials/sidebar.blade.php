{{-- Every branch below reads auth()->user()->role, so the whole nav is
     gated on there actually being a user. Without this an expired session
     renders the layout with a null user and the sidebar fatals instead of
     the request simply redirecting to login. --}}
@auth
<div class="srd-sidebar-backdrop js-srd-sidebar-close"></div>
<aside class="srd-sidebar">
  <div class="srd-brand">
    <div class="srd-brand-mark"><i class="fas fa-ship"></i></div>
    <div class="srd-brand-text"><strong>Ship Repair Dept.</strong><span>Bangladesh Shipping Corp.</span></div>
  </div>

  @if(!empty(auth()->user()->role->role) && (auth()->user()->role->role=='super-admin' ||
  auth()->user()->role->role=='gm-srd'|| auth()->user()->role->role=='admin'))

  <div class="srd-nav-label">Overview</div>
  <a href="{{url('/home')}}" class="srd-nav-item {{Route::current()->uri() == 'home' ? 'active' : ''}}"><i class="fas fa-th-large"></i>Dashboard</a>

  <div class="srd-nav-label">Fleet Records</div>
  <a href="{{url('/home/certificate')}}" class="srd-nav-item {{Route::current()->uri() == 'home/certificate' ? 'active' : ''}}"><i class="fas fa-certificate"></i>Certificates<span class="srd-nav-count">{{ \App\VesselCertificate::where('status',true)->count() }}</span></a>
  <a href="{{url('/home/survey')}}" class="srd-nav-item {{Route::current()->uri() == 'home/survey' ? 'active' : ''}}"><i class="fas fa-clipboard"></i>Surveys<span class="srd-nav-count">{{ \App\Survey::where('status',true)->count() }}</span></a>
  <a href="{{url('/home/vessel')}}" class="srd-nav-item {{Route::current()->uri() == 'home/vessel' ? 'active' : ''}}"><i class="fas fa-ship"></i>Vessels<span class="srd-nav-count">{{ \App\Vessel::where('status',true)->count() }}</span></a>

  <div class="srd-nav-label">Stores</div>
  {{-- Categories is super-admin only; Items is no longer surfaced anywhere -
       the catalog is browsed through Browse Catalog instead. --}}
  @if(auth()->user()->role->role == 'super-admin')
  <a href="{{url('/home/category')}}" class="srd-nav-item {{Route::current()->uri() == 'home/category' ? 'active' : ''}}"><i class="fas fa-th"></i>Categories<span class="srd-nav-count">{{ \App\Category::where('status',true)->count() }}</span></a>
  @endif
  {{-- The all-fleet requisition list (/home/order) is a super-admin menu item. --}}
  @if(auth()->user()->role->role == 'super-admin')
  <a href="{{url('/home/order')}}" class="srd-nav-item {{Route::current()->uri() == 'home/order' ? 'active' : ''}}"><i class="fas fa-list-alt"></i>Requisitions</a>
  @endif
  <a href="{{url('/catalog/import')}}" class="srd-nav-item {{in_array(Route::current()->uri(), ['catalog/import', 'catalog/import/history']) ? 'active' : ''}}"><i class="fas fa-upload"></i>Catalog Import</a>
  <a href="{{url('/catalog/browse')}}" class="srd-nav-item {{Route::current()->uri() == 'catalog/browse' ? 'active' : ''}}"><i class="fas fa-th"></i>Browse Catalog</a>
  {{-- GM (SRD) also lands here (this block already covers super-admin/gm-srd/
       admin) - the SSM roles get the same link further down, where the rest
       of their own nav lives, so it isn't duplicated for gm-srd. --}}
  {{-- fa-chart-bar, not fa-clipboard-list: this app loads Font Awesome 5.0.6,
       and fa-clipboard-list only exists from 5.1 onwards (it renders as
       nothing). Same trap as fa-tools/fa-boxes elsewhere in this sidebar. --}}
  <a href="{{ route('stock.report') }}" class="srd-nav-item {{Route::current()->uri() == 'stock/report' ? 'active' : ''}}"><i class="fas fa-chart-bar"></i>Stock Report</a>
  @endif

  {{-- Ship side follows the requisition's LIFECYCLE - Pending -> Approved ->
       Delivered, every requisition in exactly one of them - rather than the
       "what needs my action" queues the shore roles below get. --}}
  @if(!empty(auth()->user()->role->user_type) && auth()->user()->role->user_type == 'ship')

  <div class="srd-nav-label">Overview</div>
  <a href="{{url('/home')}}" class="srd-nav-item {{Route::current()->uri() == 'home' ? 'active' : ''}}"><i class="fas fa-th-large"></i>Dashboard</a>

  {{-- No Stores section for ship officers: Categories is super-admin only
       and Items is no longer surfaced anywhere. --}}
  <div class="srd-nav-label">Requisitions</div>
  {{-- Only the officers who actually raise requisitions get the wizard.
       Straight to it rather than the /home/order list - the Dashboard above
       already shows the vessel's requisitions, so this nav item's job is
       purely "start a new one". Route::is() rather than comparing
       Route::current()->uri() since the wizard's later steps carry a dynamic
       {order} id in the path. --}}
  {{-- Everything item-requisition related lives in one dropdown; service
       requisitions are a separate module and stay as their own links below. --}}
  @include('partials.sidebar-item-requisition', ['mode' => 'ship'])
  {{-- Service requisitions (certificate servicing, surveys, equipment
       maintenance, IT support - work that isn't an item pick-list; its chain
       ends at SRD level) are their own module with their own dropdown. --}}
  @include('partials.sidebar-service-requisition', ['mode' => 'ship'])
  @endif

  @if(!empty(auth()->user()->role->user_type) && auth()->user()->role->user_type == 'ship')
  <div class="srd-nav-label">Vessel Catalog</div>
  <a href="{{url('/catalog/import')}}" class="srd-nav-item {{in_array(Route::current()->uri(), ['catalog/import', 'catalog/import/history']) ? 'active' : ''}}"><i class="fas fa-upload"></i>Catalog Import</a>
  <a href="{{url('/catalog/browse')}}" class="srd-nav-item {{Route::current()->uri() == 'catalog/browse' ? 'active' : ''}}"><i class="fas fa-th"></i>Browse Catalog</a>
  {{-- Stock is the Master's to maintain: they're the authority on what is
       physically in the ship's store. --}}
  @if(auth()->user()->role->role == 'master')
  {{-- fa-archive, not fa-boxes: this app loads Font Awesome 5.0.6, and
       fa-boxes only exists from 5.2 onwards (it renders as nothing). --}}
  <a href="{{url('/stock/upload')}}" class="srd-nav-item {{in_array(Route::current()->uri(), ['stock/upload', 'stock/history']) ? 'active' : ''}}"><i class="fas fa-archive"></i>Update Stock</a>
  @endif
  {{-- Equipment & maker list is Master/Chief Engineer's own to maintain -
       what a Shore Repair service requisition line picks from. --}}
  @if(in_array(auth()->user()->role->role, ['master', 'chief-engineer']))
  <a href="{{ route('equipment.index') }}" class="srd-nav-item {{ Route::current()->uri() == 'vessel/equipment' ? 'active' : '' }}"><i class="fas fa-cogs"></i>Equipment List</a>
  {{-- Certificates/Surveys are fleet-wide reference data everywhere else in
       this sidebar (super-admin/GM (SRD)/admin only, see "Fleet Records"
       above) - but a vessel's own Master/Chief Engineer also see and add
       records for THEIR vessel here (HomeController::isVesselRecordsManager),
       scoped so neither can see or touch another vessel's certificates. --}}
  <a href="{{url('/home/certificate')}}" class="srd-nav-item {{Route::current()->uri() == 'home/certificate' ? 'active' : ''}}"><i class="fas fa-certificate"></i>Certificates</a>
  <a href="{{url('/home/survey')}}" class="srd-nav-item {{Route::current()->uri() == 'home/survey' ? 'active' : ''}}"><i class="fas fa-clipboard"></i>Surveys</a>
  {{-- Read-only reporting on top of Update Stock above - Master/Chief
       Engineer see only their own vessel here (StockReportController locks
       it), same authority split as everything else in this section. --}}
  <a href="{{ route('stock.report') }}" class="srd-nav-item {{Route::current()->uri() == 'stock/report' ? 'active' : ''}}"><i class="fas fa-chart-bar"></i>Stock Report</a>
  @endif
  {{-- Consuming stock is the officers' own to log - chief-officer/second-
       engineer are the ones actually using items day to day (same two roles
       who raise item requisitions above), Master keeps the separate
       "restate the real figure" authority via Update Stock instead. The log
       itself is visible to every ship role on the vessel for oversight. --}}
  @if(in_array(auth()->user()->role->role, ['chief-officer', 'second-engineer']))
  <a href="{{ route('stock-consumption.create') }}" class="srd-nav-item {{ Route::current()->uri() == 'stock/consumption/create' ? 'active' : '' }}"><i class="fas fa-wrench"></i>Log Consumption</a>
  @endif
  <a href="{{ route('stock-consumption.index') }}" class="srd-nav-item {{ Route::current()->uri() == 'stock/consumption' ? 'active' : '' }}"><i class="fas fa-list"></i>Consumption Log</a>
  @endif

  {{-- Shore side keeps ACTION QUEUES: "Pending" here means "waiting on me",
       which is a different question from the ship's lifecycle view above.
       Excluded by user_type rather than by listing every ship role, so a new
       ship role can't accidentally end up with both sets of links. --}}
  @if(!empty(auth()->user()->role->role) && (auth()->user()->role->role!='super-admin') &&
   (auth()->user()->role->user_type != 'ship'))

  {{-- SSM (DGM/AGM/AM/Superintendent) and GM (SRD)'s four named SRD
       delegates land on a real dashboard now instead of a bare redirect
       straight to Pending Requisition - same "Overview" treatment
       super-admin/GM (SRD) and the ship roles already get above. --}}
  @if(auth()->user()->role->user_type == 'ssm' || in_array(auth()->user()->role->role, ['dgm-srd', 'agm-srd', 'am-srd', 'superintendent-srd']))
  <div class="srd-nav-label">Overview</div>
  <a href="{{url('/home')}}" class="srd-nav-item {{Route::current()->uri() == 'home' ? 'active' : ''}}"><i class="fas fa-th-large"></i>Dashboard</a>
  @endif

  {{-- Fleet-wide, unlike Master/Chief Engineer's own version of this link -
       every SSM role, and every SRD role GM can delegate to, sources/approves
       against any vessel's stock, not just one (StockReportController's own
       FLEET_WIDE_ROLES list is the source of truth this has to match). GM
       (SRD) already has this above (this whole block excludes super-admin,
       and gm-srd sits with super-admin up there), so it isn't repeated for
       them here. --}}
  @if(auth()->user()->role->user_type == 'ssm' || in_array(auth()->user()->role->role, ['dgm-srd', 'agm-srd', 'am-srd', 'superintendent-srd']))
  <a href="{{ route('stock.report') }}" class="srd-nav-item {{Route::current()->uri() == 'stock/report' ? 'active' : ''}}"><i class="fas fa-chart-bar"></i>Stock Report</a>
  @endif

  <div class="srd-nav-label">Requisitions</div>
  @include('partials.sidebar-item-requisition', ['mode' => 'shore'])
  {{-- Service requisitions stop at SRD level, so only GM (SRD) and its four
       delegates ever act on one - the SSM roles would see a list they can
       do nothing with. --}}
  @if(in_array(auth()->user()->role->role, ['gm-srd', 'dgm-srd', 'agm-srd', 'am-srd', 'superintendent-srd']))
  @include('partials.sidebar-service-requisition', ['mode' => 'shore'])
  @endif
  @endif

  {{-- Parenthesised deliberately: && binds tighter than ||, so written as
       "guard && A || B" the guard covers only A and B is left to evaluate
       on a null user. --}}
  @if(!empty(auth()->user()->role->role) && (auth()->user()->role->role=='super-admin' ||
  auth()->user()->role->role=='gm-srd'))
  <div class="srd-nav-label">Admin</div>
  <a href="{{url('/home/user')}}" class="srd-nav-item {{Route::current()->uri() == 'home/user' ? 'active' : ''}}"><i class="fas fa-user"></i>Users</a>
  <a href="{{url('/home/trash')}}" class="srd-nav-item {{Route::current()->uri() == 'home/trash' ? 'active' : ''}}"><i class="fas fa-trash-alt"></i>Trash</a>
  @endif

  @if(!empty(auth()->user()->role->role) && auth()->user()->role->role=='super-admin')
  <a href="{{url('/home/budget-group')}}" class="srd-nav-item {{Route::current()->uri() == 'home/budget-group' ? 'active' : ''}}"><i class="fas fa-money-bill-alt"></i>Budget Groups<span class="srd-nav-count">{{ \App\BudgetGroup::where('status',true)->count() }}</span></a>
  @endif

  <div class="srd-sidebar-foot">
    <div class="srd-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 2)) }}</div>
    <div>
      <strong style="display:block;font-size:12.5px;font-weight:600;color:var(--srd-sidebar-text-strong);">{{ auth()->user()->name }}</strong>
      <span style="font-size:10.5px;color:var(--srd-sidebar-muted);text-transform:capitalize;">{{ !empty(auth()->user()->role->role) ? str_replace('-', ' ', auth()->user()->role->role) : '' }}</span>
    </div>
  </div>
</aside>
@include('partials.sidebar-group-script')
@endauth
