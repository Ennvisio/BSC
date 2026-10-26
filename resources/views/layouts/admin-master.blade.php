<!doctype html>
<html class="no-js" lang="">
<head>
  @include('partials.head-assets')
</head>
<body class="srd">
  <div class="srd-shell">
    @include('partials.sidebar')

    <div class="srd-main">
      @include('partials.topbar')

      <div class="srd-content">
        <div class="row">
          @yield('main-content')
        </div>
      </div>

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
    </div>
  </div>

@include('partials.body-scripts')
</body>
</html>
