{{-- Every script admin-master.blade.php and layouts.pms load, in the same order, so a
	 page's own @section('home-js') always finds jQuery/Bootstrap/DataTables/sweetalert/
	 toastr already in place, wherever it's rendered from. --}}
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
    // Server-paginated lists (data-server-paginated) page and search on the server.
    $('#example').not('[data-server-paginated]').not('[data-no-datatable]').DataTable();
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
