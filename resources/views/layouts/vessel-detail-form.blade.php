    <div class="vessel-form">
    	<!-- nav-tabs -->
    	<ul class="nav nav-tabs" id="myTab" role="tablist">
    		<li class="nav-item">
    			<a class="nav-link active" id="v-particulars-tab" data-toggle="tab" href="#v-particulars" role="tab" aria-controls="v-particulars" aria-selected="false">Particulars</a>
    		</li>

    		<li class="nav-item">
				@if(empty($vessel->vesselDetail))
					<a style="pointer-events: none;cursor: default;opacity: 0.6;" class="nav-link" id="framework-description-tab" data-toggle="tab" href="#framework-description" role="tab" aria-controls="framework-description" aria-selected="false">Framework and description</a>
				@else
					<a class="nav-link" id="framework-description-tab" data-toggle="tab" href="#framework-description" role="tab" aria-controls="framework-description" aria-selected="false">Framework and description</a>
				@endif

    		</li>

    		<li class="nav-item">
				@if(empty($vessel->vesselFrameworkAndDetail))
					<a style="pointer-events: none;cursor: default;opacity: 0.6;" class="nav-link" id="dimension-tab" data-toggle="tab" href="#dimension" role="tab" aria-controls="dimension" aria-selected="false">Dimension</a>
				@else
					<a class="nav-link" id="dimension-tab" data-toggle="tab" href="#dimension" role="tab" aria-controls="dimension" aria-selected="false">Dimension</a>
				@endif
    		</li>

    		<li class="nav-item">
				@if(empty($vessel->vesselDimension))
					<a style="pointer-events: none;cursor: default;opacity: 0.6;" class="nav-link" id="main-engines-tab" data-toggle="tab" href="#main-engines" role="tab" aria-controls="main-engines" aria-selected="false">Engines</a>
				@else
					<a class="nav-link" id="main-engines-tab" data-toggle="tab" href="#main-engines" role="tab" aria-controls="main-engines" aria-selected="false">Engines</a>
				@endif
    		</li>

    		<li class="nav-item">
				@if(empty($vessel->vesselEngine))
					<a style="pointer-events: none;cursor: default;opacity: 0.6;" class="nav-link" id="boilers-tab" data-toggle="tab" href="#boilers" role="tab" aria-controls="boilers" aria-selected="false">Boilers</a>
				@else
					<a class="nav-link" id="boilers-tab" data-toggle="tab" href="#boilers" role="tab" aria-controls="boilers" aria-selected="false">Boilers</a>
				@endif
    		</li>
    	</ul>
    	<!-- ./nav-tabs -->

    	<!-- tab-content -->
    	<div class="tab-content" id="myTabContent">
    		<!-- tab-pane -->                            
    		<div class="tab-pane fade show active" id="v-particulars" role="tabpanel" aria-labelledby="v-particulars-tab">  
    			<div class="bsc-form-wrapper vessel-particulars-wrapper">
    				<div class="form-header">Particulars of Vessel</div>
    				<form id="v-particulars-form">
						@csrf
						<div class="row justify-content-center form-group mt-3">
							<div class="col-md-11 alert alert-danger alert-dismissible fade show form_error" style="display:none" role="alert">
								<strong>Error Submission!!</strong> Please correct following info and resubmit.
								<label>    </label>
								<button type="button" class="close close_error_alert">
									<span aria-hidden="true">&times;</span>
								</button>
							</div>
							<input type="hidden" name="vessel_id" value="{{$id}}">
						</div>

    					<div class="form-group row">
    						<label for="type" class="col-sm-3 col-form-label">Type of Vessel :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" name="type" placeholder="" value="{{ !empty($vessel->vesselDetail->type) ? $vessel->vesselDetail->type:''}}">
    						</div>
    					</div>

    					<div class="form-group row">
    						<label for="flag" class="col-sm-3 col-form-label">Existing Flag :</label>
    						<div class="col-sm-9">
    							<input class="form-control" name="flag" placeholder="" value="{{ !empty($vessel->vesselDetail->flag) ? $vessel->vesselDetail->flag:''}}">
    						</div>
    					</div>

    					<div class="form-group row">
    						<label for="call_sign" class="col-sm-3 col-form-label">Call Sign :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" name="call_sign" placeholder="" value="{{ !empty($vessel->vesselDetail->call_sign) ? $vessel->vesselDetail->call_sign:''}}">
    						</div>
    					</div>

    					<div class="form-group row">
    						<label for="imo_no" class="col-sm-3 col-form-label">IMO Number :</label>
    						<div class="col-sm-9">
    							<input class="form-control" name="imo_no" placeholder="" value="{{ !empty($vessel->vesselDetail->imo_no) ? $vessel->vesselDetail->imo_no:''}}">
    						</div>
    					</div>

    					<hr>

    					<div class="form-group row">
    						<label for="grt" class="col-sm-3 col-form-label">GRT :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" name="grt" placeholder="" value="{{ !empty($vessel->vesselDetail->grt) ? $vessel->vesselDetail->grt:''}}">
    						</div>
    					</div>

    					<div class="form-group row">
    						<label for="nrt" class="col-sm-3 col-form-label">NRT :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" name="nrt" placeholder="" value="{{ !empty($vessel->vesselDetail->nrt) ? $vessel->vesselDetail->nrt:''}}">
    						</div>
    					</div>

    					<div class="form-group row">
    						<label for="dwt" class="col-sm-3 col-form-label">DWT :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" name="dwt" placeholder="" value="{{ !empty($vessel->vesselDetail->dwt) ? $vessel->vesselDetail->dwt:''}}">
    						</div>
    					</div>

    					<div class="form-group row">
    						<label for="off_no" class="col-sm-3 col-form-label">Offical Number :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" name="off_no" placeholder="" value="{{ !empty($vessel->vesselDetail->off_no) ? $vessel->vesselDetail->off_no:''}}">
    						</div>
    					</div>

    					<div class="form-group row">
    						<label for="keel_lay_date" class="col-sm-3 col-form-label">Keel Lay Date :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control date" name="keel_lay_date" placeholder="" value="{{ !empty($vessel->vesselDetail->keel_lay_date) ? $vessel->vesselDetail->keel_lay_date:''}}">
    						</div>
    					</div>

    					<hr>

    					<div class="form-group row">
    						<label for="launch_date" class="col-sm-3 col-form-label">Launching Date :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control date" name="launch_date" placeholder="" value="{{ !empty($vessel->vesselDetail->launch_date) ? $vessel->vesselDetail->launch_date:''}}">
    						</div>
    					</div>

    					<div class="form-group row">
    						<label for="delivery_date" class="col-sm-3 col-form-label">Delivery / building Date :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control date" name="delivery_date" placeholder="" value="{{ !empty($vessel->vesselDetail->delivery_date) ? $vessel->vesselDetail->delivery_date:''}}">
    						</div>
    					</div>

    					<div class="form-group row">
    						<label for="cert_date" class="col-sm-3 col-form-label">Tonnage Calculation/ Certification Date :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control date" name="cert_date" placeholder="" value="{{ !empty($vessel->vesselDetail->cert_date) ? $vessel->vesselDetail->cert_date:''}}">
    						</div>
    					</div>

    					<div class="form-group row">
    						<label for="built_year" class="col-sm-3 col-form-label">Year of Built :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" name="built_year" placeholder="" value="{{ !empty($vessel->vesselDetail->built_year) ? $vessel->vesselDetail->built_year:''}}">
    						</div>
    					</div>

    					<div class="form-group row">
    						<label for="built_loc" class="col-sm-3 col-form-label">Where Built :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" name="built_loc" placeholder="" value="{{ !empty($vessel->vesselDetail->built_loc) ? $vessel->vesselDetail->built_loc:''}}">
    						</div>
    					</div>

    					<hr>

    					<div class="form-group row">
    						<label for="steam_motor_propelled" class="col-sm-3 col-form-label">Whether Steam or Motor Ship and how propelled :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" name="steam_motor_propelled" placeholder="" value="{{ !empty($vessel->vesselDetail->type) ? $vessel->vesselDetail->type:''}}">
    						</div>
    					</div>

    					<div class="form-group row">
    						<label for="builder_name" class="col-sm-3 col-form-label">Name of Builder :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" name="builder_name" placeholder="" value="{{ !empty($vessel->vesselDetail->builder_name) ? $vessel->vesselDetail->builder_name:''}}">
    						</div>
    					</div>

    					<div class="form-group row">
    						<label for="builder_address" class="col-sm-3 col-form-label">Address of Builder :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" name="builder_address" placeholder="" value="{{ !empty($vessel->vesselDetail->builder_address) ? $vessel->vesselDetail->builder_address:''}}">
    						</div>
    					</div>

    					<div class="form-group row">
    						<label for="deck_no" class="col-sm-3 col-form-label">Number of Deck :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" name="deck_no" placeholder="" value="{{ !empty($vessel->vesselDetail->deck_no) ? $vessel->vesselDetail->deck_no:''}}">
    						</div>
    					</div>

    					<div class="form-group row">
    						<label for="mast_no" class="col-sm-3 col-form-label">Number of masts :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" name="mast_no" placeholder="" value="{{ !empty($vessel->vesselDetail->mast_no) ? $vessel->vesselDetail->mast_no:''}}">
    						</div>
    					</div>

    					<hr>

    					<div class="form-group row">
    						<label for="rigged" class="col-sm-3 col-form-label">Rigged :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" name="rigged" placeholder="" value="{{ !empty($vessel->vesselDetail->rigged) ? $vessel->vesselDetail->rigged:''}}">
    						</div>
    					</div>

    					<div class="form-group row">
    						<label for="stem" class="col-sm-3 col-form-label">Stem :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" name="stem" placeholder="" value="{{ !empty($vessel->vesselDetail->stem) ? $vessel->vesselDetail->stem:''}}">
    						</div>
    					</div>

    					<div class="form-group row">
    						<label for="stern" class="col-sm-3 col-form-label">Stern :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" name="stern" placeholder="" value="{{ !empty($vessel->vesselDetail->stern) ? $vessel->vesselDetail->stern:''}}">
    						</div>
    					</div>

    					<div class="form-group row">
    						<label for="build" class="col-sm-3 col-form-label">Build :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" name="build" placeholder="" value="{{ !empty($vessel->vesselDetail->build) ? $vessel->vesselDetail->build:''}}">
    						</div>
    					</div>

    					<div class="form-group row mt-4">
    						<div class="col-sm-12 text-center">
    							<button type="button" class="btn btn-success btn-gninfo back">Back</button>
								@if(!empty($vessel->vesselDetail->type))
									<button type="submit" class="btn btn-success btn-gninfo">Update</button>
								@else
									<button type="submit" class="btn btn-success btn-gninfo">Save & Continue</button>
								@endif

    						</div>                                        
    					</div>
    				</form>
    			</div> 
    		</div>
    		<!-- ./tab-pane -->

    		<!-- tab-pane -->
    		<div class="tab-pane fade" id="framework-description" role="tabpanel" aria-labelledby="framework-description-tab"> 
    			<div class="bsc-form-wrapper framework-description-wrapper">
    				<div class="form-header">Stern framework and description of vessel</div>
    				<form id="v-freamework-and-description-form">
						@csrf
						<div class="row justify-content-center form-group mt-3">
							<div class="col-md-11 alert alert-danger alert-dismissible fade show form_error" style="display:none" role="alert">
								<strong>Error Submission!!</strong> Please correct following info and resubmit.
								<label>    </label>
								<button type="button" class="close close_error_alert">
									<span aria-hidden="true">&times;</span>
								</button>
							</div>
							<input type="hidden" name="vessel_id" value="{{$id}}">
						</div>
    					<div class="form-group row mt-4">
    						<label for="bulk_no" class="col-sm-3 col-form-label">Number of Bulkhead :</label>
    						<div class="col-sm-9">
                                <!-- {{--<input type="text" class="form-control" name="type" placeholder="" value="{{ !empty($vessel->vesselDetail->type) ? $vessel->vesselDetail->type:''}}">--}} -->
    							<input type="text" class="form-control" id="bulk_no" placeholder="" name="bulk_no" value="{{ !empty($vessel->vesselFrameworkAndDetail->bulk_no) ? $vessel->vesselFrameworkAndDetail->bulk_no : '' }}">
    						</div>
    					</div>

    					<div class="form-group row">
    						<label for="length_stem_rudder" class="col-sm-3 col-form-label">Length from fore part of stem, to the aft side of the head of th stem post / fore side of the rudder stock :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" id="length_stem_rudder" placeholder="" name="length_stem_rudder" value="{{ !empty($vessel->vesselFrameworkAndDetail->length_stem_rudder) ? $vessel->vesselFrameworkAndDetail->length_stem_rudder : '' }}">
    						</div>
    					</div>

    					<div class="form-group row">
    						<label for="main_breadth" class="col-sm-3 col-form-label">Main breadth to outside of plating :</label>
    						<div class="col-sm-9">
    							<input class="form-control" id="main_breadth" placeholder="" name="main_breadth" value="{{ !empty($vessel->vesselFrameworkAndDetail->main_breadth) ? $vessel->vesselFrameworkAndDetail->main_breadth : '' }}">
    						</div>
    					</div>

    					<div class="form-group row">
    						<label for="depth_tonnag_ceil" class="col-sm-3 col-form-label">Depth in hold from tonnage deck to ceiling a amidships :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" id="depth_tonnag_ceil" placeholder="" name="dept_tonnag_ceil" value="{{ !empty($vessel->vesselFrameworkAndDetail->dept_tonnag_ceil) ? $vessel->vesselFrameworkAndDetail->dept_tonnag_ceil : '' }}">
    						</div>
    					</div>

    					{{--<div class="form-group row">--}}
    						{{--<label for="length_eng_room" class="col-sm-3 col-form-label">Length of Engine Room :</label>--}}
    						{{--<div class="col-sm-9">--}}
    							{{--<input class="form-control" id="length_eng_room" placeholder="" name="length_eng_room" value="{{ !empty($vessel->vesselFrameworkAndDetail->length_eng_room) ? $vessel->vesselFrameworkAndDetail->length_eng_room : '' }}">--}}
    						{{--</div>--}}
    					{{--</div>--}}

    					<hr>

    					<div class="form-group row">
    						<label for="eng_set_no" class="col-sm-3 col-form-label">Number of sets of Engines :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" id="eng_set_no" placeholder="" name="eng_set_no" value="{{ !empty($vessel->vesselFrameworkAndDetail->eng_set_no) ? $vessel->vesselFrameworkAndDetail->eng_set_no : '' }}">
    						</div>
    					</div>

    					<div class="form-group row">
    						<label for="shaft_no" class="col-sm-3 col-form-label">Number of Shaft :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" id="shaft_no" placeholder="" name="shaft_no" value="{{ !empty($vessel->vesselFrameworkAndDetail->shaft_no) ? $vessel->vesselFrameworkAndDetail->shaft_no : '' }}">
    						</div>
    					</div>

    					{{--<div class="form-group row">--}}
    						{{--<label for="no_cyl_set" class="col-sm-3 col-form-label">Number of Cylenders in each Sets :</label>--}}
    						{{--<div class="col-sm-9">--}}
    							{{--<input type="text" class="form-control" id="no_cyl_set" placeholder="" name="no_cyl_set" value="{{ !empty($vessel->vesselFrameworkAndDetail->no_cyl_set) ? $vessel->vesselFrameworkAndDetail->no_cyl_set : '' }}">--}}
    						{{--</div>--}}
    					{{--</div>--}}

    					{{--<div class="form-group row">--}}
    						{{--<label for="lengh_stroke" class="col-sm-3 col-form-label">Length of stroke :</label>--}}
    						{{--<div class="col-sm-9">--}}
    							{{--<input type="text" class="form-control" id="lengh_stroke" placeholder="" name="length_stroke" value="{{ !empty($vessel->vesselFrameworkAndDetail->length_stroke) ? $vessel->vesselFrameworkAndDetail->length_stroke : '' }}">--}}
    						{{--</div>--}}
    					{{--</div>--}}

    					{{--<div class="form-group row">--}}
    						{{--<label for="diam_cyl" class="col-sm-3 col-form-label">Diameter of cylinders :</label>--}}
    						{{--<div class="col-sm-9">--}}
    							{{--<input type="text" class="form-control" id="diam_cyl" placeholder="" name="diam_cyl" value="{{ !empty($vessel->vesselFrameworkAndDetail->diam_cyl) ? $vessel->vesselFrameworkAndDetail->diam_cyl : '' }}">--}}
    						{{--</div>--}}
    					{{--</div>--}}

    					<hr>

    					{{--<div class="form-group row">--}}
    						{{--<label for="speed" class="col-sm-3 col-form-label">Speed of ship :</label>--}}
    						{{--<div class="col-sm-9">--}}
    							{{--<input type="text" class="form-control" id="speed" placeholder="" name="spreed" value="{{ !empty($vessel->vesselFrameworkAndDetail->spreed) ? $vessel->vesselFrameworkAndDetail->spreed : '' }}">--}}
    						{{--</div>--}}
    					{{--</div>--}}

    					<div class="form-group row">
    						<label for="loaded_pressure" class="col-sm-3 col-form-label">Evaporations Loaded Pressure :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" id="loaded_pressure" placeholder="" name="loaded_pressure" value="{{ !empty($vessel->vesselFrameworkAndDetail->loaded_pressure) ? $vessel->vesselFrameworkAndDetail->loaded_pressure : '' }}">
    						</div>
    					</div>

    					<div class="form-group row">
    						<label for="gro_ton" class="col-sm-3 col-form-label">Gross Tonnage :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" id="gro_ton" placeholder="" name="gro_ton" value="{{ !empty($vessel->vesselFrameworkAndDetail->gro_ton) ? $vessel->vesselFrameworkAndDetail->gro_ton : '' }}">
    						</div>
    					</div>

    					<div class="form-group row">
    						<label for="net_ton" class="col-sm-3 col-form-label">Net Tonnage :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" id="net_ton" placeholder="" name="net_ton" value="{{ !empty($vessel->vesselFrameworkAndDetail->net_ton) ? $vessel->vesselFrameworkAndDetail->net_ton : '' }}">
    						</div>
    					</div>

    					<div class="form-group row">
    						<label for="cert_accom" class="col-sm-3 col-form-label">Certified accommodations :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" id="cert_accom" placeholder="" name="cert_accom" value="{{ !empty($vessel->vesselFrameworkAndDetail->cert_accom) ? $vessel->vesselFrameworkAndDetail->cert_accom : '' }}">
    						</div>
    					</div>

    					<hr>

    					<div class="form-group row">
    						<label for="lifeboat_num" class="col-sm-3 col-form-label">Number of Lifeboats :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" id="lifeboat_num" placeholder="" name="lifeboat_num" value="{{ !empty($vessel->vesselFrameworkAndDetail->lifeboat_num) ? $vessel->vesselFrameworkAndDetail->lifeboat_num : '' }}">
    						</div>
    					</div>

    					<div class="form-group row">
    						<label for="rafts_num" class="col-sm-3 col-form-label">Number of Life rafts :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" id="rafts_num" placeholder="" name="rafts_num" value="{{ !empty($vessel->vesselFrameworkAndDetail->rafts_num) ? $vessel->vesselFrameworkAndDetail->rafts_num : '' }}">
    						</div>
    					</div>

    					<div class="form-group row">
    						<label for="per_accom_num" class="col-sm-3 col-form-label">Number of persons accomodated by them :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" id="per_accom_num" placeholder="" name="per_accom_num" value="{{ !empty($vessel->vesselFrameworkAndDetail->per_accom_num) ? $vessel->vesselFrameworkAndDetail->per_accom_num : '' }}">
    						</div>
    					</div>

    					<div class="form-group row">
    						<label for="raft_req_num" class="col-sm-3 col-form-label">Number of life rafts raq. by ragulation 111/31.1.4 :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" id="raft_req_num" placeholder="" name="rafts_req_num" value="{{ !empty($vessel->vesselFrameworkAndDetail->rafts_req_num) ? $vessel->vesselFrameworkAndDetail->rafts_req_num : '' }}">
    						</div>
    					</div>

    					<div class="form-group row">
    						<label for="buoys_num" class="col-sm-3 col-form-label">Number of Life Buoys :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" id="buoys_num" placeholder="" name="buoys_num" value="{{ !empty($vessel->vesselFrameworkAndDetail->buoys_num) ? $vessel->vesselFrameworkAndDetail->buoys_num : '' }}">
    						</div>
    					</div>

    					<hr>

    					<div class="form-group row">
    						<label for="jack_num" class="col-sm-3 col-form-label">Number of Life Jackets :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" id="jack_num" placeholder="" name="jack_num" value="{{ !empty($vessel->vesselFrameworkAndDetail->jack_num) ? $vessel->vesselFrameworkAndDetail->jack_num : '' }}">
    						</div>
    					</div>

    					<div class="form-group row">
    						<label for="imm_suit_num" class="col-sm-3 col-form-label">Total Number of immersion Suits :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" id="imm_suit_num" placeholder="" name="imm_suit_num" value="{{ !empty($vessel->vesselFrameworkAndDetail->imm_suit_num) ? $vessel->vesselFrameworkAndDetail->imm_suit_num : '' }}">
    						</div>
    					</div>

    					<div class="form-group row">
    						<label for="therm_pro_num" class="col-sm-3 col-form-label">Number of thermal Protective aids :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" id="therm_pro_num" placeholder="" name="therm_pro_num" value="{{ !empty($vessel->vesselFrameworkAndDetail->therm_pro_num) ? $vessel->vesselFrameworkAndDetail->therm_pro_num : '' }}">
    						</div>
    					</div>

    					<div class="form-group row">
    						<label for="trans_rud_num" class="col-sm-3 col-form-label">Number of rudder transponder :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" id="trans_rud_num" placeholder="" name="trans_rud_num" value="{{ !empty($vessel->vesselFrameworkAndDetail->trans_rud_num) ? $vessel->vesselFrameworkAndDetail->trans_rud_num : '' }}">
    						</div>
    					</div>

    					<div class="form-group row">
    						<label for="propeller" class="col-sm-3 col-form-label">Propeller :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" id="propeller" placeholder="" name="propeller" value="{{ !empty($vessel->vesselFrameworkAndDetail->propeller) ? $vessel->vesselFrameworkAndDetail->propeller : '' }}">
    						</div>
    					</div>

    					<hr>

    					<div class="form-group row mt-4">
    						<div class="col-sm-12 text-center">
    							<button type="button" class="btn btn-success btn-gninfo back">Back</button>
								@if(!empty($vessel->vesselFrameworkAndDetail->bulk_no))
									<button type="submit" class="btn btn-success btn-gninfo">Update</button>
								@else
									<button type="submit" class="btn btn-success btn-gninfo">Save & Continue</button>
								@endif
    						</div>                                        
    					</div>
    				</form>
    			</div> 
    		</div>
    		<!-- ./tab-pane -->

    		<!-- tab-pane -->
    		<div class="tab-pane fade" id="dimension" role="tabpanel" aria-labelledby="dimension-tab"> 
    			<div class="bsc-form-wrapper dimension-wrapper">
    				<div class="form-header">Dimension</div>
    				<form id="v-dimension-form">
						@csrf
						<div class="row justify-content-center form-group mt-3">
							<div class="col-md-11 alert alert-danger alert-dismissible fade show form_error" style="display:none" role="alert">
								<strong>Error Submission!!</strong> Please correct following info and resubmit.
								<label>    </label>
								<button type="button" class="close close_error_alert">
									<span aria-hidden="true">&times;</span>
								</button>
							</div>
							<input type="hidden" name="vessel_id" value="{{$id}}">
						</div>

    					<div class="form-group row mt-4">
    						<label for="length_ll" class="col-sm-3 col-form-label">Length (LL Reg III/3.12) :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" id="length_LL" placeholder="" name="length_LL" value="{{ !empty($vessel->vesselDimension->length_LL) ? $vessel->vesselDimension->length_LL : '' }}">
							</div>
    					</div>

    					<div class="form-group row">
    						<label for="length_oa" class="col-sm-3 col-form-label">Length O.A & B.P :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" id="length_OA" name="length_OA" value="{{ !empty($vessel->vesselDimension->length_OA) ? $vessel->vesselDimension->length_OA : '' }}" placeholder="">
    						</div>
    					</div>

    					<div class="form-group row">
    						<label for="breath" class="col-sm-3 col-form-label">Breadth MLD :</label>
    						<div class="col-sm-9">
    							<input class="form-control" id="breadth" name="breadth" value="{{ !empty($vessel->vesselDimension->breadth) ? $vessel->vesselDimension->breadth : '' }}" placeholder="">
    						</div>
    					</div>

    					<div class="form-group row">
    						<label for="depth" class="col-sm-3 col-form-label">Depth MLD :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" id="depth" name="depth" value="{{ !empty($vessel->vesselDimension->depth) ? $vessel->vesselDimension->depth : '' }}" placeholder="">
    						</div>
    					</div>

    					<div class="form-group row">
    						<label for="length_eng_room" class="col-sm-3 col-form-label">Length of Engine Room :</label>
    						<div class="col-sm-9">
    							<input class="form-control" id="length_eng_room" name="length_eng_room" value="{{ !empty($vessel->vesselDimension->length_eng_room) ? $vessel->vesselDimension->length_eng_room : '' }}" placeholder="">
    						</div>
    					</div>

    					<hr>

    					<div class="form-group row">
    						<label for="draft" class="col-sm-3 col-form-label">Draft summer :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" id="draft" name="draft" value="{{ !empty($vessel->vesselDimension->draft) ? $vessel->vesselDimension->draft : '' }}" placeholder="">
    						</div>
    					</div>

    					<div class="form-group row">
    						<label for="suez_gro_ton" class="col-sm-3 col-form-label">Suez Gross Tonnage :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" id="suez_gro_ton" name="suez_geo_ton" value="{{ !empty($vessel->vesselDimension->suez_geo_ton) ? $vessel->vesselDimension->suez_geo_ton : '' }}" placeholder="">
    						</div>
    					</div>

    					<div class="form-group row">
    						<label for="suez_net_ton" class="col-sm-3 col-form-label">Suez Net Tonnage :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" id="suez_net_ton" name="suez_net_ton" value="{{ !empty($vessel->vesselDimension->suez_net_ton) ? $vessel->vesselDimension->suez_net_ton : '' }}" placeholder="">
    						</div>
    					</div>

    					<div class="form-group row">
    						<label for="pana_ton" class="col-sm-3 col-form-label">Panama Net Tonnage :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" id="pana_ton" name="pana_ton" value="{{ !empty($vessel->vesselDimension->pana_ton) ? $vessel->vesselDimension->pana_ton : '' }}" placeholder="">
    						</div>
    					</div>

    					{{--<div class="form-group row">--}}
    						{{--<label for="diam_cyl" class="col-sm-3 col-form-label">Diameter of cylinders :</label>--}}
    						{{--<div class="col-sm-9">--}}
    							{{--<input type="text" class="form-control" id="diam_cyl" placeholder="">--}}
    						{{--</div>--}}
    					{{--</div>--}}

    					<hr>

    					<div class="form-group row">
    						<label for="class" class="col-sm-3 col-form-label">Class :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" id="class" name="class" value="{{ !empty($vessel->vesselDimension->class) ? $vessel->vesselDimension->class : '' }}" placeholder="">
    						</div>
    					</div>

    					<div class="form-group row">
    						<label for="class_not" class="col-sm-3 col-form-label">Class Notation :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" id="class_not" name="class_not" value="{{ !empty($vessel->vesselDimension->class_not) ? $vessel->vesselDimension->class_not : '' }}" placeholder="">
    						</div>
    					</div>

    					<div class="form-group row">
    						<label for="hp" class="col-sm-3 col-form-label">Horse Power :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" id="hp" name="hp" value="{{ !empty($vessel->vesselDimension->hp) ? $vessel->vesselDimension->hp : '' }}" placeholder="">
    						</div>
    					</div>

    					<div class="form-group row">
    						<label for="speed" class="col-sm-3 col-form-label">Speed :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" id="spreed" name="spreed" value="{{ !empty($vessel->vesselDimension->spreed) ? $vessel->vesselDimension->spreed : '' }}" placeholder="">
    						</div>
    					</div>

    					<div class="form-group row">
    						<label for="hold_cap" class="col-sm-3 col-form-label">Hold Capacity (Cargo hold (grain, including hatch coamings) :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" id="hold_cap" name="hold_cap" value="{{ !empty($vessel->vesselDimension->hold_cap) ? $vessel->vesselDimension->hold_cap : '' }}" placeholder="">
    						</div>
    					</div>

    					<hr>

    					<div class="form-group row">
    						<label for="car_gear" class="col-sm-3 col-form-label">Cargo Gear :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" id="car_gear" name="car_gear" value="{{ !empty($vessel->vesselDimension->car_gear) ? $vessel->vesselDimension->car_gear : '' }}" placeholder="">
    						</div>
    					</div>

    					<div class="form-group row">
    						<label for="car_hold" class="col-sm-3 col-form-label">Cargo Holds :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" id="car_hold" name="car_hold" value="{{ !empty($vessel->vesselDimension->car_hold) ? $vessel->vesselDimension->car_hold : '' }}" placeholder="">
    						</div>
    					</div>

    					<div class="form-group row">
    						<label for="bunk_cap" class="col-sm-3 col-form-label">Bunker Capacity :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" id="bunk_cap" name="bunk_cap" value="{{ !empty($vessel->vesselDimension->bunk_cap) ? $vessel->vesselDimension->bunk_cap : '' }}" placeholder="">
    						</div>
    					</div>

    					<div class="form-group row">
    						<label for="ball_cap" class="col-sm-3 col-form-label">Ballast Capacity :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" id="ball_cap" name="ball_cap" value="{{ !empty($vessel->vesselDimension->ball_cap) ? $vessel->vesselDimension->ball_cap : '' }}" placeholder="">
    						</div>
    					</div>

    					<div class="form-group row">
    						<label for="water_cap" class="col-sm-3 col-form-label">Fresh Water Capacity :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" id="water_cap" name="water_cap" value="{{ !empty($vessel->vesselDimension->water_cap) ? $vessel->vesselDimension->water_cap : '' }}" placeholder="">
    						</div>
    					</div>

    					<div class="form-group row mt-4">
    						<div class="col-sm-12 text-center">
    							<button type="button" class="btn btn-success btn-gninfo back">Back</button>
								@if(!empty($vessel->vesselDimension))
									<button type="submit" class="btn btn-success btn-gninfo">Update</button>
								@else
									<button type="submit" class="btn btn-success btn-gninfo">Save & Continue</button>
								@endif
    						</div>                                        
    					</div>
    				</form>
    			</div> 
    		</div>
    		<!-- ./tab-pane -->

    		<!-- tab-pane -->
    		<div class="tab-pane fade" id="main-engines" role="tabpanel" aria-labelledby="main-engines-tab"> 
    			<div class="bsc-form-wrapper main-engines-wrapper">
    				<div class="form-header">Main Engines</div>
    				<form id="v-engine-form">
						@csrf
						<div class="row justify-content-center form-group mt-3">
							<div class="col-md-11 alert alert-danger alert-dismissible fade show form_error" style="display:none" role="alert">
								<strong>Error Submission!!</strong> Please correct following info and resubmit.
								<label>    </label>
								<button type="button" class="close close_error_alert">
									<span aria-hidden="true">&times;</span>
								</button>
							</div>
							<input type="hidden" name="vessel_id" value="{{$id}}">
						</div>
    					<div class="form-group row mt-4">
    						<label for="menu_name" class="col-sm-3 col-form-label">Name of Manufacturer :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" id="menu_name" name="manu_name" value="{{ !empty($vessel->vesselEngine->manu_name) ? $vessel->vesselEngine->manu_name : '' }}"  placeholder="">
    						</div>
    					</div>

    					<div class="form-group row">
    						<label for="manu_address" class="col-sm-3 col-form-label">Address of Manufacturer :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" id="manu_address" name="manu_address" value="{{ !empty($vessel->vesselEngine->manu_address) ? $vessel->vesselEngine->manu_address : '' }}" placeholder="">
    						</div>
    					</div>

    					<div class="form-group row">
    						<label for="type" class="col-sm-3 col-form-label">Type :</label>
    						<div class="col-sm-9">
    							<input class="form-control" id="type" name="type" value="{{ !empty($vessel->vesselEngine->type) ? $vessel->vesselEngine->type : '' }}" placeholder="">
    						</div>
    					</div>

    					<div class="form-group row">
    						<label for="mod_num" class="col-sm-3 col-form-label">Model Number :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" id="mod_num" name="mod_num" value="{{ !empty($vessel->vesselEngine->mod_num) ? $vessel->vesselEngine->mod_num : '' }}" placeholder="">
    						</div>
    					</div>

    					<div class="form-group row">
    						<label for="sets_no" class="col-sm-3 col-form-label">No. of sets :</label>
    						<div class="col-sm-9">
    							<input class="form-control" id="sets_no" name="sets_no" value="{{ !empty($vessel->vesselEngine->sets_no) ? $vessel->vesselEngine->sets_no : '' }}" placeholder="">
    						</div>
    					</div>

    					<hr>

    					<div class="form-group row">
    						<label for="no_cyl_set" class="col-sm-3 col-form-label">No of cylender in each set (Reciprocating Engine) :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" id="no_cyl_set" name="no_cyl_set" value="{{ !empty($vessel->vesselEngine->no_cyl_set) ? $vessel->vesselEngine->no_cyl_set : '' }}" placeholder="">
    						</div>
    					</div>

    					<div class="form-group row">
    						<label for="diam_cyl" class="col-sm-3 col-form-label">Diameter of Cylender :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" id="diam_cyl" name="diam_cyl" value="{{ !empty($vessel->vesselEngine->diam_cyl) ? $vessel->vesselEngine->diam_cyl : '' }}" placeholder="">
    						</div>
    					</div>

    					<div class="form-group row">
    						<label for="length_stroke" class="col-sm-3 col-form-label">Length of Stroke :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" id="length_stroke" name="length_stroke" value="{{ !empty($vessel->vesselEngine->length_stroke) ? $vessel->vesselEngine->length_stroke : '' }}" placeholder="">
    						</div>
    					</div>

    					<div class="form-group row">
    						<label for="power_kw" class="col-sm-3 col-form-label">Power (K.W), RPM &  :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" id="power_kw" name="power_kw" value="{{ !empty($vessel->vesselEngine->power_kw) ? $vessel->vesselEngine->power_kw : '' }}" placeholder="">
    						</div>
    					</div>

    					<div class="form-group row">
    						<label for="rpm" class="col-sm-3 col-form-label">RPM :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" id="rpm" name="rpm" value="{{ !empty($vessel->vesselEngine->rpm) ? $vessel->vesselEngine->rpm : '' }}" placeholder="">
    						</div>
    					</div>

    					<hr>

    					<div class="form-group row">
    						<label for="speed" class="col-sm-3 col-form-label">Speed (Knots) :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" id="speed" name="speed" value="{{ !empty($vessel->vesselEngine->speed) ? $vessel->vesselEngine->speed : '' }}" placeholder="">
    						</div>
    					</div>

    					<div class="form-group row">
    						<label for="charger" class="col-sm-3 col-form-label">Turbochargers :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" id="charger" name="charger" value="{{ !empty($vessel->vesselEngine->charger) ? $vessel->vesselEngine->charger : '' }}" placeholder="">
    						</div>
    					</div>

    					<div class="form-group row">
    						<label for="fuel" class="col-sm-3 col-form-label">Type of fuel :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" id="fuel" name="fuel" value="{{ !empty($vessel->vesselEngine->fuel) ? $vessel->vesselEngine->fuel : '' }}" placeholder="">
    						</div>
    					</div>

    					<div class="form-group row mt-4">
    						<div class="col-sm-12 text-center">
    							<button type="button" class="btn btn-success btn-gninfo back">Back</button>
								@if(!empty($vessel->vesselEngine))
									<button type="submit" class="btn btn-success btn-gninfo">Update</button>
								@else
									<button type="submit" class="btn btn-success btn-gninfo">Save & Continue</button>
								@endif
    						</div>                                        
    					</div>
    				</form>
    			</div> 
    		</div>
    		<!-- ./tab-pane -->

    		<!-- tab-pane -->
    		<div class="tab-pane fade" id="boilers" role="tabpanel" aria-labelledby="boilers-tab"> 
    			<div class="bsc-form-wrapper boilers-wrapper">
    				<div class="form-header">Particulars of Boilers</div>
    				<form id="v-boiler-form">
						@csrf
						<div class="row justify-content-center form-group mt-3">
							<div class="col-md-11 alert alert-danger alert-dismissible fade show form_error" style="display:none" role="alert">
								<strong>Error Submission!!</strong> Please correct following info and resubmit.
								<label>    </label>
								<button type="button" class="close close_error_alert">
									<span aria-hidden="true">&times;</span>
								</button>
							</div>
							<input type="hidden" name="vessel_id" value="{{$id}}">
						</div>
    					<div class="form-group row mt-4">
    						<label for="boiler_num" class="col-sm-3 col-form-label">Numbers of Boilers :</label>
    						<div class="col-sm-9">
    							<input class="form-control" id="boiler_num" name="boiler_num" value="{{ !empty($vessel->vesselBoiler->boiler_num) ? $vessel->vesselBoiler->boiler_num : '' }}" placeholder="">
    						</div>
    					</div>
    					<div class="form-group row">
    						<label for="menu_name" class="col-sm-3 col-form-label">Name of Manufacturer :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" id="menu_name" name="manu_name" value="{{ !empty($vessel->vesselBoiler->manu_name) ? $vessel->vesselBoiler->manu_name : '' }}" placeholder="">
    						</div>
    					</div>

    					<div class="form-group row">
    						<label for="manu_address" class="col-sm-3 col-form-label">Address of Manufacturer :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" id="manu_address" name="manu_address" value="{{ !empty($vessel->vesselBoiler->manu_address) ? $vessel->vesselBoiler->manu_address : '' }}" placeholder="">
    						</div>
    					</div>


    					<div class="form-group row">
    						<label for="boilyer_type" class="col-sm-3 col-form-label">Type :</label>
    						<div class="col-sm-9">
    							<input type="text" class="form-control" id="boilyer_type" name="boiler_type" value="{{ !empty($vessel->vesselBoiler->boiler_type) ? $vessel->vesselBoiler->boiler_type : '' }}" placeholder="">
    						</div>
    					</div>

    					<div class="form-group row">
    						<label for="loaded_pressure" class="col-sm-3 col-form-label">Loaded Pressure :</label>
    						<div class="col-sm-9">
    							<input class="form-control" id="loaded_pressure" name="loaded_pressure" value="{{ !empty($vessel->vesselBoiler->loaded_pressure) ? $vessel->vesselBoiler->loaded_pressure : '' }}" placeholder="">
    						</div>
    					</div>

    					<div class="form-group row mt-4">
    						<div class="col-sm-12 text-center">
    							<button type="button" class="btn btn-success btn-gninfo back">Back</button>
								@if(!empty($vessel->vesselBoiler))
									<button type="submit" class="btn btn-success btn-gninfo">Update</button>
								@else
									<button type="submit" class="btn btn-success btn-gninfo">Save & Continue</button>
								@endif
    						</div>                                        
    					</div>
    				</form>
    			</div> 
    		</div>
    		<!-- ./tab-pane -->
    	</div>
    	<!-- ./tab-content -->

    </div>