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
  <a href="{{url('/home/category')}}" class="srd-nav-item {{Route::current()->uri() == 'home/category' ? 'active' : ''}}"><i class="fas fa-th"></i>Categories<span class="srd-nav-count">{{ \App\Category::where('status',true)->count() }}</span></a>
  <a href="{{url('/home/item')}}" class="srd-nav-item {{Route::current()->uri() == 'home/item' ? 'active' : ''}}"><i class="fas fa-cubes"></i>Items<span class="srd-nav-count">{{ \App\Item::where('status',true)->count() }}</span></a>
  <a href="{{url('/home/order')}}" class="srd-nav-item {{Route::current()->uri() == 'home/order' ? 'active' : ''}}"><i class="fas fa-list-alt"></i>Requisitions</a>
  <a href="{{url('/catalog/import')}}" class="srd-nav-item {{in_array(Route::current()->uri(), ['catalog/import', 'catalog/import/history']) ? 'active' : ''}}"><i class="fas fa-upload"></i>Catalog Import</a>
  <a href="{{url('/catalog/browse')}}" class="srd-nav-item {{Route::current()->uri() == 'catalog/browse' ? 'active' : ''}}"><i class="fas fa-th"></i>Browse Catalog</a>
  @endif

  @if(!empty(auth()->user()->role->role) &&
  (auth()->user()->role->role=='second-engineer' || auth()->user()->role->role=='chief-officer'))

  <div class="srd-nav-label">Stores</div>
  <a href="{{url('/home/category')}}" class="srd-nav-item {{Route::current()->uri() == 'home/category' ? 'active' : ''}}"><i class="fas fa-th"></i>Categories<span class="srd-nav-count">{{ \App\Category::where('status',true)->count() }}</span></a>
  <a href="{{url('/home/item')}}" class="srd-nav-item {{Route::current()->uri() == 'home/item' ? 'active' : ''}}"><i class="fas fa-cubes"></i>Items<span class="srd-nav-count">{{ \App\Item::where('status',true)->count() }}</span></a>

  <div class="srd-nav-label">Requisitions</div>
  <a href="{{url('/home/order')}}" class="srd-nav-item {{Route::current()->uri() == 'home/order' ? 'active' : ''}}"><i class="fas fa-list-alt"></i>Add Requisition</a>
  <a href="{{url('/approved/requisition')}}" class="srd-nav-item {{Route::current()->uri() == 'approved/requisition' ? 'active' : ''}}"><i class="fas fa-clipboard"></i>Approved/Sent Requisition</a>
  <a href="{{url('/pending/requisition')}}" class="srd-nav-item {{Route::current()->uri() == 'pending/requisition' ? 'active' : ''}}"><i class="fas fa-hourglass-half"></i>Pending Requisition From SSM</a>
  <a href="{{url('/received/requisition')}}" class="srd-nav-item {{Route::current()->uri() == 'received/requisition' ? 'active' : ''}}"><i class="fas fa-inbox"></i>Received Requisition</a>
  @endif

  @if(!empty(auth()->user()->role->user_type) && auth()->user()->role->user_type == 'ship')
  <div class="srd-nav-label">Vessel Catalog</div>
  <a href="{{url('/catalog/import')}}" class="srd-nav-item {{in_array(Route::current()->uri(), ['catalog/import', 'catalog/import/history']) ? 'active' : ''}}"><i class="fas fa-upload"></i>Catalog Import</a>
  <a href="{{url('/catalog/browse')}}" class="srd-nav-item {{Route::current()->uri() == 'catalog/browse' ? 'active' : ''}}"><i class="fas fa-th"></i>Browse Catalog</a>
  @endif

  @if(!empty(auth()->user()->role->role) && (auth()->user()->role->role!='super-admin') &&
   (auth()->user()->role->role!='second-engineer') &&
   (auth()->user()->role->role!='chief-officer'))

  <div class="srd-nav-label">Requisitions</div>
  <a href="{{url('/pending/requisition')}}" class="srd-nav-item {{Route::current()->uri() == 'pending/requisition' ? 'active' : ''}}"><i class="fas fa-hourglass-half"></i>Pending Requisition</a>
  <a href="{{url('/approved/requisition')}}" class="srd-nav-item {{Route::current()->uri() == 'approved/requisition' ? 'active' : ''}}"><i class="fas fa-clipboard"></i>Approved Requisition</a>
  <a href="{{url('/received/requisition')}}" class="srd-nav-item {{Route::current()->uri() == 'received/requisition' ? 'active' : ''}}"><i class="fas fa-inbox"></i>Received Requisition</a>
  @endif

  @if(!empty(auth()->user()->role->role) && auth()->user()->role->role=='super-admin'||
  auth()->user()->role->role=='gm-srd' )
  <div class="srd-nav-label">Admin</div>
  <a href="{{url('/home/user')}}" class="srd-nav-item {{Route::current()->uri() == 'home/user' ? 'active' : ''}}"><i class="fas fa-user"></i>Users</a>
  <a href="{{url('/home/trash')}}" class="srd-nav-item {{Route::current()->uri() == 'home/trash' ? 'active' : ''}}"><i class="fas fa-trash-alt"></i>Trash</a>
  @endif

  <div class="srd-sidebar-foot">
    <div class="srd-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 2)) }}</div>
    <div>
      <strong style="display:block;font-size:12.5px;font-weight:600;">{{ auth()->user()->name }}</strong>
      <span style="font-size:10.5px;color:var(--srd-muted);text-transform:capitalize;">{{ !empty(auth()->user()->role->role) ? str_replace('-', ' ', auth()->user()->role->role) : '' }}</span>
    </div>
  </div>
</aside>
