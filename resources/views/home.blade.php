@extends('layouts.admin-master')
@section('main-content')

{{-- GM (SRD)'s own requisition queue - HomeController@index only computes
     $stats for that role, so this stays invisible for super-admin (not
     part of the approval chain) without an extra check here. --}}
@isset($stats)
@include('partials.requisition-stat-cards')
@endisset

@if(!empty($overview))
{{-- Super-admin: item and service requisitions side by side, fleet-wide.
     Each card opens its list filtered to exactly what it counts. --}}
@php
    $overviewCards = [
        'pending' => ['Pending', 'fa-hourglass-half', 'is-blue'],
        'delivered' => ['Delivered', 'fa-truck', 'is-green'],
        'received' => ['Received', 'fa-inbox', 'is-indigo'],
        'rejected' => ['Rejected', 'fa-times-circle', 'is-pink'],
    ];
    $overviewRows = [
        'item' => ['Item Requisition', 'fa-list-alt', url('/home/order')],
        'service' => ['Service Requisition', 'fa-wrench', route('service-requisition.index')],
    ];
@endphp
@foreach($overviewRows as $kind => [$heading, $headIcon, $listUrl])
<div class="col-lg-12">
    <div class="srd-stat-section"><i class="fas {{ $headIcon }}"></i> {{ $heading }}</div>
    <div class="row">
        @foreach($overviewCards as $status => [$label, $icon, $tone])
        <div class="col-6 col-md-3 mb-3">
            <a href="{{ $listUrl }}?status={{ $status }}" class="text-decoration-none">
                <div class="srd-stat-card">
                    <div class="srd-stat-icon {{ $tone }}"><i class="fas {{ $icon }}"></i></div>
                    <div class="srd-stat-body">
                        <div class="srd-stat-label">{{ $label }}</div>
                        <div class="srd-stat-value">{{ $overview[$kind][$status] }}</div>
                    </div>
                </div>
            </a>
        </div>
        @endforeach
    </div>
</div>
@endforeach
@endif
@include('partials.fleet-overview', [
    'fleetRequisitionTotals' => empty($overview),
])

<!--<div class="col-lg-6 col-md-6">-->
<!--    <div class="card ">-->
<!--        <div class="card-header pv-card-hader">-->
<!--            <strong class="pptitle">Requisition Report</strong>-->

<!--            <div class="right-buttons d-none">                -->
<!--            </div>-->
<!--        </div>-->
        
<!--        <div class="card-body">-->
<!--           <canvas id="lineChart"></canvas>-->
<!--       </div>-->
<!--   </div>-->
<!--</div>-->


@endsection
@section('home-js')
<script type="text/javascript" src="{{ asset('assets/js/Chart.bundle.js') }}"></script>
<script type="text/javascript" src="{{ asset('assets/js/utils.js') }}"></script>
<script>
    (function($){
        "use strict";

        //line
        // var ctxL = document.getElementById("lineChart").getContext('2d');
        // var myLineChart = new Chart(ctxL, {
        //     type: 'line',
        //     data: {
        //         labels: ["January", "February", "March", "April", "May", "June", "July"],
        //         datasets: [{
        //           label: "Received Requisitions",
        //           data: [65, 59, 80, 81, 56, 55, 40],
        //           backgroundColor: [
        //           'rgba(105, 0, 132, .2)',
        //           ],
        //           borderColor: [
        //           'rgba(200, 99, 132, .7)',
        //           ],
        //           borderWidth: 2
        //         },
        //         {
        //           label: "Approved Requisitions",
        //           data: [28, 48, 40, 19, 86, 27, 90],
        //           backgroundColor: [
        //           'rgba(0, 137, 132, .2)',
        //           ],
        //           borderColor: [
        //           'rgba(0, 10, 130, .7)',
        //           ],
        //           borderWidth: 2
        //         }
        //       ]
        //     },
        //     options: {
        //         responsive: true
        //     }
        // });

    })(jQuery);
</script>
@include('partials.fleet-overview-scripts')
@endsection
