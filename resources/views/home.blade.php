@extends('layouts.admin-master')
@section('main-content')

{{-- GM (SRD)'s own requisition queue - HomeController@index only computes
     $stats for that role, so this stays invisible for super-admin (not
     part of the approval chain) without an extra check here. --}}
@isset($stats)
@include('partials.requisition-stat-cards')
@endisset

<div class="col-lg-12">
    <div class="row">
        <div class="col-6 col-md-3 mb-3">
            <a href="{{url('/home/certificate')}}" class="text-decoration-none">
                <div class="srd-stat-card">
                    <div class="srd-stat-icon is-indigo"><i class="fas fa-certificate"></i></div>
                    <div class="srd-stat-body">
                        <div class="srd-stat-label">Certificates</div>
                        <div class="srd-stat-value"><span class="count">{{\App\Certificate::where('status',true)->get()->count()}}</span></div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-3 mb-3">
            <a href="{{url('/home/survey')}}" class="text-decoration-none">
                <div class="srd-stat-card">
                    <div class="srd-stat-icon is-blue"><i class="fas fa-clipboard"></i></div>
                    <div class="srd-stat-body">
                        <div class="srd-stat-label">Surveys</div>
                        <div class="srd-stat-value"><span class="count">{{\App\Survey::where('status',true)->get()->count()}}</span></div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-3 mb-3">
            <a href="{{url('/home/vessel')}}" class="text-decoration-none">
                <div class="srd-stat-card">
                    <div class="srd-stat-icon is-pink"><i class="fas fa-ship"></i></div>
                    <div class="srd-stat-body">
                        <div class="srd-stat-label">Vessels</div>
                        <div class="srd-stat-value"><span class="count">{{\App\Vessel::where('status',true)->get()->count()}}</span></div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-3 mb-3">
            <a href="{{url('/home/item')}}" class="text-decoration-none">
                <div class="srd-stat-card">
                    <div class="srd-stat-icon is-amber"><i class="fas fa-th"></i></div>
                    <div class="srd-stat-body">
                        <div class="srd-stat-label">Categories</div>
                        <div class="srd-stat-value"><span class="count">{{\App\Category::where('status',true)->get()->count()}}</span></div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-3 mb-3">
            <a href="{{url('/home/item')}}" class="text-decoration-none">
                <div class="srd-stat-card">
                    <div class="srd-stat-icon is-teal"><i class="fas fa-cubes"></i></div>
                    <div class="srd-stat-body">
                        <div class="srd-stat-label">Items</div>
                        <div class="srd-stat-value"><span class="count">{{\App\Item::where('status',true)->get()->count()}}</span></div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-3 mb-3">
            <a href="{{url('/home/order')}}" class="text-decoration-none">
                <div class="srd-stat-card">
                    <div class="srd-stat-icon is-green"><i class="fas fa-truck"></i></div>
                    <div class="srd-stat-body">
                        <div class="srd-stat-label">Delivered</div>
                        <div class="srd-stat-value"><span class="count">{{\App\Order::where('ord_status',true)->where('status','delivered')->get()->count()}}</span></div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-3 mb-3">
            <a href="{{url('/home/order')}}" class="text-decoration-none">
                <div class="srd-stat-card">
                    <div class="srd-stat-icon is-slate"><i class="fas fa-inbox"></i></div>
                    <div class="srd-stat-body">
                        <div class="srd-stat-label">Received</div>
                        <div class="srd-stat-value"><span class="count">{{\App\Order::where('ord_status',true)->where('status','received')->get()->count()}}</span></div>
                    </div>
                </div>
            </a>
        </div>
    </div>
</div>

<div class="col-lg-12 col-md-12">
    <div class="card">
        <div class="card-header pv-card-hader st-1">
            <strong class="pptitle">Validity of Certificates</strong>

            <div class="right-buttons">
                <button class="btn btn-info bsc-zoom"  data-toggle="modal" data-target="#summary-table-modal"><i class="fas fa-search-plus"></i> Zoom</button>              
            </div>
        </div>

        {{-- One summary row per vessel, expanding to its certificates by
             category - see App\CertificateValidityReport for why the old
             vessels x certificate-type grid no longer fits. Deliberately NOT
             class "certificate-report-table": admin-master auto-initialises
             that class as a DataTable, which can't handle the expandable
             detail rows below. --}}
        <div class="card-body certificate-report-table-wrapper" id="summary-table-wrapper">
            <table class="table table-bordered cv-table" style="width:100%">
                <thead>
                    <tr class="th">
                        <th>Name of Vessel</th>
                        <th class="text-center">Total</th>
                        <th class="text-center">Expired</th>
                        <th class="text-center">Due &le; {{ \App\VesselCertificate::DUE_WITHIN_DAYS }} days</th>
                        <th class="text-center">Valid</th>
                        <th>Next Expiry</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($certificateReport ?? [] as $row)
                    <tr class="cv-vessel-row {{ $row['total'] ? '' : 'cv-empty' }}" data-vessel="{{ $row['vessel']->id }}" @if($row['total']) title="Click to see certificates" @endif>
                        <td>
                            @if($row['total'])<i class="fas fa-chevron-right cv-chevron"></i>@endif
                            <span class="vessal-name">{{ $row['vessel']->name }}</span>
                        </td>
                        <td class="text-center">{{ $row['total'] }}</td>
                        <td class="text-center">
                            @if($row['expired'])<span class="badge badge-danger cv-count">{{ $row['expired'] }}</span>@else<span class="text-muted">0</span>@endif
                        </td>
                        <td class="text-center">
                            @if($row['due'])<span class="badge badge-warning cv-count">{{ $row['due'] }}</span>@else<span class="text-muted">0</span>@endif
                        </td>
                        <td class="text-center">
                            @if($row['valid'])<span class="badge badge-success cv-count">{{ $row['valid'] }}</span>@else<span class="text-muted">0</span>@endif
                        </td>
                        <td class="{{ \App\ExpiryHelper::cssClass($row['next_expiry']) }}">
                            @if($row['next_expiry'])
                            {{ \Carbon\Carbon::parse($row['next_expiry'])->format('d M Y') }}
                            @elseif($row['all_permanent'])
                            Permanent
                            @elseif(! $row['total'])
                            <span class="text-muted">No certificates recorded</span>
                            @else
                            <span class="text-muted">&mdash;</span>
                            @endif
                        </td>
                    </tr>
                    @if($row['total'])
                    <tr class="cv-detail-row" data-vessel="{{ $row['vessel']->id }}" style="display:none;">
                        <td colspan="6" class="cv-detail-cell">
                            <table class="table table-sm mb-0 cv-detail-table">
                                <thead>
                                    <tr>
                                        <th>Certificate</th>
                                        <th>Issued</th>
                                        <th>Expires</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($row['groups'] as $categoryName => $certs)
                                    <tr class="cv-category-row"><td colspan="4">{{ $categoryName }}</td></tr>
                                    @foreach($certs as $cert)
                                    @php
                                        $status = $cert->validityStatus();
                                        $days = $cert->daysUntilExpiry();
                                    @endphp
                                    <tr>
                                        <td>{{ $cert->title }}</td>
                                        <td>{{ $cert->issue_date ? \Carbon\Carbon::parse($cert->issue_date)->format('d M Y') : '—' }}</td>
                                        <td class="{{ $cert->is_permanent ? '' : \App\ExpiryHelper::cssClass($cert->exp_date) }}">
                                            {{ $cert->is_permanent ? 'Permanent' : ($cert->exp_date ? \Carbon\Carbon::parse($cert->exp_date)->format('d M Y') : '—') }}
                                        </td>
                                        <td>
                                            @switch($status)
                                                @case('expired')
                                                    <span class="badge badge-danger">Expired</span>
                                                    <small class="text-muted">{{ number_format(abs($days)) }} {{ abs($days) == 1 ? 'day' : 'days' }} ago</small>
                                                    @break
                                                @case('due')
                                                    <span class="badge badge-warning">Due</span>
                                                    <small class="text-muted">{{ $days == 0 ? 'today' : $days.' '.($days == 1 ? 'day' : 'days').' left' }}</small>
                                                    @break
                                                @case('valid')
                                                    <span class="badge badge-success">Valid</span>
                                                    <small class="text-muted">{{ number_format($days) }} days left</small>
                                                    @break
                                                @case('permanent')
                                                    <span class="badge badge-info">Permanent</span>
                                                    @break
                                                @default
                                                    <span class="badge badge-secondary">No expiry date</span>
                                            @endswitch
                                        </td>
                                    </tr>
                                    @endforeach
                                    @endforeach
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    @endif
                    @empty
                    <tr><td colspan="6" class="text-center text-muted">No vessels.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="col-lg-12 col-md-12">
    <div class="card">
        <div class="card-header pv-card-hader st-1">
            <strong class="pptitle">Survey Report</strong>

            <div class="right-buttons-1"> 
                <button class="btn btn-info bsc-zoom"  data-toggle="modal" data-target="#summary-table-modal"><i class="fas fa-search-plus"></i> Zoom</button>
            </div>
        </div>

        <div class="card-body" id="summary-table-wrapper">
            <div class="header text-center">
                <div class="center">
                     <!-- <h1>Bangladesh Shipping Corporation</h1>
                     <p class="lead">BSC Bhaban, Saltgola Road, Chittagong</p>
                     <p>Ship Repair Department</p>  -->
                </div>    
            </div>
            <!-- survey-report-table -->
            <table id="summary-table" class="survey-report-table table table-striped table-bordered" style="width:100%">
                <thead>
                    @if(!empty($vessels))
                    @foreach($vessels as $v)
                    @if($loop->first)
                    <tr class="th">
                        <th rowspan="2">Name of Vessels</th>
                        @if(!empty($surveys ))
                        @foreach($surveys as $s)
                        <th colspan="2"> {{!empty($s->name) ? $s->name:''}}</th>
                        @endforeach
                        @endif
                        <!-- <th>Renewal Survay</th> -->
                        <th rowspan="2">Remark</th>
                    </tr>
                    <tr class="th">
                        <!-- <th>Name of Vessels</th> -->
    
                        <th>Done Date</th>
                        <th>Expire Date</th>
    
                        <th>Done Date</th>
                        <th>Expire Date</th>
    
                        <th>Done Date</th>
                        <th>Expire Date</th>
    
                        <th>Done Date</th>
                        <th>Expire Date</th>
    
                        <!-- <th>Remark</th> -->
                    </tr>
    
                </thead>
    
                <tbody>
                    @endif
    
                    <tr>
                        <td>
                            <span class="vessal-name">{{$v->name}}</span> <br>
                            <span class="location">China 9/18</span>
                        </td>
                        @if(!empty($surveys ))
                        @foreach($surveys as $s)
                        <td colspan="2" style="padding:0;"> 
                            <table border="0" cellpadding="0" width='100%' style="border:0px">
                                <tr></tr>
                                @php
                                    // Same as the certificate table above: a vessel can have several
                                    // vessel_surveys rows for the same survey type (one per cycle),
                                    // so both dates must come from the SAME most-recent row rather
                                    // than two independent, potentially mismatched first() calls.
                                    $matchedSurvey = $s->vesselSurveys
                                        ->whereIn('survey_id',$s->id)
                                        ->whereIn('vessel_id',$v->id)
                                        ->sortByDesc('survey_exp_date')
                                        ->first();
                                    $surveyDoneDate = !empty($matchedSurvey->survey_date) ? $matchedSurvey->survey_date : '';
                                    $surveyExpDate = !empty($matchedSurvey->survey_exp_date) ? $matchedSurvey->survey_exp_date : '';
                                @endphp
                                <tr>
                                    <td style="border: none!important;background: inherit!important">
                                        {{ $surveyDoneDate }}
                                    </td>
                                   <td class="{{ \App\ExpiryHelper::cssClass($surveyExpDate) }}" style="border: none!important">
                                        {{ $surveyExpDate }}
                                    </td>
                                </tr>
                                
                            </table>
    
                        </td>
                        @endforeach
                        @endif
                        
                        <td></td>
                    </tr>
                    @endforeach
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>

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


<!-- Modal -->
<div class="modal fade bd-example-modal-lg" id="summary-table-modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">
                    <b></b>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                  <span aria-hidden="true">&times;</span>
              </button>
            </div>
            <div class="modal-body">
                <div class="card"> 
                    <div class="card-body" id="summary-table-wrapper">
    
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .cv-table .cv-vessel-row:not(.cv-empty) { cursor: pointer; }
    .cv-table .cv-vessel-row:not(.cv-empty):hover { background: #f4f8f8; }
    .cv-table .cv-vessel-row.cv-open { background: #eef5f4; }
    .cv-table .cv-chevron { width: 14px; margin-right: 6px; color: #6b7a82; transition: transform .15s ease; }
    .cv-table .cv-open .cv-chevron { transform: rotate(90deg); }
    .cv-table .cv-count { font-size: 13px; min-width: 26px; }
    .cv-table .cv-detail-cell { background: #fafcfc; padding: 8px 12px 12px 34px; }
    .cv-table .cv-detail-table th { border-top: none; font-size: 12px; color: #6b7a82; font-weight: 600; }
    .cv-table .cv-category-row td {
        background: #eef2f2; font-size: 11.5px; font-weight: 700; letter-spacing: .04em;
        text-transform: uppercase; color: #4c5958; padding: 5px 8px;
    }
</style>
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
@endsection
