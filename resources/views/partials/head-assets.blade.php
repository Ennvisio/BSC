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
<link href="https://use.fontawesome.com/releases/v5.0.6/css/all.css" rel="stylesheet">
<link rel="stylesheet" type="text/css" href="//cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/css/toastr.css">
<link rel="stylesheet" type="text/css" href="{{ asset('/assets/sweetalert2/dist/sweetalert2.min.css') }}">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.1.1/css/bootstrap.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.10.19/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/lykmapipo/themify-icons@0.1.2/css/themify-icons.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/pixeden-stroke-7-icon@1.2.3/pe-icon-7-stroke/dist/pe-icon-7-stroke.min.css">
<link rel="stylesheet" href="{{url('/css/zebra_datepicker.min.css')}}">
<link rel="stylesheet" href="{{url('/assets/css/style.css')}}">
<link rel="stylesheet" href="{{url('/css/style.css')}}">
<link rel="stylesheet" href="{{url('/css/vesselFormstyle.css')}}">
<!-- SRD theme — extends Bootstrap's own classes, see css/srd-theme.css -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{url('/css/srd-theme.css')}}?v={{ file_exists(base_path('css/srd-theme.css')) ? filemtime(base_path('css/srd-theme.css')) : 1 }}">
