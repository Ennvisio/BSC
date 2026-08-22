@extends('layouts.admin-master')
@section('main-content')
<div class="col-lg-6 col-xl-12">
  <div class="card">
    <div class="card-header pv-card-hader">
      <strong class="pptitle">Vessel Lists</strong>

      <div class="right-buttons">  

        <a  class="btn btn-primary" href="{{url('/vessel/add')}}"> <i class="fas fa-plus-square"></i> Add New Vessel 
        </a>

        <button class="btn btn-info btn-bvprint" onClick="print_this();"><i class="fa fa-print"></i>  Print</button>
      </div>
    </div>
    <!-- ./pv-card-hader -->
    <!-- card-body -->
    <div class="card-body">
      <table id="example" class="table table-bordered dt-responsive" style="width: 100%;">
        <thead>
          <th>#</th>
          <th>Name</th>
          <th>Owner</th>
          <th>Manager</th>
          <th>Master</th>
          <th>Engineer</th>
          <th>Prev. Record</th>
          <th class="action">Action</th>
        </thead>
        <tbody>
          @if(!empty($vessels))
          @foreach($vessels as $vessel)
          <tr id="vessel-{{$vessel->id}}">
            <td class="sl_no"><b class="serial"> {{$loop->iteration}}</b> </td>
            <td>{{!empty($vessel->name)?$vessel->name:''}}</td>
            <td>
              {{!empty($vessel->owner_name)?$vessel->owner_name:''}} <br>
              {{!empty($vessel->owner_address)?$vessel->owner_address:''}}
            </td>
            <td>
              {{!empty($vessel->manager_name)?$vessel->manager_name:''}} <br>
              {{!empty($vessel->manager_address)?$vessel->manager_address:''}}
            </td>
            <td>
              {{!empty($vessel->master_name)?$vessel->master_name:''}} <br>
              {{!empty($vessel->master_cert_no)?$vessel->master_cert_no:''}} <br>
              {{!empty($vessel->master_cert_validity)?$vessel->master_cert_validity:''}}
            </td>
            <td>
              {{!empty($vessel->ch_eng_name)?$vessel->ch_eng_name:''}} <br>
              {{!empty($vessel->ch_eng_cert_no)?$vessel->ch_eng_cert_no:''}} <br>
              {{!empty($vessel->ch_eng_cert_validity)?$vessel->ch_eng_cert_validity:''}}
            </td>
            <td>
              {{!empty($vessel->prev_port_no)?$vessel->prev_port_no:''}} <br>
              {{!empty($vessel->prev_reg_date)?$vessel->prev_reg_date:''}}
            </td>
            <td class="action">

              <a title="view this vessel" class="btn btn-info view-vessel" data-toggle="tooltip" data-placement="top" href="{{url('/vessel-view/'.$vessel->id)}}" data-toggle="tooltip" data-placement="top"> <i class="fas fa-eye"></i></a>
              <a  title="edit this vessel" class="btn btn-info edit-vessel" data-toggle="tooltip" data-placement="top" href="{{url('/vessel-edit/'.$vessel->id)}}"> <i class="fas fa-edit"></i> </a>

          <!--     @if(empty($vessel->vesselFrameworkAndDetail) && empty($vessel->vesselDimension) && empty($vessel->vesselEngine) && empty($vessel->vesselBoiler) && empty($vessel->vesselDetail))
              
            @endif -->
              <!-- 
                <a title="add detail of this vessel" class="btn btn-primary add-vessel-detail" data-toggle="tooltip" data-placement="top" href="{{url('/vessel-detail-add/'.$vessel->id)}}" > <i class="fas fa-plus"></i> </a>  -->
                <button  title="delete this vessel" class="btn btn-danger delete-vessel" data-id="{{$vessel->id}}" data-toggle="tooltip" data-placement="top">
                  <i class="fas fa-trash-alt"></i>
                </button>
              </td>
            </tr>
            @endforeach
            @endif
          </tbody>
        </table>


        
      </div>
    </div>
  </div>


  <!-- logo-base64 for pdf page -->
  @include('pdf.logo-base64')
  <!-- logo-base64 for pdf page -->
  @endsection

