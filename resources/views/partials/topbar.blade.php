<div class="srd-topbar">
  <div class="srd-topbar-left">
    <a href="#" class="js-srd-menu-toggle srd-menu-toggle"><i class="fas fa-bars"></i></a>
    <div class="srd-search"><i class="fas fa-search"></i><input type="text" placeholder="Search vessels, certificates, items…"></div>
  </div>
  <div class="srd-topbar-right">
    @if(!empty(auth()->user()->role->role) && auth()->user()->role->role=='super-admin'||
    auth()->user()->role->role=='gm-srd' )
    <a href="{{url('/home/user')}}" class="srd-icon-btn" title="Users"><i class="fas fa-user"></i></a>
    <a href="{{url('/home/trash')}}" class="srd-icon-btn" title="Trash"><i class="fas fa-trash-alt"></i></a>
    @endif
    <div class="dropdown">
      <a href="#" class="srd-user-chip dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
        <div class="srd-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 2)) }}</div>
        <div class="srd-user-meta">
          <strong>{{ auth()->user()->name }}</strong>
          <span>{{ auth()->user()->email }}</span>
        </div>
      </a>
      <div class="dropdown-menu dropdown-menu-right">
        <a class="dropdown-item" href="{{url('/profile')}}"><i class="fas fa-user mr-1"></i> My Profile</a>
        <a class="dropdown-item" href="{{ route('logout') }}"
        onclick="event.preventDefault();
        document.getElementById('logout-form').submit();"><i class="fas fa-power-off mr-1"></i> Logout
        </a>
        <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
          {{ csrf_field() }}
        </form>
      </div>
    </div>
  </div>
</div>
