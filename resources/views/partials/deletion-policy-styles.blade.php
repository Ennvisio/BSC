{{-- ADR-006: vessel users do not delete records, and nobody deletes on a
     vessel server. The server refuses the actions (App\Support\DeletionPolicy,
     RefuseVesselUserDeletes); this only stops the screens offering them,
     including the rows that js/dataForm.js adds without a reload.
     ADR-004: only a Superadmin blocks a user. --}}
@auth
@php($__deletePolicy = app(\App\Support\DeletionPolicy::class))
@if(! $__deletePolicy->userMayDelete(auth()->user()))
<style>
  .delete-budget-group, .delete-cert-category, .delete-item, .delete-category,
  .delete-survey, .delete-certificate, .toggle-vessel-status, .delete-equipment,
  .pms-delete, .pms-reading-delete, .pms-master-delete, .pj-part-delete,
  .file-delete, .permanent_delete { display: none !important; }
</style>
@endif
@if((auth()->user()->role->role ?? null) !== 'super-admin')
<style>.delete-user { display: none !important; }</style>
@endif
@endauth
