@extends('layouts.admin-master')
@section('main-content')
<div class="container-fluid">	

	<div class="col-xs-12">
		<ul class="nav nav-tabs" id="myTab" role="tablist">
			<li class="nav-item">
				<a class="nav-link active" id="v-survey-tab" data-toggle="tab" href="#v-survey" role="tab" aria-controls="v-survey" aria-selected="false">Survey</a>
			</li>   
			<li class="nav-item">			
				<a class="nav-link" id="certificate-tab" data-toggle="tab" href="#v-certificate" role="tab" aria-controls="v-certificate" aria-selected="false">Certifciate</a>			
			</li>
			<li class="nav-item">			
				<a class="nav-link" id="vessel-tab" data-toggle="tab" href="#v-vessel" role="tab" aria-controls="v-vessel" aria-selected="false">Vessel</a>			
			</li>
			<li class="nav-item">			
				<a class="nav-link" id="categories-tab" data-toggle="tab" href="#v-categories" role="tab" aria-controls="v-categories" aria-selected="false">Categories</a>			
			</li>
			<li class="nav-item">			
				<a class="nav-link" id="items-tab" data-toggle="tab" href="#v-items" role="tab" aria-controls="v-items" aria-selected="false">Items</a>			
			</li>
			<li class="nav-item">			
				<a class="nav-link" id="requisitions-tab" data-toggle="tab" href="#v-requisitions" role="tab" aria-controls="v-requisitions" aria-selected="false">Requisitions</a>			
			</li>
		</ul>

		<div class="tab-content" id="myTabContent">
			<div class="tab-pane fade show active" id="v-survey" role="tabpanel" aria-labelledby="v-survey-tab">
				<div class="card">
					<div class="card-header">
						Survey Trash
					</div>
					<div class="card-body">							
						<table id="example" class="table table-bordered dt-responsive" style="width: 100%;">
							<thead>
								<th>#</th>
								<th>Name</th>
								<th>Society Name</th>
								<th>Survey Date</th>
								<th>Exp Date</th>
								<th>Vessel Name</th>

								<th class="action">Action</th>
							</thead>
							<tbody>
								@if(!empty($vessel_surveys))
								@foreach($vessel_surveys as $survey)
								<tr id="survey-{{$survey->id}}">
									<td class="sl_no"> <b class="serial"> {{$loop->iteration}}</b> </td>
									<td>{{!empty($survey->survey->name)?$survey->survey->name:''}}</td>
									<td>{{!empty($survey->society_name)?$survey->society_name:''}}</td>
									<td>{{!empty($survey->survey_date)?$survey->survey_date:''}}</td>
									<td>{{!empty($survey->survey_exp_date)?$survey->survey_exp_date:''}}</td>
									<td>{{!empty($survey->vessel->name)?$survey->vessel->name:''}}</td>
									<td class="action">
										<button class="btn btn-info restore" data-id="{{$survey->id}}" data-type="survey"><i class="fas fa-undo"></i></button>
										<button class="btn btn-danger permanent_delete" data-id="{{$survey->id}}"  data-type="survey"><i class="fas fa-trash"></i></button>
									</td>
								</tr>
								@endforeach
								@endif
							</tbody>
						</table>    				
					</div>
				</div>
			</div>
			<div class="tab-pane fade" id="v-certificate" role="tabpanel" aria-labelledby="v-certificate-tab">
				<div class="card">
					<div class="card-header">
						Certifciate Trash
					</div>
					<div class="card-body">
						<table id="example" class="table table-bordered dt-responsive" style="width: 100%;">
							<thead>
								<th>#</th>
								<th>Name</th>
								<th>Issueing Authority</th>
								<th>Issued On</th>
								<th>Expiry</th>
								<th>Vessel Name</th>
								<th class="tdfile">Cert. Copy</th>
								<th class="action">Action</th>
							</thead>
							<tbody>
								@if(!empty($vessel_certificates))
								@foreach($vessel_certificates as $vessel_certificate)
								<tr id="certificate-{{$vessel_certificate->id}}">
									<td class="sl_no"> <b class="serial"> {{$loop->iteration}}</b> </td>
									<td>{{!empty($vessel_certificate->certificate->name)?$vessel_certificate->certificate->name:''}}</td>
									<td>{{!empty($vessel_certificate->issue_auth)?$vessel_certificate->issue_auth:''}}</td>
									<td>{{!empty($vessel_certificate->issue_date)?$vessel_certificate->issue_date:''}}</td>
									<td>{{!empty($vessel_certificate->exp_date)?$vessel_certificate->exp_date:''}}</td>
									<td>{{!empty($vessel_certificate->vessel->name)?$vessel_certificate->vessel->name:''}}</td>
									<td class="tdfile">
										<button type="button" class="cert_file btn btn-info" data-toggle="modal" data-target="#fileShowModal" data-file="{{url('/')}}/{{!empty($vessel_certificate->cert_copy)?$vessel_certificate->cert_copy:''}}" data-name="{{!empty($vessel_certificate->name)?$vessel_certificate->name:''}}"> 
											<i class="fas fa-eye"></i> Show File
										</button>
									</td>
									<td class="action">
										<button class="btn btn-info restore" data-id="{{$vessel_certificate->id}}" data-type="certificate"><i class="fas fa-undo"></i></button>
										<button class="btn btn-danger permanent_delete" data-id="{{$vessel_certificate->id}}"  data-type="certificate"><i class="fas fa-trash"></i></button>
									</td>
								</tr>
								@endforeach
								@endif
							</tbody>
						</table>
					</div>
				</div>
			</div>

			<div class="tab-pane fade" id="v-vessel" role="tabpanel" aria-labelledby="v-vessel-tab">
				<div class="card">
					<div class="card-header">
						Vessel Trash
					</div>
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
										<button class="btn btn-info restore" data-id="{{$vessel->id}}" data-type="vessel"><i class="fas fa-undo"></i></button>
										<button class="btn btn-danger permanent_delete" data-id="{{$vessel->id}}"  data-type="vessel"><i class="fas fa-trash"></i></button>
									</td>
								</tr>
								@endforeach
								@endif
							</tbody>
						</table>
					</div>
				</div>
			</div>
			<div class="tab-pane fade" id="v-categories" role="tabpanel" aria-labelledby="v-categories-tab">
				<div class="card">
					<div class="card-header">
						Categories Trash
					</div>
					<div class="card-body">
						<table id="example" class="table table-bordered dt-responsive" style="width: 100%;">
							<thead>
								<tr>					
									<th>#</th>
									<th>Name</th>
									<th>Symbol</th>
									<th>Created By</th>
									<th>Updated By</th>
									<th class="action">Action</th>
								</tr>
							</thead>
							<tbody>
								@if(!empty($categories))
								@foreach($categories as $category)
								<tr id="category-{{$category->id}}">
									<td class="sl_no"> <b class="serial"> {{$loop->iteration}}</b> </td>
									<td>{{!empty($category->name)?$category->name:''}}</td>
									<td>{{!empty($category->symbol)?$category->symbol:''}}</td>
									<td>{{!empty($category->created_by)?$category->created_by:''}}</td>
									<td>{{!empty($category->updated_by)?$category->updated_by:''}}</td>
									<td class="action">
										<button class="btn btn-info restore" data-id="{{$category->id}}" data-type="category"><i class="fas fa-undo"></i></button>
										<button class="btn btn-danger permanent_delete" data-id="{{$category->id}}"  data-type="category"><i class="fas fa-trash"></i></button>
									</td>
								</tr>
								@endforeach
								@endif
							</tbody>
						</table>
					</div>
				</div>

			</div>
			<div class="tab-pane fade" id="v-items" role="tabpanel" aria-labelledby="v-items-tab">
				<div class="card">
					<div class="card-header">
						Items Trash
					</div>
					<div class="card-body">
						<table id="example" class="table table-bordered dt-responsive" style="width: 100%;">
							<thead>
								<th>#</th>
								<th>Imapa Code</th>
								<th>Name</th>
								<th>Unit</th>
								<th>Category</th>
								<th>Created By</th>
								<th>Updated By</th>
								<th class="action">Action</th>
							</thead>
							<tbody>
								@if(!empty($items))
								@foreach($items as $item)
								<tr id="item-{{$item->id}}">
									<td class="sl_no"> <b class="serial"> {{$loop->iteration}}</b> </td>
									<td>{{!empty($item->impa_code)?$item->impa_code:''}}</td>
									<td>{{!empty($item->name)?$item->name:''}}</td>
									<td>{{!empty($item->unit)?$item->unit:''}}</td>
									<td>{{!empty($item->category->name)?$item->category->name:''}}</td>
									<td>{{!empty($item->created_by)?$item->created_by:''}}</td>
									<td>{{!empty($item->updated_by)?$item->updated_by:''}}</td>
									<td class="action">
										<button class="btn btn-info restore" data-id="{{$item->id}}" data-type="item"><i class="fas fa-undo"></i></button>
										<button class="btn btn-danger permanent_delete" data-id="{{$item->id}}"  data-type="item"><i class="fas fa-trash"></i></button>
									</td>
								</tr>
								@endforeach
								@endif
							</tbody>
						</table>
					</div>
				</div>

			</div>
			<div class="tab-pane fade" id="v-requisitions" role="tabpanel" aria-labelledby="v-requisitions-tab">
				<div class="card">
					<div class="card-header">
						Requisitions Trash
					</div>
					<div class="card-body">
						<table id="example" class="table table-bordered dt-responsive" style="width: 100%;">
							<thead>
								<th>#</th>
								<th>Req. No</th>
								<th>Category</th>
								<th>Vessel Name</th>
								<th>Req. Date</th>
								<th>Port</th>
								<th>status</th>
								<th>status from ssm</th>
								<th>Created By</th>
								<th>Updated By</th>
								<!-- <th class="action">Action</th> -->
							</thead>
							<tbody>
								@if(!empty($orders))
								@foreach($orders as $order)
								<tr id="order-{{$order->id}}">
									<td class="sl_no"> <b class="serial"> {{$loop->iteration}}</b> </td>
									<td>
										<a href="{{url('/order/detail/'.$order->id)}}" class="req_no_link">
											{{!empty($order->req_no)?$order->req_no:''}}
										</a>
									</td>
									<td>{{!empty($order->category->name)?$order->category->name:''}}</td>
									<td>{{!empty($order->vessel->name)?$order->vessel->name:''}}</td>
									<td>{{!empty($order->req_date)?$order->req_date:''}}</td>
									<td>{{!empty($order->port_name)?$order->port_name:''}}</td>
									<td>{{!empty($order->status)?$order->status:''}}</td>
									<td>{{!empty($order->status_from_am)?$order->status_from_am:''}}</td>
									<td>{{!empty($order->created_by)?$order->created_by:''}}</td>
									<td>{{!empty($order->updated_by)?$order->updated_by:''}}</td>
									<td class="action">
										<button class="btn btn-info restore" data-id="{{$order->id}}" data-type="order"><i class="fas fa-undo"></i></button>
										<button class="btn btn-danger permanent_delete" data-id="{{$order->id}}"  data-type="order"><i class="fas fa-trash"></i></button>
									</td> 
								</tr>
								@endforeach
								@endif
							</tbody>
						</table>
					</div>
				</div>

			</div>


		</div>
	</div>

</div>
@endsection


