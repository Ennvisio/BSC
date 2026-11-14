{{-- Behaviour for partials.fleet-overview - include inside the page's own
     @section('home-js'). Plain DOM, no jQuery dependency: this runs as soon
     as the page's section is reached, and a missing/late jQuery must not
     take the expand/collapse toggles down with it. Capture-phase listeners
     on document, so the theme/DataTables scripts can't cut a click off and
     the Zoom modal's copy of the table works too (it's inserted later). --}}
<script>
    (function () {
        "use strict";

        function setRowsDisplay(rows, open) {
            rows.forEach(function (r) { r.style.display = open ? 'table-row' : 'none'; });
        }

        document.addEventListener('click', function (e) {
            if (!e.target.closest) { return; }

            // Vessel row -> its certificate detail table.
            // State lives on the row's own class, not :visible - that reads
            // layout, which is wrong for a table inside a container that isn't
            // shown yet (the Zoom modal while it animates open).
            var vesselRow = e.target.closest('.cv-vessel-row:not(.cv-empty)');
            if (vesselRow) {
                var open = !vesselRow.classList.contains('cv-open');
                vesselRow.classList.toggle('cv-open', open);

                var vesselId = vesselRow.getAttribute('data-vessel');
                var details = Array.prototype.filter.call(
                    vesselRow.parentNode.querySelectorAll('.cv-detail-row'),
                    function (d) { return d.getAttribute('data-vessel') === vesselId; }
                );
                setRowsDisplay(details, open);
                return;
            }

            // Category row inside an expanded vessel -> that category's
            // certificates (the rows after it, up to the next category).
            var catRow = e.target.closest('.cv-category-row');
            if (catRow) {
                var catOpen = !catRow.classList.contains('cv-cat-open');
                catRow.classList.toggle('cv-cat-open', catOpen);

                var certs = [];
                for (var next = catRow.nextElementSibling; next && !next.classList.contains('cv-category-row'); next = next.nextElementSibling) {
                    if (next.classList.contains('cv-cert-row')) { certs.push(next); }
                }
                setRowsDisplay(certs, catOpen);
                return;
            }

            // Zoom: copy the card's table into the modal (Bootstrap opens it
            // through the button's own data-toggle).
            var zoom = e.target.closest('.bsc-zoom');
            if (zoom) {
                var card = zoom.closest('.card');
                var title = card.querySelector('.card-header .pptitle');
                var body = card.querySelector('.card-body');
                var modal = document.getElementById('summary-table-modal');
                if (!modal || !body) { return; }

                modal.querySelector('.modal-title').innerHTML = title ? title.innerHTML : '';
                modal.querySelector('#summary-table-wrapper').innerHTML = body.innerHTML;
            }
        }, true);
    })();
</script>
