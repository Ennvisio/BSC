@extends('layouts.admin-master')
@section('main-content')
<div class="col-lg-6 col-xl-12  form-wrap">
  <div class="card">
    <div class="card-header pv-card-hader">
      <strong class="pptitle">Vessel Details Form</strong>
  </div>
  <!-- ./pv-card-hader -->
  <!-- card-body -->
  <div class="card-body">
      @include('layouts.vessel-detail-form')
  </div>
</div>
</div>
@endsection