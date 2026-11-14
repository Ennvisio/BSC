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
  <a href="{{url('/home/certificate')}}" class="srd-nav-item {{Route::current()->uri() == 'home/certificate' ? 'active' : ''}}"><i class="fas fa-certificate"></i>Certificates<span class="srd-nav-count">{{ \App\Support\NavCounts::get('certificates') }}</span></a>
  <a href="{{url('/home/survey')}}" class="srd-nav-item {{Route::current()->uri() == 'home/survey' ? 'active' : ''}}"><i class="fas fa-clipboard"></i>Surveys<span class="srd-nav-count">{{ \App\Support\NavCounts::get('surveys') }}</span></a>
  <a href="{{url('/home/vessel')}}" class="srd-nav-item {{Route::current()->uri() == 'home/vessel' ? 'active' : ''}}"><i class="fas fa-ship"></i>Vessels<span class="srd-nav-count">{{ \App\Support\NavCounts::get('vessels') }}</span></a>

  {{-- Super-admin's Requisitions section (Categories + the all-fleet list) -
       SRD/SSM roles (GM (SRD) included) get Categories in their own
       Requisitions section further down. Items is no longer surfaced
       anywhere - the catalog is browsed through Browse Catalog. --}}
  @if(auth()->user()->role->role == 'super-admin')
  <div class="srd-nav-label">Requisitions</div>
  <a href="{{url('/home/category')}}" class="srd-nav-item {{Route::current()->uri() == 'home/category' ? 'active' : ''}}"><i class="fas fa-th"></i>Categories<span class="srd-nav-count">{{ \App\Support\NavCounts::get('categories') }}</span></a>
  @endif
  {{-- The all-fleet item and service requisition lists are super-admin menu
       items - same lists the dashboard's overview cards open. --}}
  @if(auth()->user()->role->role == 'super-admin')
  <a href="{{url('/home/order')}}" class="srd-nav-item {{Route::current()->uri() == 'home/order' ? 'active' : ''}}"><i class="fas fa-list-alt"></i>Item Requisitions</a>
  <a href="{{ route('service-requisition.index') }}" class="srd-nav-item {{Route::current()->uri() == 'service-requisitions' ? 'active' : ''}}"><i class="fas fa-wrench"></i>Service Requisitions</a>
  @endif
  {{-- GM (SRD) also lands here (this block already covers super-admin/gm-srd/
       admin) - the SSM roles get the same group further down, where the rest
       of their own nav lives, so it isn't duplicated for gm-srd. Read-only
       ('shore' mode - fleet-wide, no write actions), same as every other
       fleet-wide role sees. Catalog Import/Browse Catalog live inside this
       dropdown now, not as standalone links here. --}}
  <div class="srd-nav-label">Inventory and Stock</div>
  @include('partials.sidebar-inventory', ['mode' => 'shore'])
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
  <div class="srd-nav-label">Inventory and Stock</div>
  @include('partials.sidebar-inventory', ['mode' => 'ship'])
  {{-- Equipment & maker list is Master/Chief Engineer's own to maintain -
       what a Shore Repair service requisition line picks from. Catalog
       Import/Browse Catalog live inside the Inventory and Stock dropdown
       above now, not as standalone links under a separate label here. --}}
  @if(in_array(auth()->user()->role->role, ['master', 'chief-engineer']))
  <div class="srd-nav-label">Vessel Records</div>
  <a href="{{ route('equipment.index') }}" class="srd-nav-item {{ Route::current()->uri() == 'vessel/equipment' ? 'active' : '' }}"><i class="fas fa-cogs"></i>Equipment List</a>
  {{-- Certificates/Surveys are fleet-wide reference data everywhere else in
       this sidebar (super-admin/GM (SRD)/admin only, see "Fleet Records"
       above) - but a vessel's own Master/Chief Engineer also see and add
       records for THEIR vessel here (HomeController::isVesselRecordsManager),
       scoped so neither can see or touch another vessel's certificates. --}}
  <a href="{{url('/home/certificate')}}" class="srd-nav-item {{Route::current()->uri() == 'home/certificate' ? 'active' : ''}}"><i class="fas fa-certificate"></i>Certificates</a>
  <a href="{{url('/home/survey')}}" class="srd-nav-item {{Route::current()->uri() == 'home/survey' ? 'active' : ''}}"><i class="fas fa-clipboard"></i>Surveys</a>
  @endif
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

  {{-- Same Fleet Records links super-admin/GM (SRD) get above, for every
       other SRD and SSM role (HomeController::isFleetRecordsAdmin lets them
       manage certificates/surveys fleet-wide). GM (SRD) is excluded here
       only because it already has them in that block. --}}
  @if(in_array(auth()->user()->role->user_type, ['srd', 'ssm']) && auth()->user()->role->role != 'gm-srd')
  <div class="srd-nav-label">Fleet Records</div>
  <a href="{{url('/home/certificate')}}" class="srd-nav-item {{Route::current()->uri() == 'home/certificate' ? 'active' : ''}}"><i class="fas fa-certificate"></i>Certificates<span class="srd-nav-count">{{ \App\Support\NavCounts::get('certificates') }}</span></a>
  <a href="{{url('/home/survey')}}" class="srd-nav-item {{Route::current()->uri() == 'home/survey' ? 'active' : ''}}"><i class="fas fa-clipboard"></i>Surveys<span class="srd-nav-count">{{ \App\Support\NavCounts::get('surveys') }}</span></a>
  <a href="{{url('/home/vessel')}}" class="srd-nav-item {{Route::current()->uri() == 'home/vessel' ? 'active' : ''}}"><i class="fas fa-ship"></i>Vessels<span class="srd-nav-count">{{ \App\Support\NavCounts::get('vessels') }}</span></a>
  @endif

  {{-- Fleet-wide, unlike Master/Chief Engineer's own version of this group -
       every SSM role, and every SRD role GM can delegate to, sources/approves
       against any vessel's stock, not just one (App\Support\InventoryGate's
       FLEET_WIDE_ROLES list is the source of truth this has to match). GM
       (SRD) already has this above (this whole block excludes super-admin,
       and gm-srd sits with super-admin up there), so it isn't repeated for
       them here. Read-only ('shore' mode) - shore never writes to a
       vessel's stock. --}}
  @if(auth()->user()->role->user_type == 'ssm' || in_array(auth()->user()->role->role, ['dgm-srd', 'agm-srd', 'am-srd', 'superintendent-srd']))
  <div class="srd-nav-label">Inventory and Stock</div>
  @include('partials.sidebar-inventory', ['mode' => 'shore'])
  @endif

  <div class="srd-nav-label">Requisitions</div>
  {{-- Every SRD/SSM role (GM (SRD) included) - super-admin has its own
       Requisitions section with Categories up top. --}}
  @if(in_array(auth()->user()->role->user_type, ['srd', 'ssm']))
  <a href="{{url('/home/category')}}" class="srd-nav-item {{Route::current()->uri() == 'home/category' ? 'active' : ''}}"><i class="fas fa-th"></i>Categories<span class="srd-nav-count">{{ \App\Support\NavCounts::get('categories') }}</span></a>
  @endif
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
  <a href="{{ route('get.certificate-categories') }}" class="srd-nav-item {{Route::current()->uri() == 'home/certificate-category' ? 'active' : ''}}"><i class="fas fa-tags"></i>Certificate Categories<span class="srd-nav-count">{{ \App\Support\NavCounts::get('certificate_categories') }}</span></a>
  @endif

  @if(!empty(auth()->user()->role->role) && auth()->user()->role->role=='super-admin')
  <a href="{{url('/home/budget-group')}}" class="srd-nav-item {{Route::current()->uri() == 'home/budget-group' ? 'active' : ''}}"><i class="fas fa-money-bill-alt"></i>Budget Groups<span class="srd-nav-count">{{ \App\Support\NavCounts::get('budget_groups') }}</span></a>
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
