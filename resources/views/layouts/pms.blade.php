<!doctype html>
<html class="no-js" lang="">
<head>
  @include('partials.head-assets')
  @include('partials.pms-shell-styles')
</head>
<body class="srd pms-shell">
  <header class="pms-header">
    <div class="pms-header-row">
      <a href="{{ route('home') }}" class="pms-brand">
        <span class="pms-brand-mark"><i class="fas fa-ship"></i></span>
        <span class="pms-brand-text"><strong>Ship Repair Dept.</strong><span>Bangladesh Shipping Corp.</span></span>
      </a>
      @include('partials.module-switch')
      <div class="pms-header-user dropdown">
        <a href="#" class="dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" style="display:flex; align-items:center; gap:10px; text-decoration:none;">
          <span class="pms-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 2)) }}</span>
          <span class="pms-user-meta">
            <strong>{{ auth()->user()->name }}</strong>
            <span>{{ auth()->user()->email }}</span>
          </span>
        </a>
        <div class="dropdown-menu dropdown-menu-right">
          <a class="dropdown-item" href="{{ url('/profile') }}"><i class="fas fa-user mr-1"></i> My Profile</a>
          <a class="dropdown-item" href="{{ route('logout') }}"
          onclick="event.preventDefault(); document.getElementById('pms-logout-form').submit();"><i class="fas fa-power-off mr-1"></i> Logout</a>
          <form id="pms-logout-form" action="{{ route('logout') }}" method="POST" style="display:none;">{{ csrf_field() }}</form>
        </div>
      </div>
    </div>
    <nav aria-label="PMS" class="pms-nav">
      @php
        $pmsRole = auth()->user()->role->role ?? null;
        $pmsTabs = [
          ['route' => 'pms.dashboard', 'is' => 'pms.dashboard', 'label' => 'Dashboard'],
          ['route' => 'pms.jobs.index', 'is' => 'pms.jobs.*', 'label' => 'Jobs'],
          ['route' => 'pms.components.index', 'is' => ['pms.components.*', 'pms.readings.*'], 'label' => 'Components'],
          ['route' => 'pms.counters.index', 'is' => 'pms.counters.*', 'label' => 'Counters'],
          ['route' => 'pms.parts.index', 'is' => 'pms.parts.*', 'label' => 'Required Parts'],
          ['route' => 'pms.reports.index', 'is' => 'pms.reports.*', 'label' => 'Reports'],
        ];
        if ($pmsRole === 'super-admin') {
          $pmsTabs[] = ['route' => 'pms.master.index', 'params' => 'locations', 'is' => 'pms.master.*', 'label' => 'Master Data'];
        }
      @endphp
      @foreach($pmsTabs as $tab)
      <a href="{{ route($tab['route'], $tab['params'] ?? []) }}" class="pms-nav-link {{ Route::is($tab['is']) ? 'active' : '' }}" @if(Route::is($tab['is'])) aria-current="page" @endif>{{ $tab['label'] }}</a>
      @endforeach
      <span class="pms-nav-spacer"></span>
      @if(isset($vessels) && $vessels->isNotEmpty() && !Route::is('pms.reports.*'))
      <form method="GET" action="{{ url()->current() }}" class="pms-vessel-form">
        Vessel
        <select name="vessel_id" class="pms-vessel-select" id="pms-tab-vessel" aria-label="Vessel">
          @if(Route::is('pms.dashboard'))
          <option value="" {{ empty($vessel) ? 'selected' : '' }}>All vessels (fleet)</option>
          @endif
          @foreach($vessels as $v)
          <option value="{{ $v->id }}" {{ !empty($vessel) && $vessel->id === $v->id ? 'selected' : '' }}>{{ $v->name }}</option>
          @endforeach
        </select>
      </form>
      <script>document.getElementById('pms-tab-vessel').addEventListener('change', function () { this.form.submit(); });</script>
      @elseif(isset($vessel) && $vessel)
      <span class="pms-vessel-name">{{ $vessel->name }}</span>
      @endif
    </nav>
  </header>

  <main class="pms-main">
    <div class="row">
      @yield('main-content')
    </div>
  </main>

  <footer class="site-footer " style="background: #fff">
    <div class="footer-inner" >
      <div class="row">
        <div class="col-sm-6">
          Copyright &copy; {{ date('Y') }}.
বাংলাদেশ শিপিং কর্পোরেশন
        </div>
        <div class="col-sm-6 text-right">
          Powered by <a href="https://www.ennvisiodigital.tech">Ennvisio Digital</a>
        </div>
      </div>
    </div>
  </footer>

@include('partials.body-scripts')
</body>
</html>
