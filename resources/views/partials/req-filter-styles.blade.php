{{-- The filter panel above a requisition list (Vessel / Category or Type /
     dates / Search) - layouts/order.blade.php and
     layouts/service-requisition-index.blade.php. --}}
<style>
.req-filter{ background:#f6f9f9; border:1px solid #dfe5e4; border-radius:12px; padding:16px 18px; margin-bottom:18px; }
.req-filter-fields{ display:flex; flex-wrap:wrap; align-items:flex-end; gap:14px 16px; }
.req-filter-field{ flex:1 1 200px; min-width:160px; }
.req-filter-date{ flex:0 1 170px; min-width:150px; }
.req-filter-field label{ display:block; margin:0 0 5px; font-size:11px; font-weight:600; letter-spacing:.07em; text-transform:uppercase; color:#6b7877; }
.req-filter .form-control{ height:38px; font-size:14px; background-color:#fff; }
.req-filter-input{ position:relative; }
.req-filter-actions{ display:flex; gap:8px; margin-left:auto; }
.req-filter-actions .btn{ height:38px; padding:0 18px; display:inline-flex; align-items:center; justify-content:center; gap:6px; }
.req-filter-summary{ margin-top:14px; padding-top:12px; border-top:1px dashed #d3dcdb; font-size:13.5px; color:#44524f; display:flex; flex-wrap:wrap; align-items:center; gap:6px 8px; }
.req-chip{ background:#e3f1f1; color:#0a5d57; border-radius:999px; padding:2px 11px; font-size:12.5px; font-weight:500; }
@media (max-width: 575px){ .req-filter-field, .req-filter-date{ flex:1 1 100%; } .req-filter-actions{ width:100%; margin-left:0; } .req-filter-actions .btn{ flex:1; } }
</style>
