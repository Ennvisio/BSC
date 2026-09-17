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
  <a href="{{url('/home/certificate')}}" class="srd-nav-item {{Route::current()->uri() == 'home/certificate' ? 'active' : ''}}"><i class="fas fa-certificate"></i>Certificates<span class="srd-nav-count">{{ \App\Certificate::where('status',true)->count() }}</span></a>
  <a href="{{url('/home/survey')}}" class="srd-nav-item {{Route::current()->uri() == 'home/survey' ? 'active' : ''}}"><i class="fas fa-clipboard"></i>Surveys<span class="srd-nav-count">{{ \App\Survey::where('status',true)->count() }}</span></a>
  <a href="{{url('/home/vessel')}}" class="srd-nav-item {{Route::current()->uri() == 'home/vessel' ? 'active' : ''}}"><i class="fas fa-ship"></i>Vessels<span class="srd-nav-count">{{ \App\Vessel::where('status',true)->count() }}</span></a>

  <div class="srd-nav-label">Stores</div>
  {{-- Categories is super-admin only; Items is no longer surfaced anywhere -
       the catalog is browsed through Browse Catalog instead. --}}
  @if(auth()->user()->role->role == 'super-admin')
  <a href="{{url('/home/category')}}" class="srd-nav-item {{Route::current()->uri() == 'home/category' ? 'active' : ''}}"><i class="fas fa-th"></i>Categories<span class="srd-nav-count">{{ \App\Category::where('status',true)->count() }}</span></a>
  @endif
  <a href="{{url('/home/order')}}" class="srd-nav-item {{Route::current()->uri() == 'home/order' ? 'active' : ''}}"><i class="fas fa-list-alt"></i>Requisitions</a>
  <a href="{{url('/catalog/import')}}" class="srd-nav-item {{in_array(Route::current()->uri(), ['catalog/import', 'catalog/import/history']) ? 'active' : ''}}"><i class="fas fa-upload"></i>Catalog Import</a>
  <a href="{{url('/catalog/browse')}}" class="srd-nav-item {{Route::current()->uri() == 'catalog/browse' ? 'active' : ''}}"><i class="fas fa-th"></i>Browse Catalog</a>
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
  @if(in_array(auth()->user()->role->role, ['chief-officer', 'second-engineer']))
  <a href="{{ route('requisition.step1') }}" class="srd-nav-item {{ Route::is('requisition.*') ? 'active' : '' }}"><i class="fas fa-list-alt"></i>Add Requisition</a>
  {{-- Certificate servicing, surveys, equipment maintenance, IT support -
       work that isn't an item pick-list. Its approval chain ends at SRD
       level (no SSM/procurement leg), but raising one still starts here,
       same as an item requisition. UI only for now - see
       ServiceRequisitionController. --}}
  {{-- fa-wrench, not fa-tools: this app loads Font Awesome 5.0.6, and
       fa-tools only exists from 5.0.9 onwards (it renders as nothing). --}}
  <a href="{{ route('service-requisition.create') }}" class="srd-nav-item {{ Route::is('service-requisition.*') ? 'active' : '' }}"><i class="fas fa-wrench"></i>Add Service Requisition</a>
  @endif
  <a href="{{url('/pending/requisition')}}" class="srd-nav-item {{Route::current()->uri() == 'pending/requisition' ? 'active' : ''}}"><i class="fas fa-hourglass-half"></i>Pending Requisition</a>
  {{-- Only Master/Chief Engineer approve someone else's requisition, so only
       they get a "did I approve this" view - separate from the vessel-wide
       lifecycle pages below. --}}
  @if(in_array(auth()->user()->role->role, ['master', 'chief-engineer']))
  <a href="{{ route('my.approvals') }}" class="srd-nav-item {{Route::current()->uri() == 'my/approvals' ? 'active' : ''}}"><i class="fas fa-check-circle"></i>My Approvals</a>
  @endif
  <a href="{{url('/approved/requisition')}}" class="srd-nav-item {{Route::current()->uri() == 'approved/requisition' ? 'active' : ''}}"><i class="fas fa-clipboard"></i>Approved Requisition</a>
  <a href="{{url('/received/requisition')}}" class="srd-nav-item {{Route::current()->uri() == 'received/requisition' ? 'active' : ''}}"><i class="fas fa-inbox"></i>Delivered Requisition</a>
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

  <div class="srd-nav-label">Requisitions</div>
  @if(in_array(auth()->user()->role->role, ['technical-superintendent', 'marine-superintendent']))
  <a href="{{url('/home/order')}}" class="srd-nav-item {{Route::current()->uri() == 'home/order' ? 'active' : ''}}"><i class="fas fa-list-alt"></i>All Requisitions</a>
  @endif
  <a href="{{url('/pending/requisition')}}" class="srd-nav-item {{Route::current()->uri() == 'pending/requisition' ? 'active' : ''}}"><i class="fas fa-hourglass-half"></i>Pending Requisition</a>
  {{-- Every role that personally approves or delegates a requisition (GM
       and its four SRD delegates, DGM and its three SSM final-actors) gets
       a "did I act on this" view of their own, same reasoning as Master/
       Chief Engineer's version above. --}}
  @if(in_array(auth()->user()->role->role, ['gm-srd', 'dgm-srd', 'agm-srd', 'am-srd', 'superintendent-srd', 'dgm-ssm', 'agm-ssm', 'am-ssm', 'superintendent-ssm']))
  <a href="{{ route('my.approvals') }}" class="srd-nav-item {{Route::current()->uri() == 'my/approvals' ? 'active' : ''}}"><i class="fas fa-check-circle"></i>My Approvals</a>
  @endif
  <a href="{{url('/approved/requisition')}}" class="srd-nav-item {{Route::current()->uri() == 'approved/requisition' ? 'active' : ''}}"><i class="fas fa-clipboard"></i>Approved Requisition</a>
  <a href="{{url('/received/requisition')}}" class="srd-nav-item {{Route::current()->uri() == 'received/requisition' ? 'active' : ''}}"><i class="fas fa-inbox"></i>Received Requisition</a>
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
@endauth
