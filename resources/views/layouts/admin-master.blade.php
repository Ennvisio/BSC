<!doctype html>
<html class="no-js" lang="">
<head> 
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title>Ship Repair Department | Bangladesh Shipping Corporation</title>
  <meta name="description" content="Ship Repair Department | Bangladesh Shipping Corporation">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <link rel="apple-touch-icon" href="{{asset('/images/logo.png')}}">
  <link rel="shortcut icon" href="{{asset('/images/logo.png')}}">
  <link href="https://use.fontawesome.com/releases/v5.0.6/css/all.css" rel="stylesheet">
  <link rel="stylesheet" type="text/css" href="//cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/css/toastr.css">
  <link rel="stylesheet" type="text/css" href="{{ asset('/assets/sweetalert2/dist/sweetalert2.min.css') }}">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.1.1/css/bootstrap.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.10.19/css/dataTables.bootstrap4.min.css">
  <!-- <link rel="stylesheet" href="https://cdn.datatables.net/buttons/1.5.2/css/buttons.dataTables.min.css"> -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/lykmapipo/themify-icons@0.1.2/css/themify-icons.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/pixeden-stroke-7-icon@1.2.3/pe-icon-7-stroke/dist/pe-icon-7-stroke.min.css">
  <link rel="stylesheet" href="{{url('/css/zebra_datepicker.min.css')}}">
  <link rel="stylesheet" href="{{url('/assets/css/style.css')}}">
  <link rel="stylesheet" href="{{url('/css/style.css')}}">
  <link rel="stylesheet" href="{{url('/css/vesselFormstyle.css')}}">
  <!-- SRD theme — extends Bootstrap's own classes, see css/srd-theme.css -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{url('/css/srd-theme.css')}}?v={{ file_exists(base_path('css/srd-theme.css')) ? filemtime(base_path('css/srd-theme.css')) : 1 }}">
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

      <footer class="site-footer " style="background: #c9cab8">
        <div class="footer-inner" >
          <div class="row">
            <div class="col-sm-6">
              Copyright &copy; 2019.
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

<script src="https://code.jquery.com/jquery-3.3.1.js" type="text/javascript"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js" ></script>
<script src="https://cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js" type="text/javascript"></script>
<script src="https://cdn.datatables.net/1.10.19/js/dataTables.bootstrap4.min.js" type="text/javascript"></script>
<script src="https://cdn.datatables.net/buttons/1.5.2/js/dataTables.buttons.min.js" type="text/javascript"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/pdfmake.min.js" type="text/javascript"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/vfs_fonts.js" type="text/javascript"></script>
<script src="https://cdn.datatables.net/buttons/1.5.2/js/buttons.html5.min.js" type="text/javascript"></script>
<script type="text/javascript" src="{{ asset('js/print-pdf-custom.js') }}"></script>
<script src="//cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<script src="{{ asset('/assets/sweetalert2/dist/sweetalert2.min.js') }}"></script>
<script type="text/javascript" src="{{ asset('js/zebra_datepicker.min.js') }}"></script>
<script type="text/javascript" src="{{ asset('js/dataForm.js') }}"></script>
<script type="text/javascript" src="{{ asset('js/datalist.js') }}"></script>
@yield('home-js')
@yield('print-pdf-custom-js')
<script src="{{url('/')}}/assets/js/main.js"></script>
<script src="{{ asset('js/srd-theme.js') }}"></script>
@yield('pie-flot')
@yield('create-order-js')
<script type="text/javascript">
  $(document).ready(function(){
    $('#example').DataTable();
    $('input.date').Zebra_DatePicker({
      format: 'Y-m-d'
    });
    $(function () {
      $('[data-toggle="tooltip"]').tooltip()
    })

    $('.survey-report-table').DataTable();
    $('.certificate-report-table').DataTable();
  });
</script>
@yield('file-validate')
</body>
</html>
