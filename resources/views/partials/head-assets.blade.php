{{-- Everything admin-master.blade.php and layouts.pms put in <head> - one copy so the
	 two shells can never drift apart on fonts, icon sets or library versions. --}}
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title>Ship Repair Department | Bangladesh Shipping Corporation</title>
<meta name="description" content="Ship Repair Department | Bangladesh Shipping Corporation">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="apple-touch-icon" href="{{asset('/images/logo.png')}}">
<link rel="shortcut icon" href="{{asset('/images/logo.png')}}">
{{-- Third-party CSS/fonts are self-hosted under assets/vendor (no CDN) so the
	 app works with no internet access - see assets/vendor/README.md. --}}
<link href="{{ asset('/assets/vendor/fontawesome/css/all.css') }}" rel="stylesheet">
<link rel="stylesheet" type="text/css" href="{{ asset('/assets/vendor/toastr/toastr.css') }}">
<link rel="stylesheet" type="text/css" href="{{ asset('/assets/sweetalert2/dist/sweetalert2.min.css') }}">
<link rel="stylesheet" href="{{ asset('/assets/vendor/bootstrap/css/bootstrap.css') }}">
<link rel="stylesheet" href="{{ asset('/assets/vendor/datatables/css/dataTables.bootstrap4.min.css') }}">
<link rel="stylesheet" href="{{ asset('/assets/vendor/themify-icons/css/themify-icons.css') }}">
<link rel="stylesheet" href="{{ asset('/assets/vendor/pe-icon-7-stroke/css/pe-icon-7-stroke.min.css') }}">
<link rel="stylesheet" href="{{url('/css/zebra_datepicker.min.css')}}">
<link rel="stylesheet" href="{{url('/assets/css/style.css')}}">
{{-- Our own stylesheets, as opposed to the template's assets/css/style.css
	 above - versioned the same way css/srd-theme.css already is below, so a
	 deploy's CSS changes show up on the next load instead of whatever the
	 browser cached before. --}}
<link rel="stylesheet" href="{{url('/css/style.css')}}?v={{ filemtime(base_path('css/style.css')) }}">
<link rel="stylesheet" href="{{url('/css/vesselFormstyle.css')}}?v={{ filemtime(base_path('css/vesselFormstyle.css')) }}">
<!-- SRD theme — extends Bootstrap's own classes, see css/srd-theme.css -->
<link rel="stylesheet" href="{{ asset('/assets/vendor/fonts/public-sans.css') }}">
<link rel="stylesheet" href="{{url('/css/srd-theme.css')}}?v={{ file_exists(base_path('css/srd-theme.css')) ? filemtime(base_path('css/srd-theme.css')) : 1 }}">
