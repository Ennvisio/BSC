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
</head>
<body>
  <aside id="left-panel" class="left-panel" style="background: #f8f9e7;">
    <nav class="navbar navbar-expand-sm navbar-default w100" style="background: #f8f9e7;">
      <div id="main-menu" class="main-menu w100 collapse navbar-collapse">
        <ul class="nav navbar-nav w100">
          @if(!empty(auth()->user()->role->role) && (auth()->user()->role->role=='super-admin' ||
          auth()->user()->role->role=='gm-srd'|| auth()->user()->role->role=='admin'))

          <li class="{{Route::current()->uri() == 'home' ? 'active' : ''}}">
            <a href="{{url('/home')}}"><i class="menu-icon fa fa-laptop"></i><span class="left-menu-title">Dashboard</span> </a>
          </li>
          <li class="{{Route::current()->uri() == 'home/certificate' ? 'active' : ''}}">
            <a href="{{url('/home/certificate')}}"> <i class="menu-icon fa fa-cogs"></i><span class="left-menu-title">Certificate</span>
            </a>
          </li>
          <li class="{{Route::current()->uri() == 'home/survey' ? 'active' : ''}}">
            <a href="{{url('/home/survey')}}"> <i class="menu-icon fa fa-table"></i><span class="left-menu-title">Survey</span></a>
          </li>
          <li class="{{Route::current()->uri() == 'home/vessel' ? 'active' : ''}}">
            <a href="{{url('/home/vessel')}}">
             <i class="menu-icon fa fa-th"></i>
             <span class="left-menu-title">Vessels</span></a>
           </li>
           <li class="{{Route::current()->uri() == 'home/category' ? 'active' : ''}}">
            <a href="{{url('/home/category')}}">
             <i class="menu-icon fa fa-th"></i>
             <span class="left-menu-title">Category</span></a>
           </li>
           <li class="{{Route::current()->uri() == 'home/item' ? 'active' : ''}}">
            <a href="{{url('/home/item')}}">
             <i class="menu-icon fa fa-th"></i>
             <span class="left-menu-title">Items</span></a>
           </li>
           <li class="{{Route::current()->uri() == 'home/order' ? 'active' : ''}}">
            <a href="{{url('/home/order')}}">
             <i class="menu-icon fa fa-th"></i>
             <span class="left-menu-title">Requisitions</span></a>
           </li>
           @endif



           @if(!empty(auth()->user()->role->role) && 
           (auth()->user()->role->role=='second-engineer' || auth()->user()->role->role=='chief-officer'))
         
           
           <li class="{{Route::current()->uri() == 'home/category' ? 'active' : ''}}">
            <a href="{{url('/home/category')}}">
             <i class="menu-icon fa fa-th"></i>
             <span class="left-menu-title">Category</span></a>
           </li>
           <li class="{{Route::current()->uri() == 'home/item' ? 'active' : ''}}">
            <a href="{{url('/home/item')}}">
             <i class="menu-icon fa fa-th"></i>
             <span class="left-menu-title">Items</span></a>
           </li>
           <!-- <li class="{{Route::current()->uri() == 'home/created-orders' ? 'active' : ''}}">
            <a href="{{url('/home/created-orders')}}">
             <i class="menu-icon fa fa-th"></i>
             <span class="left-menu-title">Created Requisition</span></a>
           </li> -->
           <li class="{{Route::current()->uri() == 'home/order' ? 'active' : ''}}">
            <a href="{{url('/home/order')}}">
             <i class="menu-icon fa fa-th"></i>
             <span class="left-menu-title">Add Requisition</span></a>
           </li>
           <li class="{{Route::current()->uri() == 'approved/requisition' ? 'active' : ''}}">
            <a href="{{url('/approved/requisition')}}">
             <i class="menu-icon fa fa-th"></i>
             <span class="left-menu-title">Approved/Sent Requisition
             </span></a>
           </li>
           <li class="{{Route::current()->uri() == 'pending/requisition' ? 'active' : ''}}">
            <a href="{{url('/pending/requisition')}}">
             <i class="menu-icon fa fa-th"></i>
             <span class="left-menu-title">Pending Requisition From SSM</span></a>
           </li>
           <li class="{{Route::current()->uri() == 'received/requisition' ? 'active' : ''}}">
            <a href="{{url('/received/requisition')}}">
             <i class="menu-icon fa fa-th"></i>
             <span class="left-menu-title">Receiived Requisition
             </span></a>
           </li>
           @endif
           @if(!empty(auth()->user()->role->role) && (auth()->user()->role->role!='super-admin') &&
            (auth()->user()->role->role!='second-engineer') &&
            (auth()->user()->role->role!='chief-officer'))

           <li class="{{Route::current()->uri() == 'pending/requisition' ? 'active' : ''}}">
            <a href="{{url('/pending/requisition')}}">
             <i class="menu-icon fa fa-th"></i>
             <span class="left-menu-title">Pending Requisition</span></a>
           </li>
           <li class="{{Route::current()->uri() == 'approved/requisition' ? 'active' : ''}}">
            <a href="{{url('/approved/requisition')}}">
             <i class="menu-icon fa fa-th"></i>
             <span class="left-menu-title">Approved Requisition
             </span></a>
           </li>
           <li class="{{Route::current()->uri() == 'received/requisition' ? 'active' : ''}}">
            <a href="{{url('/received/requisition')}}">
             <i class="menu-icon fa fa-th"></i>
             <span class="left-menu-title">Receiived Requisition
             </span></a>
           </li>
           @endif
         </ul>
       </div>
     </nav>
   </aside>
   <div id="right-panel" class="right-panel">
    <header id="header" class="header" style="background: #007184">
      <div class="top-left">
        <div class="navbar-header" style="background: #007184;">
          <a class="navbar-brand" href="{{url('/home')}}" style="color: #fff;">
            <img style="width:40px;height: auto;" src="{{asset('/images/logo.png')}}" class="site-logo"> Ship Repair Department
          </a>
          <a  id="menuToggle" class="menutoggle"><i style="color: #fff;" class="fa fa-bars"></i></a>

        </div>
      </div>

      <div class="top-right">
        <div class="header-menu new">

          <div class="user-area create-user-area  float-left">
            @if(!empty(auth()->user()->role->role) && auth()->user()->role->role=='super-admin'||
            auth()->user()->role->role=='gm-srd' )
            <a href="{{url('/home/user')}}"><i style="color: #fff;" class="fa fa-user"></i> User</a> &nbsp;&nbsp;
            <a href="{{url('/home/trash')}}">
             <i class="menu-icon fas fa-trash-alt"></i>
             <span class="left-menu-title"> Trash</span></a>
             @endif
           </div> 
           <div class="user-area dropdown float-right">
            <a href="#" class="dropdown-toggle active" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
              <span class='admin_name' style="margin-right:20px;color:#fff;text-transform: capitalize;font-style: italic;">
              {{auth()->user()->name}}</span> <img class="user-avatar rounded-circle" src="{{!empty(auth()->user()->photo)?url('/'.auth()->user()->photo):url('/images/admin.jpg')}}" alt="User Avatar" style="    height: 40px;">
            </a> 
            <div class="user-menu dropdown-menu">
              <a class="nav-link" href="{{url('/profile')}}"><i class="fas fa-user"></i> My Profile</a>
              <a class="nav-link" href="{{ route('logout') }}"
              onclick="event.preventDefault();
              document.getElementById('logout-form').submit();"><i class="fas fa-power-off"></i> Logout
            </a>
            <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
              {{ csrf_field() }}
            </form>
          </div>
        </div>
      </div>
    </div>
  </header>
  <div class="content" style="background: #e9ead9">
    <div class="animated fadeIn">
      <div class="row"> 
        @yield('main-content')
      </div>
    </div>
  </div>
  <div class="clearfix"></div>
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
