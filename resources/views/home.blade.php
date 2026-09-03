@extends('layouts.admin-master')
@section('main-content')

<div class="col-lg-12">
    <div class="row">
        <div class="col-6 col-md-4 col-xl mb-3 mb-xl-0">
            <a href="{{url('/home/certificate')}}" class="text-decoration-none">
                <div class="srd-stat-card">
                    <div class="srd-stat-icon"><i class="fas fa-certificate"></i></div>
                    <div class="srd-stat-value"><span class="count">{{\App\Certificate::where('status',true)->get()->count()}}</span></div>
                    <div class="srd-stat-label">Certificates</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-xl mb-3 mb-xl-0">
            <a href="{{url('/home/survey')}}" class="text-decoration-none">
                <div class="srd-stat-card">
                    <div class="srd-stat-icon"><i class="fas fa-clipboard"></i></div>
                    <div class="srd-stat-value"><span class="count">{{\App\Survey::where('status',true)->get()->count()}}</span></div>
                    <div class="srd-stat-label">Surveys</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-xl mb-3 mb-xl-0">
            <a href="{{url('/home/vessel')}}" class="text-decoration-none">
                <div class="srd-stat-card">
                    <div class="srd-stat-icon"><i class="fas fa-ship"></i></div>
                    <div class="srd-stat-value"><span class="count">{{\App\Vessel::where('status',true)->get()->count()}}</span></div>
                    <div class="srd-stat-label">Vessels</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-xl mb-3 mb-xl-0">
            <a href="{{url('/home/item')}}" class="text-decoration-none">
                <div class="srd-stat-card">
                    <div class="srd-stat-icon"><i class="fas fa-th"></i></div>
                    <div class="srd-stat-value"><span class="count">{{\App\Category::where('status',true)->get()->count()}}</span></div>
                    <div class="srd-stat-label">Categories</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-xl mb-3 mb-xl-0">
            <a href="{{url('/home/item')}}" class="text-decoration-none">
                <div class="srd-stat-card">
                    <div class="srd-stat-icon"><i class="fas fa-cubes"></i></div>
                    <div class="srd-stat-value"><span class="count">{{\App\Item::where('status',true)->get()->count()}}</span></div>
                    <div class="srd-stat-label">Items</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-xl mb-3 mb-xl-0">
            <a href="{{url('/home/order')}}" class="text-decoration-none">
                <div class="srd-stat-card">
                    <div class="srd-stat-icon"><i class="fas fa-truck"></i></div>
                    <div class="srd-stat-value"><span class="count">{{\App\Order::where('ord_status',true)->where('status','delivered')->get()->count()}}</span></div>
                    <div class="srd-stat-label">Delivered</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-xl mb-3 mb-xl-0">
            <a href="{{url('/home/order')}}" class="text-decoration-none">
                <div class="srd-stat-card">
                    <div class="srd-stat-icon"><i class="fas fa-inbox"></i></div>
                    <div class="srd-stat-value"><span class="count">{{\App\Order::where('ord_status',true)->where('status','received')->get()->count()}}</span></div>
                    <div class="srd-stat-label">Received</div>
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

        <div class="card-body certificate-report-table-wrapper" id="summary-table-wrapper">
            <div class="header text-center">
                <div class="center">
                     <!-- <h1>Bangladesh Shipping Corporation</h1>
                     <p class="lead">BSC Bhaban, Saltgola Road, Chittagong</p>
                     <p>Ship Repair Department</p> --> 
                </div>
            </div>

            <!-- certificate-report-table -->
            <table id="summary-table" class="certificate-report-table table table-striped table-bordered" style="width:100%">
                @if(!empty($vessels))
                @foreach($vessels as $k=>$vessel)  
                @if($k==0)
                <thead>
                    <tr class="th">
                        <th rowspan="2">Name of Vessels</th>
                        <th rowspan="2">Remark</th>
                    </tr>
                    <tr class="th">
                        <!-- <th>Name of Vessels</th> -->
                        @endif
                        @if(!empty($certificates))
                        @foreach($certificates as $certificate)
                        @if($k==0)
                        <th>{{!empty($certificate->name)?$certificate->name:''}}</th>
                        @endif
                        @endforeach
                        @endif
    
                        @if($k==0)   
                    </tr>                    
                </thead>
                <tbody>
                    @endif
    
                    <tr>
                        <td>
                            <span class="vessal-name">{{!empty($vessel->name)?$vessel->name:''}}</span> <br>
                            <span class="location">China -9/18</span>
                        </td>
    
                        @if(!empty($certificates))
                        @foreach($certificates as $k1=> $certificate)
                        
                        @if($loop->first)
                        <td>Remark</td>
                        @endif
                        
                        @php($certExpDate = !empty($certificate->vesselCertificates->whereIn('vessel_id',$vessel->id)->whereIn('certificate_id',$certificate->id)->first()->exp_date)?$certificate->vesselCertificates->whereIn('vessel_id',$vessel->id)->whereIn('certificate_id',$certificate->id)->first()->exp_date:'')
                        <td class="{{ \App\ExpiryHelper::cssClass($certExpDate) }}">{{ $certExpDate }}</td>
                        
                        @endforeach
                        @endif
                    </tr>
                    @endforeach
                    @endif

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
                                @php($surveyExpDate = !empty($s->vesselSurveys->whereIn('survey_id',$s->id)->whereIn('vessel_id',$v->id)->first()->survey_exp_date) ? $s->vesselSurveys->whereIn('survey_id',$s->id)->whereIn('vessel_id',$v->id)->first()->survey_exp_date :'')
                                <tr>
                                    <td style="border: none!important;background: inherit!important">
                                        {{!empty($s->vesselSurveys->whereIn('survey_id',$s->id)->whereIn('vessel_id',$v->id)->first()->survey_date) ? $s->vesselSurveys->whereIn('survey_id',$s->id)->whereIn('vessel_id',$v->id)->first()->survey_date :''}}
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


    })(jQuery);
</script>
@endsection
