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

  @if(!empty(auth()->user()->role->role) &&
  (auth()->user()->role->role=='second-engineer' || auth()->user()->role->role=='chief-officer'))

  <div class="srd-nav-label">Overview</div>
  <a href="{{url('/home')}}" class="srd-nav-item {{Route::current()->uri() == 'home' ? 'active' : ''}}"><i class="fas fa-th-large"></i>Dashboard</a>

  {{-- No Stores section for ship officers: Categories is super-admin only
       and Items is no longer surfaced anywhere. --}}
  <div class="srd-nav-label">Requisitions</div>
  {{-- Straight to the wizard, not the /home/order list - the Dashboard
       above already shows the full vessel-wide requisition list, so this
       nav item's job is purely "start a new one" now. Route::is() rather
       than comparing Route::current()->uri() since the wizard's later
       steps carry a dynamic {order} id in the path. --}}
  <a href="{{ route('requisition.step1') }}" class="srd-nav-item {{ Route::is('requisition.*') ? 'active' : '' }}"><i class="fas fa-list-alt"></i>Add Requisition</a>
  <a href="{{url('/approved/requisition')}}" class="srd-nav-item {{Route::current()->uri() == 'approved/requisition' ? 'active' : ''}}"><i class="fas fa-clipboard"></i>Approved/Sent Requisition</a>
  <a href="{{url('/pending/requisition')}}" class="srd-nav-item {{Route::current()->uri() == 'pending/requisition' ? 'active' : ''}}"><i class="fas fa-hourglass-half"></i>Pending Requisition From SSM</a>
  <a href="{{url('/received/requisition')}}" class="srd-nav-item {{Route::current()->uri() == 'received/requisition' ? 'active' : ''}}"><i class="fas fa-inbox"></i>Received Requisition</a>
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
  @endif

  @if(!empty(auth()->user()->role->role) && (auth()->user()->role->role!='super-admin') &&
   (auth()->user()->role->role!='second-engineer') &&
   (auth()->user()->role->role!='chief-officer'))

  <div class="srd-nav-label">Requisitions</div>
  @if(in_array(auth()->user()->role->role, ['technical-superintendent', 'marine-superintendent']))
  <a href="{{url('/home/order')}}" class="srd-nav-item {{Route::current()->uri() == 'home/order' ? 'active' : ''}}"><i class="fas fa-list-alt"></i>All Requisitions</a>
  @endif
  <a href="{{url('/pending/requisition')}}" class="srd-nav-item {{Route::current()->uri() == 'pending/requisition' ? 'active' : ''}}"><i class="fas fa-hourglass-half"></i>Pending Requisition</a>
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
