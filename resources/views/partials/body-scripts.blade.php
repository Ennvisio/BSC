{{-- Every script admin-master.blade.php and layouts.pms load, in the same order, so a
	 page's own @section('home-js') always finds jQuery/Bootstrap/DataTables/sweetalert/
	 toastr already in place, wherever it's rendered from. --}}
{{-- Third-party JS is self-hosted under assets/vendor (no CDN) - same versions as before. --}}
<script src="{{ asset('/assets/vendor/jquery/jquery-3.3.1.js') }}" type="text/javascript"></script>
<script src="{{ asset('/assets/vendor/popper/popper.min.js') }}"></script>
<script src="{{ asset('/assets/vendor/bootstrap/js/bootstrap.min.js') }}" ></script>
<script src="{{ asset('/assets/vendor/datatables/js/jquery.dataTables.min.js') }}" type="text/javascript"></script>
<script src="{{ asset('/assets/vendor/datatables/js/dataTables.bootstrap4.min.js') }}" type="text/javascript"></script>
<script src="{{ asset('/assets/vendor/datatables/js/dataTables.buttons.min.js') }}" type="text/javascript"></script>
<script src="{{ asset('/assets/vendor/pdfmake/pdfmake.min.js') }}" type="text/javascript"></script>
<script src="{{ asset('/assets/vendor/pdfmake/vfs_fonts.js') }}" type="text/javascript"></script>
<script src="{{ asset('/assets/vendor/datatables/js/buttons.html5.min.js') }}" type="text/javascript"></script>
{{-- Our own JS, as opposed to the vendor files above - versioned off its
	 own mtime (same trick partials.head-assets already uses for
	 css/srd-theme.css) so a deploy's changes show up on the very next load
	 instead of waiting out whatever the browser cached the old file as. --}}
<script type="text/javascript" src="{{ asset('js/print-pdf-custom.js') }}?v={{ filemtime(public_path('js/print-pdf-custom.js')) }}"></script>
<script src="{{ asset('/assets/vendor/toastr/toastr.min.js') }}"></script>
<script src="{{ asset('/assets/sweetalert2/dist/sweetalert2.min.js') }}"></script>
<script type="text/javascript" src="{{ asset('js/zebra_datepicker.min.js') }}"></script>
<script type="text/javascript" src="{{ asset('js/dataForm.js') }}?v={{ filemtime(public_path('js/dataForm.js')) }}"></script>
<script type="text/javascript" src="{{ asset('js/datalist.js') }}?v={{ filemtime(public_path('js/datalist.js')) }}"></script>
@yield('home-js')
@yield('print-pdf-custom-js')
<script src="{{url('/')}}/assets/js/main.js"></script>
<script src="{{ asset('js/srd-theme.js') }}?v={{ filemtime(public_path('js/srd-theme.js')) }}"></script>
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
