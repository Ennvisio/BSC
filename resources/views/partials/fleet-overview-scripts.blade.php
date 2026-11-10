{{-- Behaviour for partials.fleet-overview - include inside the page's own
     @section('home-js'). --}}
<script>
    (function($){
        "use strict";

        $('.bsc-zoom').on('click',function(){
            var header = $(this).parents('.card-header').find('.pptitle').html();
            var content = $(this).parents('.card').find('.card-body').html();

            $('#summary-table-modal').find('.modal-title').html(header);
            $('#summary-table-modal').find('#summary-table-wrapper').html(content);
        })

        // Delegated rather than bound per row: the Zoom button above copies
        // this table's HTML into the modal, and the copy has to expand too.
        $(document).on('click', '.cv-vessel-row:not(.cv-empty)', function () {
            var $row = $(this);
            var $detail = $row.closest('tbody').find('.cv-detail-row[data-vessel="' + $row.data('vessel') + '"]');
            // State lives on the row's own class, not :visible - that reads
            // layout, which is wrong for a table inside a container that isn't
            // shown yet (the Zoom modal while it animates open).
            var open = !$row.hasClass('cv-open');

            $detail.toggle(open);
            $row.toggleClass('cv-open', open);
        });
    })(jQuery);
</script>
