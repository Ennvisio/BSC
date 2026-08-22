@extends('layouts.admin-master')
@section('main-content')



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
                        
                        <td>{{!empty($certificate->vesselCertificates->whereIn('vessel_id',$vessel->id)->whereIn('certificate_id',$certificate->id)->first()->exp_date)?$certificate->vesselCertificates->whereIn('vessel_id',$vessel->id)->whereIn('certificate_id',$certificate->id)->first()->exp_date:''}}</td>
                        
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
                                <tr>
                                    <td style="border: none!important;background: inherit!important">
                                        {{!empty($s->vesselSurveys->whereIn('survey_id',$s->id)->whereIn('vessel_id',$v->id)->first()->survey_date) ? $s->vesselSurveys->whereIn('survey_id',$s->id)->whereIn('vessel_id',$v->id)->first()->survey_date :''}}
                                    </td>
                                   <td style="border: none!important">
                                        {{!empty($s->vesselSurveys->whereIn('survey_id',$s->id)->whereIn('vessel_id',$v->id)->first()->survey_exp_date) ? $s->vesselSurveys->whereIn('survey_id',$s->id)->whereIn('vessel_id',$v->id)->first()->survey_exp_date :''}}
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

<div class="col-lg-3 col-md-6">
    <div class="card counter">
        <div class="card-body">
            <a href="{{url('/home/certificate')}}">
                <div class="stat-widget-five">
                    <div class="stat-icon dib flat-color-1">
                        <img style="width: 58px;height: auto;" src="{{url('assets/icons/certificate.png')}}">
                    </div>
                    <div class="stat-content">
                        <div class="text-left dib">
                            <div class="stat-text"><span class="count positive">{{\App\Certificate::where('status',true)->get()->count()}}</span></div>
                            <div class="stat-heading">Certificate</div>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    </div>
</div>
<div class="col-lg-3 col-md-6">
    <div class="card counter">
        <div class="card-body">
            <a href="{{url('/home/survey')}}">
                <div class="stat-widget-five">
                    <div class="stat-icon dib flat-color-2">
                        <img style="width: 62px;height: auto;" src="{{url('assets/icons/survey.png')}}">
                    </div>
                    <div class="stat-content">
                        <div class="text-left dib">
                            <div class="stat-text"><span class="count negative">{{\App\Survey::where('status',true)->get()->count()}}</span></div>
                            <div class="stat-heading">Survey</div>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    </div>
</div>
<div class="col-lg-3 col-md-6">
    <div class="card counter">
        <div class="card-body">
            <a href="{{url('/home/vessel')}}">
                <div class="stat-widget-five">
                    <div class="stat-icon dib flat-color-3">
                        <img style="width: 58px;height: auto;" src="{{url('assets/icons/vessels.png')}}">
                    </div>
                    <div class="stat-content">
                        <div class="text-left dib">
                            <div class="stat-text">
                                <span class="count pending">{{\App\Vessel::where('status',true)->get()->count()}}
                                </span>
                            </div>
                            <div class="stat-heading">Vessels</div>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    </div>
</div>

<div class="col-lg-3 col-md-6">
    <div class="card counter">
        <div class="card-body">
            <a href="{{url('/home/item')}}">
                <div class="stat-widget-five">
                    <div class="stat-icon dib flat-color-4" style="color: #d6bd0c">
                        <img style="width: 62px;height: auto;" src="{{url('assets/icons/category.png')}}">
                    </div>
                    <div class="stat-content">
                        <div class="text-left dib">
                            <div class="stat-text">    
                                <span class="count pending">{{\App\Category::where('status',true)->get()->count()}}
                                </span>
                            </div>
                            <div class="stat-heading">Category</div>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    </div>
</div>

<div class="col-lg-3 col-md-6">
    <div class="card counter">
        <div class="card-body">
            <a href="{{url('/home/item')}}">
                <div class="stat-widget-five">
                    <div class="stat-icon dib flat-color-4" style="color: #d6bd0c">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <div class="stat-content">
                        <div class="text-left dib">
                            <div class="stat-text">    
                                <span class="count pending">{{\App\Item::where('status',true)->get()->count()}}
                                </span>
                            </div>
                            <div class="stat-heading">Items</div>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    </div>
</div>

<div class="col-lg-3 col-md-6">
    <div class="card counter">
        <div class="card-body">
            <a href="{{url('/home/order')}}">
                <div class="stat-widget-five">
                    <div class="stat-icon dib flat-color-4" style="color: #66bb6a">
                       <img style="width: 58px;height: auto;" src="{{url('assets/icons/requisition.png')}}">
                    </div>
                    <div class="stat-content">
                        <div class="text-left dib">
                            <div class="stat-text">                                   
                                <span class="count pending">{{\App\Order::where('ord_status',true)->where('status','delivered')->get()->count()}}
                                </span>
                            </div>
                            <div class="stat-heading">Delivered </div>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    </div>
</div>

<div class="col-lg-3 col-md-6">
    <div class="card counter">
        <div class="card-body">
            <a href="{{url('/home/order')}}">
                <div class="stat-widget-five">
                    <div class="stat-icon dib flat-color-4" style="color: #66bb6a">
                       <img style="width: 58px;height: auto;" src="{{url('assets/icons/requisition.png')}}">
                    </div>
                    <div class="stat-content">
                        <div class="text-left dib">
                            <div class="stat-text"><span class="count">
                                {{\App\Order::where('ord_status',true)->where('status','received')->get()->count()}}
                            </span></div>
                            <div class="stat-heading">Received </div>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    </div>
</div>

<div class="col-lg-3 col-md-6">
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
