@extends('layouts.admin-master')
@section('main-content')
<div class="col-lg-12">
	<div class="card" style="max-width: 1200px;">
		<div class="card-header pv-card-hader">
			<strong class="pptitle">Ship Particurlars for Registration of Vessel</strong>

			<div class="right-buttons">  
				<button class="btn btn-info btn-bvprint" onClick="print_vehical_info();" style="margin-right: 0px;"><i class="fa fa-print"></i> Print</button>
			</div>
		</div>
		<div class="card-body">			
			<div class="vessel-form privew-wraper" id="privew-wrapper">	
				<div class="row">
					<div class="col-lg-6">
						<div class="no-break">
							<h4>General Information of Vessel:</h4>
							<!-- geninfo-table -->
							<table class="geninfo-table">
								<tbody>
									<tr>
										<td class="p_lebel">Name of Vessel</td>
										<td class="p_dot">:</td>
										<td class="p_data">{{!empty($vessel->name) ? $vessel->name :''}}</td>    
									</tr> 
									<tr>
										<td class="p_lebel">Name, Address & Co. IMO of Owner</td>
										<td class="p_dot">:</td>
										<td class="p_data">
											<span class="owner_name">{{!empty($vessel->owner_name) ? $vessel->owner_name :''}}</span>, <br>
											<span class="owner_address">{{!empty($vessel->owner_address) ? $vessel->owner_address :''}}</span> <br>
											<!-- <span class="owner_co_imo">0036936</span> -->
										</td>    
									</tr> 
									<tr>
										<td class="p_lebel">
											Name, Address & Co. IMO of Manager <br>
											(Incloding place of oparation)
										</td>
										<td class="p_dot">:</td>
										<td class="p_data">
											<span class="manager_name">{{!empty($vessel->manager_name) ? $vessel->manager_name :''}}</span>, <br>
											<span class="manager_address">{{!empty($vessel->manager_address) ? $vessel->manager_address :''}}</span> <br>
											<!-- <span class="manager_co_imo">0036936</span> -->
										</td>    
									</tr>  
									<tr>
										<td class="p_lebel">
											Name & Certificate Number of Master <br>
											(With Validity)
										</td>
										<td class="p_dot">:</td>
										<td class="p_data">
											<span class="master_name">{{!empty($vessel->master_name) ? $vessel->master_name :''}}</span>, 
											<span class="master_cert_no">{{!empty($vessel->master_cert_no) ? $vessel->master_cert_no :''}}</span>,
											<span class="master_cert_validity">{{!empty($vessel->master_cert_validity) ? $vessel->master_cert_validity :''}}</span>
										</td>    
									</tr>  
									<tr>
										<td class="p_lebel">
											Name & Certificate Number of Ch. Eng. <br>
											(With Validity)
										</td>
										<td class="p_dot">:</td>
										<td class="p_data">
											<span class="ch_eng_name">{{!empty($vessel->ch_eng_name) ? $vessel->ch_eng_name :''}}</span>, 
											<span class="ch_eng_cert_no">{{!empty($vessel->ch_eng_cert_no) ? $vessel->ch_eng_cert_no :''}}</span>,
											<span class="ch_eng_cert_validity">{{!empty($vessel->ch_eng_cert_validity) ? $vessel->ch_eng_cert_validity :''}}</span>
										</td>    
									</tr>
									<tr>
										<td class="p_lebel">
											No., Port & Date of Previous Registry
										</td>
										<td class="p_dot">:</td>
										<td class="p_data ">
											<span class="prev_port_no">{{!empty($vessel->prev_port_no) ? $vessel->prev_port_no :''}}</span> 
											<!-- <span class="prev_reg_port">{{!empty($vessel->prev_reg_port) ? $vessel->prev_reg_port :''}}</span>, -->
											<span class="prev_reg_date">{{!empty($vessel->prev_reg_date) ? $vessel->prev_reg_date :''}}</span>
										</td>    
									</tr>                                  
								</tbody>
							</table>
							<!-- ./geninfo-table -->				
						</div>	

						<div class="no-break">
							<h4>Particulars of vessel:</h4>
							<!-- particulars-table -->
							<table class="particulars-table">
								<tbody>
									<tr>
										<td class="p_lebel">Types of Vassel</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="type">{{!empty($vessel->vesselDetail->type)?$vessel->vesselDetail->type:''}}</span></td>    
									</tr>  
									<tr>
										<td class="p_lebel">Existing Flag</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="flag">{{!empty($vessel->vesselDetail->flag)?$vessel->vesselDetail->flag:''}}</span></td>    
									</tr> 
									<tr>
										<td class="p_lebel">Call Sign</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="call_sign">{{!empty($vessel->vesselDetail->call_sign)?$vessel->vesselDetail->call_sign:''}}</span></td>    
									</tr>   
									<tr>
										<td class="p_lebel">IMO Number</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="imo_no">{{!empty($vessel->vesselDetail->imo_no)?$vessel->vesselDetail->imo_no:''}}</span></td>    
									</tr>  
									<tr>
										<td class="p_lebel">GRT/NRT/DWT</td>
										<td class="p_dot">:</td>
										<td class="p_data">
											<span class="grt">{{!empty($vessel->vesselDetail->grt)?$vessel->vesselDetail->grt:''}}</span>/
											<span class="nrt">{{!empty($vessel->vesselDetail->nrt)?$vessel->vesselDetail->nrt:''}}</span>/
											<span class="grt">{{!empty($vessel->vesselDetail->grt)?$vessel->vesselDetail->grt:''}}</span>
										</td>    
									</tr>   
									<tr>
										<td class="p_lebel">Official Number</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="off_no">{{!empty($vessel->vesselDetail->off_no)?$vessel->vesselDetail->off_no:''}}</span></td>    
									</tr>  
									<tr>
										<td class="p_lebel">Keel Laying date</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="keel_lay_date">{{!empty($vessel->vesselDetail->keel_lay_date)?$vessel->vesselDetail->keel_lay_date:''}}</span></td>    
									</tr> 
									<tr>
										<td class="p_lebel">Launching Date</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="launch_date">{{!empty($vessel->vesselDetail->launch_date)?$vessel->vesselDetail->launch_date:''}}</span></td>    
									</tr>   
									<!-- dfd -->
									<tr>
										<td class="p_lebel">Delivery / building Date</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="delivery_date">{{!empty($vessel->vesselDetail->delivery_date)?$vessel->vesselDetail->delivery_date:''}}</span></td>    
									</tr>  
									<tr>
										<td class="p_lebel">Tonnage Calculation/ Certification Date</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="cert_date">{{!empty($vessel->vesselDetail->cert_date)?$vessel->vesselDetail->cert_date:''}}</span></td>    
									</tr>   
									<tr>
										<td class="p_lebel">Year of Built</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="built_year">{{!empty($vessel->vesselDetail->built_year)?$vessel->vesselDetail->built_year:''}}</span></td>    
									</tr> 
									<tr>
										<td class="p_lebel">Where Built</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="built_loc">{{!empty($vessel->vesselDetail->built_loc)?$vessel->vesselDetail->built_loc:''}}</span></td>    
									</tr> 
									<tr>
										<td class="p_lebel">Where Built</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="built_loc">{{!empty($vessel->vesselDetail->built_loc)?$vessel->vesselDetail->built_loc:''}}</span></td>    
									</tr> 
									<tr>
										<td class="p_lebel">Whether Steam or Motor Ship and how propelled</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="steam_motor_propelled">{{!empty($vessel->vesselDetail->steam_motor_propelled)?$vessel->vesselDetail->steam_motor_propelled:''}}</span></td>    
									</tr> 
									<tr>
										<td class="p_lebel">Name & Address of Builder</td>
										<td class="p_dot">:</td>
										<td class="p_data">
											<span class="builder_name">{{!empty($vessel->vesselDetail->builder_name)?$vessel->vesselDetail->builder_name:''}}</span>,
											<span class="builder_address">{{!empty($vessel->vesselDetail->builder_address)?$vessel->vesselDetail->builder_address:''}}</span>
										</td>    
									</tr>  
									<tr>
										<td class="p_lebel">Number of Deck</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="deck_no">{{!empty($vessel->vesselDetail->deck_no)?$vessel->vesselDetail->deck_no:''}}</span></td>    
									</tr> 
									<tr>
										<td class="p_lebel">Number of masts</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="mast_no">{{!empty($vessel->vesselDetail->mast_no)?$vessel->vesselDetail->mast_no:''}}</span></td>    
									</tr> 
									<tr>
										<td class="p_lebel">Rigged</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="rigged">{{!empty($vessel->vesselDetail->rigged)?$vessel->vesselDetail->rigged:''}}</span></td>    
									</tr> 
									<tr>
										<td class="p_lebel">Stem</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="stem">{{!empty($vessel->vesselDetail->stem)?$vessel->vesselDetail->stem:''}}</span></td>    
									</tr>  
									<tr>
										<td class="p_lebel">Stern</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="stern">{{!empty($vessel->vesselDetail->stern)?$vessel->vesselDetail->stern:''}}</span></td>    
									</tr>  
									<tr>
										<td class="p_lebel">Build</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="build">{{!empty($vessel->vesselDetail->build)?$vessel->vesselDetail->build:''}}</span></td>    
									</tr>                
								</tbody>
							</table>
							<!-- ./particulars-table -->			
						</div>	
						
						<div class="no-break">
							<h4>Stern Framework and Description of Vessel:</h4>
							<!-- framework-description-table -->
							<table class="framework-description-table">
								<tbody>
									<tr>
										<td class="p_lebel">Number of bulkhead</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="bulk_no">{{!empty($vessel->vesselFrameworkAndDetail->bulk_no)?$vessel->vesselFrameworkAndDetail->bulk_no:''}}</span></td>    
									</tr>  
									<tr>
										<td class="p_lebel">Length from fore part of stem, to the aft side of the head of th stem post / fore side of the rudder stock</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="length_stem_rudder">{{!empty($vessel->vesselFrameworkAndDetail->length_stem_rudder)?$vessel->vesselFrameworkAndDetail->length_stem_rudder:''}}</span></td>    
									</tr> 
									<tr>
										<td class="p_lebel">Main breadth to outside of plating</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="main_breadth">{{!empty($vessel->vesselFrameworkAndDetail->main_breadth)?$vessel->vesselFrameworkAndDetail->main_breadth:''}}</span></td>    
									</tr>   
									<tr>
										<td class="p_lebel">Depth in hold from tonnage deck to ceiling a amidships</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="depth">{{!empty($vessel->vesselDimension->depth)?$vessel->vesselDimension->depth:''}}</span></td>    
									</tr>  
									<tr>
										<td class="p_lebel">Length of Engine Room</td>
										<td class="p_dot">:</td>
										<td class="p_data">
											<span class="length_eng_room">{{!empty($vessel->vesselDimension->length_eng_room)?$vessel->vesselDimension->length_eng_room:''}}</span>
										</td>    
									</tr>   
									<tr>
										<td class="p_lebel">Number of sets of Engines</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="eng_set_no">{{!empty($vessel->vesselFrameworkAndDetail->eng_set_no)?$vessel->vesselFrameworkAndDetail->eng_set_no:''}}</span></td>    
									</tr>  
									<tr>
										<td class="p_lebel">Number of Shaft</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="shaft_no">{{!empty($vessel->vesselFrameworkAndDetail->shaft_no)?$vessel->vesselFrameworkAndDetail->shaft_no:''}}</span></td>    
									</tr> 
									<tr>
										<td class="p_lebel">Number of Cylenders in each Sets</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="no_cyl_set">{{!empty($vessel->vesselEngine->no_cyl_set)?$vessel->vesselEngine->no_cyl_set:''}}</span></td>    
									</tr>   
									<tr>
										<td class="p_lebel">Length of stroke</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="length_stroke">{{!empty($vessel->vesselEngine->length_stroke)?$vessel->vesselEngine->length_stroke:''}}</span></td>    
									</tr>  
									<tr>
										<td class="p_lebel">Diameter of cylinders</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="diam_cyl">{{!empty($vessel->vesselEngine->diam_cyl)?$vessel->vesselEngine->diam_cyl:''}}</span></td>    
									</tr>   
									<tr>
										<td class="p_lebel">Speed of ship</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="speed">{{!empty($vessel->vesselEngine->speed)?$vessel->vesselEngine->speed:''}}</span></td>    
									</tr> 
									<tr>
										<td class="p_lebel">Evaporations Loaded Pressure</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="loaded_pressure">{{!empty($vessel->vesselFrameworkAndDetail->loaded_pressure)?$vessel->vesselFrameworkAndDetail->loaded_pressure:''}}</span></td>    
									</tr> 
									<tr>
										<td class="p_lebel">Gross Tonnage</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="gro_ton">{{!empty($vessel->vesselFrameworkAndDetail->gro_ton)?$vessel->vesselFrameworkAndDetail->gro_ton:''}}</span></td>    
									</tr> 
									<tr>
										<td class="p_lebel">Net Tonnage</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="net_ton">{{!empty($vessel->vesselFrameworkAndDetail->net_ton)?$vessel->vesselFrameworkAndDetail->net_ton:''}}</span></td>    
									</tr> 
									<tr>
										<td class="p_lebel">Certified accommodations</td>
										<td class="p_dot">:</td>
										<td class="p_data">
											<span class="cert_accom">{{!empty($vessel->vesselFrameworkAndDetail->cert_accom)?$vessel->vesselFrameworkAndDetail->cert_accom:''}}</span>
										</td>    
									</tr>  
									<tr>
										<td class="p_lebel">Number of Lifeboats</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="lifeboat_num">{{!empty($vessel->vesselFrameworkAndDetail->lifeboat_num)?$vessel->vesselFrameworkAndDetail->lifeboat_num:''}}</span></td>    
									</tr> 
									<tr>
										<td class="p_lebel">Number of Life rafts</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="rafts_num">{{!empty($vessel->vesselFrameworkAndDetail->rafts_num)?$vessel->vesselFrameworkAndDetail->rafts_num:''}}</span></td>    
									</tr> 
									<tr>
										<td class="p_lebel">Number of persons accomodated by them</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="per_accom_num">{{!empty($vessel->vesselFrameworkAndDetail->per_accom_num)?$vessel->vesselFrameworkAndDetail->per_accom_num:''}}</span></td>    
									</tr> 
									<tr>
										<td class="p_lebel">Number of life rafts raq. by ragulation 111/31.1.4</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="rafts_req_num">{{!empty($vessel->vesselFrameworkAndDetail->rafts_req_num)?$vessel->vesselFrameworkAndDetail->rafts_req_num:''}}</span></td>    
									</tr>  
									<tr>
										<td class="p_lebel">Number of Life Buoys</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="buoys_num">{{!empty($vessel->vesselFrameworkAndDetail->buoys_num)?$vessel->vesselFrameworkAndDetail->buoys_num:''}}</span></td>    
									</tr>  
									<tr>
										<td class="p_lebel">Number of Life Jackets</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="jack_num">{{!empty($vessel->vesselFrameworkAndDetail->jack_num)?$vessel->vesselFrameworkAndDetail->jack_num:''}}</span></td>    
									</tr>  
									<tr>
										<td class="p_lebel">Total Number of immersion Suits</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="imm_suit_num">{{!empty($vessel->vesselFrameworkAndDetail->imm_suit_num)?$vessel->vesselFrameworkAndDetail->imm_suit_num:''}}</span></td>    
									</tr>  
									<tr>
										<td class="p_lebel">Number of thermal Protective aids</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="therm_pro_num">{{!empty($vessel->vesselFrameworkAndDetail->therm_pro_num)?$vessel->vesselFrameworkAndDetail->therm_pro_num:''}}</span></td>    
									</tr>  
									<tr>
										<td class="p_lebel">Number of rudder transponder</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="trans_rud_num">{{!empty($vessel->vesselFrameworkAndDetail->trans_rud_num)?$vessel->vesselFrameworkAndDetail->trans_rud_num:''}}</span></td>    
									</tr>  
									<tr>
										<td class="p_lebel">Propeller</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="propeller">{{!empty($vessel->vesselFrameworkAndDetail->propeller)?$vessel->vesselFrameworkAndDetail->propeller:''}}</span></td>    
									</tr>                 
								</tbody>
							</table>
							<!-- ./framework-description-table -->			
						</div>							
					</div>
					<div class="col-lg-6">
						<div class="no-break">
							<h4>Dimension:</h4>
							<!-- dimension-table -->
							<table class="dimension-table">
								<tbody>
									<tr>
										<td class="p_lebel">Length (LL Reg III/3.12)</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="length_LL">{{!empty($vessel->vesselDimension->length_LL)?$vessel->vesselDimension->length_LL:''}}</span></td>    
									</tr>  
									<tr>
										<td class="p_lebel">Length O.A & B.P</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="length_OA">{{!empty($vessel->vesselDimension->length_OA)?$vessel->vesselDimension->length_OA:''}}</span></td>    
									</tr> 
									<tr>
										<td class="p_lebel">Breadth MLD</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="breadth">{{!empty($vessel->vesselDimension->breadth)?$vessel->vesselDimension->breadth:''}}</span></td>    
									</tr>   
									<tr>
										<td class="p_lebel">Depth MLD</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="depth">{{!empty($vessel->vesselDimension->depth)?$vessel->vesselDimension->depth:''}}</span></td>    
									</tr>  
									<tr>
										<td class="p_lebel">Length of Engine Room</td>
										<td class="p_dot">:</td>
										<td class="p_data">
											<span class="length_eng_room">{{!empty($vessel->vesselDimension->length_eng_room)?$vessel->vesselDimension->length_eng_room:''}}</span>
										</td>    
									</tr>   
									<tr>
										<td class="p_lebel">Draft summer</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="draft">{{!empty($vessel->vesselDimension->draft)?$vessel->vesselDimension->draft:''}}</span></td>    
									</tr>  
									<tr>
										<td class="p_lebel">Suez Gross Tonnage</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="suez_geo_ton">{{!empty($vessel->vesselDimension->suez_geo_ton)?$vessel->vesselDimension->suez_geo_ton:''}}</span></td>    
									</tr> 
									<tr>
										<td class="p_lebel">Suez Net Tonnage</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="suez_net_ton">{{!empty($vessel->vesselDimension->suez_net_ton)?$vessel->vesselDimension->suez_net_ton:''}}</span></td>    
									</tr>   
									<tr>
										<td class="p_lebel">Panama Net Tonnage</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="pana_ton">{{!empty($vessel->vesselDimension->pana_ton)?$vessel->vesselDimension->pana_ton:''}}</span></td>    
									</tr>  
									<tr>
										<td class="p_lebel">Class</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="class">{{!empty($vessel->vesselDimension->class)?$vessel->vesselDimension->class:''}}</span></td>    
									</tr>   
									<tr>
										<td class="p_lebel">Class Notation</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="class_not">{{!empty($vessel->vesselDimension->class_not)?$vessel->vesselDimension->class_not:''}}</span></td>    
									</tr> 
									<tr>
										<td class="p_lebel">Horse Power</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="hp">{{!empty($vessel->vesselDimension->hp)?$vessel->vesselDimension->hp:''}}</span></td>    
									</tr> 
									<tr>
										<td class="p_lebel">Speed</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="speed">{{!empty($vessel->vesselEngine->speed)?$vessel->vesselEngine->speed:''}}</span></td>    
									</tr> 
									<tr>
										<td class="p_lebel">Hold Capacity (Cargo hold (grain, including hatch coamings)</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="hold_cap">{{!empty($vessel->vesselDimension->hold_cap)?$vessel->vesselDimension->hold_cap:''}}</span></td>    
									</tr> 
									<tr>
										<td class="p_lebel">Cargo Gear</td>
										<td class="p_dot">:</td>
										<td class="p_data">
											<span class="car_gear">{{!empty($vessel->vesselDimension->car_gear)?$vessel->vesselDimension->car_gear:''}}</span>
										</td>    
									</tr>  
									<tr>
										<td class="p_lebel">Cargo Holds</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="car_hold">{{!empty($vessel->vesselDimension->car_hold)?$vessel->vesselDimension->car_hold:''}}</span></td>    
									</tr> 
									<tr>
										<td class="p_lebel">Bunker Capacity</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="bunk_cap">{{!empty($vessel->vesselDimension->bunk_cap)?$vessel->vesselDimension->bunk_cap:''}}</span></td>    
									</tr> 
									<tr>
										<td class="p_lebel">Ballast Capacity</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="ball_cap">{{!empty($vessel->vesselDimension->ball_cap)?$vessel->vesselDimension->ball_cap:''}}</span></td>    
									</tr> 
									<tr>
										<td class="p_lebel">Fresh Water Capacity</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="water_cap">{{!empty($vessel->vesselDimension->water_cap)?$vessel->vesselDimension->water_cap:''}}</span></td>    
									</tr>      
								</tbody>
							</table>
							<!-- ./dimension-table -->			
						</div>	
						
						<div class="no-break">
							<h4>Main Engines:</h4>
							<!-- main-engines-table -->
							<table class="main-engines-table">
								<tbody>
									<tr>
										<td class="p_lebel">Name & Address of Manufacturer</td>
										<td class="p_dot">:</td>
										<td class="p_data">
											<span class="menu_name">{{!empty($vessel->vesselEngine->menu_name)?$vessel->vesselEngine->menu_name:''}}</span>, 
											<span class="manu_address">{{!empty($vessel->vesselEngine->manu_address)?$vessel->vesselEngine->manu_address:''}}</span>
										</td>    
									</tr>  
									<tr>
										<td class="p_lebel">Type</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="type">{{!empty($vessel->vesselEngine->type)?$vessel->vesselEngine->type:''}}</span></td>    
									</tr>   
									<tr>
										<td class="p_lebel">Model Number</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="mod_num">{{!empty($vessel->vesselEngine->mod_num)?$vessel->vesselEngine->mod_num:''}}</span></td>    
									</tr>  
									<tr>
										<td class="p_lebel">No. of sets</td>
										<td class="p_dot">:</td>
										<td class="p_data">
											<span class="sets_no">{{!empty($vessel->vesselEngine->sets_no)?$vessel->vesselEngine->sets_no:''}}</span>
										</td>    
									</tr>   
									<tr>
										<td class="p_lebel">No of cylender in each set (Reciprocating Engine)</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="no_cyl_set">{{!empty($vessel->vesselEngine->no_cyl_set)?$vessel->vesselEngine->no_cyl_set:''}}</span></td>    
									</tr>  
									<tr>
										<td class="p_lebel">Diameter of Cylender</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="diam_cyl">{{!empty($vessel->vesselEngine->diam_cyl)?$vessel->vesselEngine->diam_cyl:''}}</span></td>    
									</tr> 
									<tr>
										<td class="p_lebel">Length of Stroke</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="length_stroke">{{!empty($vessel->vesselEngine->length_stroke)?$vessel->vesselEngine->length_stroke:''}}</span></td>    
									</tr>   
									<tr>
										<td class="p_lebel">Power (K.W), RPM & Speed (Knots)</td>
										<td class="p_dot">:</td>
										<td class="p_data">
											<span class="power_kw">{{!empty($vessel->vesselEngine->power_kw)?$vessel->vesselEngine->power_kw:''}}</span>, 
											<span class="rpm">{{!empty($vessel->vesselEngine->rpm)?$vessel->vesselEngine->rpm:''}}</span>, 
											<span class="speed">{{!empty($vessel->vesselEngine->speed)?$vessel->vesselEngine->speed:''}}</span>
										</td>    
									</tr>  
									<tr>
										<td class="p_lebel">Turbochargers</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="charger">{{!empty($vessel->vesselEngine->charger)?$vessel->vesselEngine->charger:''}}</span></td>    
									</tr>   
									<tr>
										<td class="p_lebel">Type of fuel</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="fuel">{{!empty($vessel->vesselEngine->fuel)?$vessel->vesselEngine->fuel:''}}</span></td>    
									</tr> 
								</tbody>
							</table>
							<!-- ./main-engines-table -->			
						</div>	
						
						<div class="no-break">
							<h4>Particulars of Boilers:</h4>
							<!-- boilers-table -->
							<table class="boilers-table">
								<tbody>
									<tr>
										<td class="p_lebel">Numbers of Boilers</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="boiler_num">{{!empty($vessel->vesselBoiler->boiler_num)?$vessel->vesselBoiler->boiler_num:''}}</span></td>    
									</tr>  
									<tr>
										<td class="p_lebel">Name & Address of Manufacturer</td>
										<td class="p_dot">:</td>
										<td class="p_data">
											<span class="manu_name">{{!empty($vessel->vesselBoiler->manu_name)?$vessel->vesselBoiler->manu_name:''}}</span> <br> 
											<span class="manu_address">{{!empty($vessel->vesselBoiler->manu_address)?$vessel->vesselBoiler->manu_address:''}}</span>
										</td>    
									</tr>   
									<tr>
										<td class="p_lebel">Type</td>
										<td class="p_dot">:</td>
										<td class="p_data"><span class="boiler_type">{{!empty($vessel->vesselBoiler->boiler_type)?$vessel->vesselBoiler->boiler_type:''}}</span></td>    
									</tr>  
									<tr>
										<td class="p_lebel">Loaded Pressure</td>
										<td class="p_dot">:</td>
										<td class="p_data">
											<span class="loaded_pressure">{{!empty($vessel->vesselBoiler->loaded_pressure)?$vessel->vesselBoiler->loaded_pressure:''}}</span>
										</td>    
									</tr>  
								</tbody>
							</table>
							<!-- ./boilers-table -->			
						</div>							
					</div>
				</div>
				

			</div>
		</div>
	</div>	
</div>

<!-- order-print-header -->
<div id="order-print-header" class="print-header" >
    <div class="title-wrap">
        <h2 class="line2">Bangladesh Shipping Corporation</h2>
        <h2 class="line3">Ship <span></span> Repair <span></span> Department</h2>
        <h3 class="line4">Vessel Detail Info</h3>
    </div>
</div>
<!-- ./order-print-header -->

@endsection
