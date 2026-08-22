<?php
namespace App\Http\Controllers;
use App\Boiler;
use App\Category;
use App\Certificate;
use App\Dimension;
use App\Engine;
use App\FrameworkDescription;
use App\Http\Controllers\RoleController;
use App\Http\Requests\BoilerValidate;
use App\Http\Requests\CategoryFormValidate;
use App\Http\Requests\CertificateEditFormVal;
use App\Http\Requests\CertificateFormValidate;
use App\Http\Requests\DimensionValidate;
use App\Http\Requests\EngineValidate;
use App\Http\Requests\ItemFormValidate;
use App\Http\Requests\OrderFormValidate;
use App\Http\Requests\SurveyFormValidate;
use App\Http\Requests\UserFormVal;
use App\Http\Requests\VesselFrameworkAndDescriptionValidate;
use App\Http\Requests\VesselGenInfoValidate;
use App\Http\Requests\VesselParticularDetailValidate;
use App\Http\Requests\updateUserFormVal;
use App\Item;
use App\Order;
use App\OrderApproval;
use App\OrderItem;
use App\Role;
use App\VesselSurvey;
use App\VesselCertificate;
use App\Survey;
use App\User;
use App\Vessel;
use App\VesselParticular;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
class HomeController extends Controller
{
  public function __construct()
  {
    $this->middleware('auth');
  }
  public function index()
  {
    if(!empty(auth()->user()->role->role && auth()->user()->role->user_type=='ship')){
      if(auth()->user()->role->role=='second-engineer'){
        $orders=Order::where('status','ready')
        ->where('ord_status',true)
        ->where('vessel_id', auth()->user()->role->vessel->id)
        ->orderBy('created_at','desc')
        ->get();
        return view('layouts.ship-home',compact('orders'));  
      }else{
        return redirect('/pending/requisition');
      } 
    }
    elseif(!empty(auth()->user()->role->role && auth()->user()->role->user_type=='ssm')){
      return redirect('/pending/requisition');
    }
    elseif(!empty(auth()->user()->role->role && auth()->user()->role->user_type=='srd')||!empty(auth()->user()->role->role && auth()->user()->role->role=='super-admin')){
      $surveys=Survey::where('status',true)->orderBy('name','asc')->get();
      $certificates=Certificate::orderBy('created_at','desc')->where('status',true)->get();
      $vessel_certificates=VesselCertificate::orderBy('created_at','desc')->where('status',true)->get();
      $vessel_surveys=VesselSurvey::orderBy('created_at','desc')->where('status',true)->get();
      $vessels=Vessel::orderBy('created_at','desc')->where('status',true)->get();

      return view('home',compact('surveys','certificates','vessels')); 
    }
  }
  public function getSurvey()
  {
    $vessel_surveys=VesselSurvey::orderBy('created_at','desc')->where('status',true)->get();
    $vessels=Vessel::orderBy('created_at','desc')->where('status',true)->get();
    $surveys=Survey::orderBy('created_at','desc')->where('status',true)->get();
    return view('layouts.survey',compact('vessel_surveys','vessels','surveys'));
  }
  public function storeSurvey(SurveyFormValidate $request)
  {
    $survey=new VesselSurvey;
    $survey->survey_id=$request->Survey_Name;
    $survey->society_name=$request->Survey_Society;
    $survey->survey_date=$request->Survey_Date;
    $survey->survey_exp_date=$request->Survey_Expire_Date;
    $survey->vessel_id=$request->Vessel_Name;
    $survey->created_by=auth()->user()->id;
    $survey->save();
    $newsurvey=VesselSurvey::with('vessel')->with('Survey')->where('id',$survey->id)->first();
    $data ="New Vessel Survey has been added successfully.";
    return array($data,$newsurvey);
        // return view('layouts.survey');
  }
  public function getOneSurvey($id)
  {
    return $survey=VesselSurvey::with('vessel')->with('survey')->where('id',$id)->first();
  }
  public function updateOneSurvey(SurveyFormValidate $request)
  {
    $survey=VesselSurvey::findOrFail($request->survey_Id);
    $survey->survey_id=$request->Survey_Name;
    $survey->vessel_id=$request->Vessel_Name;
    $survey->society_name=$request->Survey_Society;
    $survey->survey_date=$request->Survey_Date;
    $survey->survey_exp_date=$request->Survey_Expire_Date;
    $survey->updated_by=auth()->user()->id;
    $survey->update();
    $newsurvey=VesselSurvey::with('vessel')->with('survey')->where('id',$survey->id)->first();
    $data ="Requested vessel Survey has been updated Successfully.";
    return array($data,$newsurvey);
  }
  public function deleteOneSurvey(Request $request)
  {
    $survey =VesselSurvey::findOrFail($request->id);
    $survey->status=false; 
    $survey->update(); 
    $data ="Requested Vessel Survey has been deleted successfully!";
    return array($data);
  }
  public function getCertificates()
  {
    $vessels=Vessel::orderBy('created_at','desc')->where('status',true)->get();
    $vessel_certificates=VesselCertificate::orderBy('created_at','desc')->where('status',true)->get();
    $certificates=Certificate::orderBy('created_at','desc')->where('status',true)->get();
    return view('layouts.certificate',compact('certificates','vessels','vessel_certificates'));
  }
  public function certificateStore(CertificateFormValidate $request)
  {
    $certificate=new VesselCertificate; 
    $certificate->certificate_id=$request->Certificate_Name;
    $certificate->vessel_id=$request->Vessel_Name;
    $certificate->issue_auth=$request->Issuing_Authority;
    $certificate->issue_date=$request->Issue_Date;
    $certificate->exp_date=$request->Certificate_Expire_Date;
    $certificate->created_by=auth()->user()->id;
    if($request->hasFile('Certificate_Copy')){
     $name = 'images/cert_copy/'.time() . '.' . $request->Certificate_Copy->getClientOriginalExtension();
     $request->Certificate_Copy->move(base_path('images/cert_copy'), $name);
     $certificate->cert_copy=$name;
   }
   $certificate->save();
   $newcertificate=VesselCertificate::with('vessel')->with('certificate')->where('id',$certificate->id)->first();
   $data ="New Vessel Certificate has been added successfully.";
   return array($data,$newcertificate,url('/'));
 }

 public function getOneCertificate($id)
 {
  return array ($vessel_certificate=VesselCertificate::with('vessel')->with('certificate')->where('id',$id)->first(),url('/'));
}
public function updateOneCertificate(CertificateEditFormVal $request)
{
  $certificate= VesselCertificate::findOrFail($request->Cert_Id);
  $certificate->certificate_id=$request->Certificate_Name;
  $certificate->issue_auth=$request->Issuing_Authority;
  $certificate->issue_date=$request->Issue_Date;
  $certificate->exp_date=$request->Certificate_Expire_Date;
  $certificate->vessel_id=$request->Vessel_Name;
  if($request->hasFile('Certificate_Copy')){
   \File::delete('images/cert_copy/' . basename($certificate->cert_copy));
   $name = 'images/cert_copy/'.time() . '.' . $request->Certificate_Copy->getClientOriginalExtension();
   $request->Certificate_Copy->move(base_path('images/cert_copy'), $name);
   $certificate->cert_copy=$name;
 }
 $certificate->updated_by=auth()->user()->id;
 $certificate->update();
 $newcertificate=VesselCertificate::with('vessel')->with('certificate')->where('id',$certificate->id)->first();
 $data ="Requested Vessel Certificate has been updated successfully.";
 return array($data,$newcertificate,url('/'));
}
public function deleteOneCertificate(Request $request)
{
  $certificate =VesselCertificate::findOrFail($request->id);
  // \File::delete('images/cert_copy/' . basename($certificate->cert_copy));
  $certificate->status = false; 
  $certificate->update(); 
  $data ="Requested Vessel Certificate has been deleted successfully!";
  return array($data);
}
public function getItem(){
  $categories=Category::orderBy('created_at','desc')->where('status',true)->get();
  $items=Item::orderBy('created_at','desc')->where('status',true)->get();
  return view('layouts.item',compact('categories','items'));
}
public function getCategory(){
  $categories=Category::orderBy('created_at','desc')->where('status',true)->get();
  return view('layouts.category',compact('categories'));
}
public function storeCategory(CategoryFormValidate $request){
 $category = new Category;
 $category->name=$request->name;
 $category->symbol=$request->symbol;
 $category->created_by=auth()->user()->name;
 $category->updated_by='';
 $category->status=true;
 $category->save();
 $data ="New Category has been added successfully.";
 return array($data,$category);
}
public function updateCategory(CategoryFormValidate $request){
 $category = Category::findOrFail($request->Category_Id);
 $category->name=$request->name;
 $category->symbol=$request->symbol;
 $category->updated_by=auth()->user()->name;
 $category->update();
 $data ="Requested Category has been updated successfully.";
 return array($data,$category);
}
public function deleteCategory(Request $request){
  $category =Category::findOrFail($request->id);
  $category->status = false; 
  $category->update(); 
  $data ="Requested Category has been deleted successfully!";
  return array($data);
}
public function getVessels()
{
  $vessels=Vessel::orderBy('created_at','desc')->where('status',true)->get();
  return view('layouts.vessel',compact('vessels'));
}
public function addVesselForm()
{
  return view('layouts.add-vessel');
}
public function storeVesselGenInfo(VesselGenInfoValidate $req)
{
  $genInfo=new Vessel;
  $genInfo->name=$req->vessel_name;
  $genInfo->owner_name=$req->owner_name;
  $genInfo->owner_address=$req->owner_address;
  $genInfo->manager_name=$req->manager_name;
  $genInfo->manager_address=$req->manager_address;
  $genInfo->master_name=$req->master_name;
  $genInfo->master_cert_no=$req->master_certificate_no;
  $genInfo->master_cert_validity=$req->master_certificate_validity;
  $genInfo->ch_eng_name=$req->cheif_engineer_name;
  $genInfo->ch_eng_cert_no=$req->cheif_engineer_certificate_no;
  $genInfo->ch_eng_cert_validity=$req->cheif_engineer_certificate_validity;
  $genInfo->prev_port_no=$req->prev_port_no;
  $genInfo->prev_reg_date=$req->prev_reg_date;
  $genInfo->status=true;
  $genInfo->save();
  $data ="New Vessel's General Info has been saved successfully.";
  return array($data,$genInfo);
}

public function addVesselDetail($id){
  $vessel = Vessel::findOrFail($id);
//  dd($vessel->vesselDetail);
  return view('layouts.add-vessel-detail',compact('id','vessel'));
}

public function editVessel($id){
  $vessel= Vessel::findOrFail($id);
  return view('layouts.vessel-edit',compact('id','vessel'));
}
public function storeItem(ItemFormValidate $request){
  $item = new Item;
  $item->name=$request->Item_Name;
  $item->category_id=$request->Category_Name;
  $item->impa_code=$request->Impa_Code_No;
  $item->unit=$request->Measurement_Unit;
  $item->created_by=auth()->user()->name;
  $item->updated_by='';
  $item->status=true;
  $item->save();
  $data ="New Item has been saved successfully.";
  return array($data,$item,$item->category);
}
public function getOneItem($id){
  return array ($item=Item::with('category')->where('id',$id)->first());
}
public function updateOneItem(ItemFormValidate $request){
  $item = Item::findOrFail($request->item_id);
  $item->name=$request->Item_Name;
  $item->category_id=$request->Category_Name;
  $item->impa_code=$request->Impa_Code_No;
  $item->unit=$request->Measurement_Unit;
  $item->updated_by=auth()->user()->name;
  $item->update();
  $data ="Requested Item has been Updated successfully.";
  return array($data,$item,$item->category);
}
public function deleteOneItem(Request $request){
  $item =Item::findOrFail($request->id);
  $item->status = false; 
  $item->update(); 
  $data ="Requested Item has been deleted successfully!";
  return array($data);
}
public function getOrder(){
  $items =Item::orderBy('created_at','desc')->where('status',true)->get();
  $categories =Category::orderBy('created_at','desc')->where('status',true)->get();
  $vessels =Vessel::orderBy('created_at','desc')->where('status',true)->get();
  
  if(auth()->user()->role->role=='second-engineer' || auth()->user()->role->role=='chief-officer'){
    $orders=Order::where('status','ready')
    ->where('ord_status',true)
    ->where('vessel_id', auth()->user()->role->vessel->id)
    ->orderBy('created_at','desc')
    ->get();
  } 
  if(auth()->user()->role->role=='super-admin' || auth()->user()->role->role=='gm-srd'){
    $orders=Order::
    where('ord_status',true)
    ->orderBy('created_at','desc')
    ->get();
  }
  return view('layouts.order',compact('items','categories','vessels','orders'));
}
public function createOrder(){
  $categories =Category::orderBy('created_at','desc')->where('status',true)->get();
  $vessels =Vessel::orderBy('created_at','desc')->where('status',true)->get();
  return view('layouts.create-order',compact('categories','vessels'));
}
public function getItemsByCat($cat_id){
  $category =Category::findOrFail($cat_id);
  return $category->items;
}
public function storeOrder(Request $request)
{
  // return $request->all();
  $counter=0;
  $cat=Category::findOrFail($request->Category_Name);
  $counter=Order::where('vessel_id',auth()->user()->role->vessel->id)
  ->whereYear('req_date',Carbon::now()->year)->where('ord_status',true)->get()->count();
  $counter+=1;
  if($counter<10)
  {
    $counter='0'.$counter;
  }
  if (!empty($request->item_id) && count($request->item_id) > 0) {
    $order = new Order;
    $order->vessel_id = auth()->user()->role->vessel->id;
    $order->category_id = $request->Category_Name;
    $order->req_date =Carbon::now();
    $order->req_no = 'DK/'.$cat->symbol.'/'.$counter .'/'.Carbon::now()->year;
    $order->port_name = $request->Port_Name;
    $order->created_by_role = auth()->user()->role->role;
    $order->created_by = auth()->user()->id;
    $order->status ='ready';
    $order->ord_status=true;
    $order->save();
    for ($i = 0; $i < count($request->item_id); $i++) {
      $orderitem = new OrderItem;
      $orderitem->order_id = $order->id;
      $orderitem->item_id = $request->item_id[$i];
      $orderitem->item_qty = $request->item_qty[$i];
      $orderitem->save();
    }
    $OrderApproval = new OrderApproval;
    $OrderApproval->order_id =$order->id;
    // if(auth()->user()->role->role=='chief-officer'){
    //   $OrderApproval->cheif_ofcr_app =auth()->user()->id;
    // }
    // else{
    //   $OrderApproval->second_eng_app =auth()->user()->id;
    // }
    $OrderApproval->save();
    $alert = "success";
    $data = "New Order has been submitted successfully!";
    return array($alert, $data);
  } else {
    $alert = "fail";
    $data = "Add item and retry. Thank You.";
    return array($alert, $data);
  }
}
public function createdOrders()
{
  $categories =Category::orderBy('created_at','desc')->where('status',true)->get();
  $vessels =Vessel::orderBy('created_at','desc')->where('status',true)->get();
    $orders=Order::with('orderApproval')->where('ord_status',true)
    ->where('created_by_role',auth()->user()->role->role)
    ->where('vessel_id', auth()->user()->role->vessel->id)
    ->whereHas('orderApproval',function($q){
      $q->where('cheif_ofcr_app',null)
      ->where('second_eng_app',null);
    })
    ->orderBy('created_at','desc')
    ->get();
  return view('layouts.created-orders',compact('categories','vessels','orders'));
}

public function storeVessParticularDetail(VesselParticularDetailValidate $req){
  $check_vessel = Vessel::findOrFail($req['vessel_id']);

  if(empty($check_vessel->vesselDetail)){

    $vessParDetail = new VesselParticular();
    $vessParDetail->vessel_id=$req['vessel_id'];
    $vessParDetail->type=$req['type'];
    $vessParDetail->flag=$req['flag'];
    $vessParDetail->call_sign=$req['call_sign'];
    $vessParDetail->imo_no=$req['imo_no'];
    $vessParDetail->grt=$req['grt'];
    $vessParDetail->nrt=$req['nrt'];
    $vessParDetail->dwt=$req['dwt'];
    $vessParDetail->off_no=$req['off_no'];
    $vessParDetail->keel_lay_date=$req['keel_lay_date'];
    $vessParDetail->launch_date=$req['launch_date'];
    $vessParDetail->delivery_date=$req['delivery_date'];
    $vessParDetail->cert_date=$req['cert_date'];
    $vessParDetail->built_year=$req['built_year'];
    $vessParDetail->built_loc=$req['built_loc'];
    $vessParDetail->steam_motor_propelled=$req['steam_motor_propelled'];
    $vessParDetail->builder_name=$req['builder_name'];
    $vessParDetail->builder_address=$req['builder_address'];
    $vessParDetail->deck_no=$req['deck_no'];
    $vessParDetail->mast_no=$req['mast_no'];
    $vessParDetail->rigged=$req['rigged'];
    $vessParDetail->stem=$req['stem'];
    $vessParDetail->stern=$req['stern'];
    $vessParDetail->build=$req['build'];
    $vessParDetail->imo_no=$req['imo_no'];

    $vessParDetail->save();

    $data ="Vessel's Particular Details Info has been saved successfully.";
    return array($data,$vessParDetail);
  }else{

    $vessParDetail = VesselParticular::where('vessel_id','=',$req['vessel_id'])->first();
    $vessParDetail->vessel_id=$req['vessel_id'];
    $vessParDetail->type=$req['type'];
    $vessParDetail->flag=$req['flag'];
    $vessParDetail->call_sign=$req['call_sign'];
    $vessParDetail->imo_no=$req['imo_no'];
    $vessParDetail->grt=$req['grt'];
    $vessParDetail->nrt=$req['nrt'];
    $vessParDetail->dwt=$req['dwt'];
    $vessParDetail->off_no=$req['off_no'];
    $vessParDetail->keel_lay_date=$req['keel_lay_date'];
    $vessParDetail->launch_date=$req['launch_date'];
    $vessParDetail->delivery_date=$req['delivery_date'];
    $vessParDetail->cert_date=$req['cert_date'];
    $vessParDetail->built_year=$req['built_year'];
    $vessParDetail->built_loc=$req['built_loc'];
    $vessParDetail->steam_motor_propelled=$req['steam_motor_propelled'];
    $vessParDetail->builder_name=$req['builder_name'];
    $vessParDetail->builder_address=$req['builder_address'];
    $vessParDetail->deck_no=$req['deck_no'];
    $vessParDetail->mast_no=$req['mast_no'];
    $vessParDetail->rigged=$req['rigged'];
    $vessParDetail->stem=$req['stem'];
    $vessParDetail->stern=$req['stern'];
    $vessParDetail->build=$req['build'];
    $vessParDetail->imo_no=$req['imo_no'];

    $vessParDetail->update();

    $data ="Vessel's Particular Details Info has been Updated successfully.";

    return array($data,$vessParDetail);
  }
}

public function storeVessFramDescription(VesselFrameworkAndDescriptionValidate $req){
  // return $req['vessel_id'];
  $check_vessel = Vessel::findOrFail($req['vessel_id']);

  if(empty($check_vessel->vesselFrameworkAndDetail)){

    $frame_des = new FrameworkDescription();
    $frame_des->vessel_id = $req['vessel_id'];
    $frame_des->bulk_no = $req['bulk_no'];
    $frame_des->length_stem_rudder = $req['length_stem_rudder'];
    $frame_des->main_breadth = $req['main_breadth'];
    $frame_des->dept_tonnag_ceil = $req['dept_tonnag_ceil'];
    $frame_des->eng_set_no = $req['eng_set_no'];
    $frame_des->shaft_no = $req['shaft_no'];
    $frame_des->loaded_pressure = $req['loaded_pressure'];
    $frame_des->gro_ton = $req['gro_ton'];
    $frame_des->net_ton = $req['net_ton'];
    $frame_des->cert_accom = $req['cert_accom'];
    $frame_des->lifeboat_num = $req['lifeboat_num'];
    $frame_des->rafts_num = $req['rafts_num'];
    $frame_des->per_accom_num = $req['per_accom_num'];
    $frame_des->rafts_req_num = $req['rafts_req_num'];
    $frame_des->buoys_num = $req['buoys_num'];
    $frame_des->jack_num = $req['jack_num'];
    $frame_des->imm_suit_num = $req['imm_suit_num'];
    $frame_des->therm_pro_num = $req['therm_pro_num'];
    $frame_des->trans_rud_num = $req['trans_rud_num'];
    $frame_des->propeller = $req['propeller'];

    $frame_des->save();

    $data ="Vessel's Framework And Description has been Updated successfully.";

    return array($data,$frame_des);
  }else{

    $frame_des = FrameworkDescription::where('vessel_id','=',$req['vessel_id'])->first();

    $frame_des->vessel_id = $req['vessel_id'];
    $frame_des->bulk_no = $req['bulk_no'];
    $frame_des->length_stem_rudder = $req['length_stem_rudder'];
    $frame_des->main_breadth = $req['main_breadth'];
    $frame_des->dept_tonnag_ceil = $req['bulk_no'];
    $frame_des->eng_set_no = $req['eng_set_no'];
    $frame_des->shaft_no = $req['shaft_no'];
    $frame_des->loaded_pressure = $req['loaded_pressure'];
    $frame_des->gro_ton = $req['gro_ton'];
    $frame_des->net_ton = $req['net_ton'];
    $frame_des->cert_accom = $req['cert_accom'];
    $frame_des->lifeboat_num = $req['lifeboat_num'];
    $frame_des->rafts_num = $req['rafts_num'];
    $frame_des->per_accom_num = $req['per_accom_num'];
    $frame_des->rafts_req_num = $req['rafts_req_num'];
    $frame_des->buoys_num = $req['buoys_num'];
    $frame_des->jack_num = $req['jack_num'];
    $frame_des->imm_suit_num = $req['imm_suit_num'];
    $frame_des->therm_pro_num = $req['therm_pro_num'];
    $frame_des->trans_rud_num = $req['trans_rud_num'];
    $frame_des->propeller = $req['propeller'];

    $frame_des->update();

    $data ="Vessel's Framework And Description has been Updated successfully.";

    return array($data,$frame_des);
  }
}

public function storeDimension(DimensionValidate $req){
  $check_vessel = Vessel::findOrFail($req['vessel_id']);

  if(empty($check_vessel->vesselDimension)){
    $dimen = new Dimension();
    $dimen->vessel_id = $req['vessel_id'];
    $dimen->length_LL = $req['length_LL'];
    $dimen->length_OA = $req['length_OA'];
    $dimen->breadth = $req['breadth'];
    $dimen->depth = $req['depth'];
    $dimen->length_eng_room = $req['length_eng_room'];
    $dimen->draft = $req['draft'];
    $dimen->suez_geo_ton = $req['suez_geo_ton'];
    $dimen->suez_net_ton = $req['suez_net_ton'];
    $dimen->pana_ton = $req['pana_ton'];
    $dimen->class = $req['class'];
    $dimen->class_not = $req['class_not'];
    $dimen->hp = $req['hp'];
    $dimen->spreed = $req['spreed'];
    $dimen->hold_cap = $req['hold_cap'];
    $dimen->car_gear = $req['car_gear'];
    $dimen->car_hold = $req['car_hold'];
    $dimen->bunk_cap = $req['bunk_cap'];
    $dimen->ball_cap = $req['ball_cap'];
    $dimen->water_cap = $req['water_cap'];

    $dimen->save();

    $data ="Vessel's Dimension Info has been Added successfully.";
    return array($data,$dimen);

  }else{
    $dimen = Dimension::where('vessel_id','=',$req['vessel_id'])->first();

    $dimen->vessel_id = $req['vessel_id'];
    $dimen->length_LL = $req['length_LL'];
    $dimen->length_OA = $req['length_OA'];
    $dimen->breadth = $req['breadth'];
    $dimen->depth = $req['depth'];
    $dimen->length_eng_room = $req['length_eng_room'];
    $dimen->draft = $req['draft'];
    $dimen->suez_geo_ton = $req['suez_geo_ton'];
    $dimen->suez_net_ton = $req['suez_net_ton'];
    $dimen->pana_ton = $req['pana_ton'];
    $dimen->class = $req['class'];
    $dimen->class_not = $req['class_not'];
    $dimen->hp = $req['hp'];
    $dimen->spreed = $req['spreed'];
    $dimen->hold_cap = $req['hold_cap'];
    $dimen->car_gear = $req['car_gear'];
    $dimen->car_hold = $req['car_hold'];
    $dimen->bunk_cap = $req['bunk_cap'];
    $dimen->ball_cap = $req['ball_cap'];
    $dimen->water_cap = $req['water_cap'];

    $dimen->update();

    $data ="Vessel's Dimension Info has been Updated successfully.";
    return array($data,$dimen);
  }
}
public function storeEngine(EngineValidate $req){
  $check_vessel = Vessel::findOrFail($req['vessel_id']);

  if(empty($check_vessel->vesselEngine)){
    $engine = new Engine();
    $engine->vessel_id = $req['vessel_id'];
    $engine->manu_name = $req['manu_name'];
    $engine->manu_address = $req['manu_address'];
    $engine->type = $req['type'];
    $engine->mod_num = $req['mod_num'];
    $engine->sets_no = $req['sets_no'];
    $engine->no_cyl_set = $req['no_cyl_set'];
    $engine->diam_cyl = $req['diam_cyl'];
    $engine->length_stroke = $req['length_stroke'];
    $engine->power_kw = $req['power_kw'];
    $engine->rpm = $req['rpm'];
    $engine->speed = $req['speed'];
    $engine->charger = $req['charger'];
    $engine->fuel = $req['fuel'];

    $engine->save();

    $data ="Vessel's Engine Info has been Added successfully.";
    return array($data,$engine);

  }else{
    $engine = Engine::where('vessel_id','=',$req['vessel_id'])->first();

    $engine->vessel_id = $req['vessel_id'];
    $engine->manu_name = $req['manu_name'];
    $engine->manu_address = $req['manu_address'];
    $engine->type = $req['type'];
    $engine->mod_num = $req['mod_num'];
    $engine->sets_no = $req['sets_no'];
    $engine->no_cyl_set = $req['no_cyl_set'];
    $engine->diam_cyl = $req['diam_cyl'];
    $engine->length_stroke = $req['length_stroke'];
    $engine->power_kw = $req['power_kw'];
    $engine->rpm = $req['rpm'];
    $engine->speed = $req['speed'];
    $engine->charger = $req['charger'];
    $engine->fuel = $req['fuel'];

    $engine->update();
    $data ="Vessel's Engine Info has been Updated successfully.";
    return array($data,$engine);
  }
}

public function storeBoiler(BoilerValidate $req){

  $check_vessel = Vessel::findOrFail($req['vessel_id']);

  if(empty($check_vessel->vesselBoiler)){
    $boiler = new Boiler();

    $boiler->vessel_id = $req['vessel_id'];
    $boiler->boiler_num = $req['boiler_num'];
    $boiler->manu_name = $req['manu_name'];
    $boiler->manu_address = $req['manu_address'];
    $boiler->loaded_pressure = $req['loaded_pressure'];
    $boiler->boiler_type = $req['boiler_type'];

    $boiler->save();

    $data ="Vessel's Boiler Info has been Added successfully.";
    return array($data,$boiler);
  }else{
    $boiler = Boiler::where('vessel_id','=',$req['vessel_id'])->first();

    $boiler->vessel_id = $req['vessel_id'];
    $boiler->boiler_num = $req['boiler_num'];
    $boiler->manu_name = $req['manu_name'];
    $boiler->manu_address = $req['manu_address'];
    $boiler->loaded_pressure = $req['loaded_pressure'];
    $boiler->boiler_type = $req['boiler_type'];

    $boiler->update();

    $data ="Vessel's Boiler Info has been Updated successfully.";
    return array($data,$boiler);
  }
}

public function storeGeninfo(VesselGenInfoValidate $req){
  $check_vessel = Vessel::findOrFail($req['vessel_id']);

  if(empty($check_vessel)){
    $genInfo=new Vessel;
    $genInfo->name=$req->vessel_name;
    $genInfo->owner_name=$req->owner_name;
    $genInfo->owner_address=$req->owner_address;
    $genInfo->manager_name=$req->manager_name;
    $genInfo->manager_address=$req->manager_address;
    $genInfo->master_name=$req->master_name;
    $genInfo->master_cert_no=$req->master_certificate_no;
    $genInfo->master_cert_validity=$req->master_certificate_validity;
    $genInfo->ch_eng_name=$req->cheif_engineer_name;
    $genInfo->ch_eng_cert_no=$req->cheif_engineer_certificate_no;
    $genInfo->ch_eng_cert_validity=$req->cheif_engineer_certificate_validity;
    $genInfo->prev_port_no=$req->prev_port_no;
    $genInfo->prev_reg_date=$req->prev_reg_date;
    $genInfo->save();
    $data ="Vessel's General Info has been saved successfully.";
    return array($data,$genInfo);
  }else{
    $genInfo = Vessel::findOrFail($req['vessel_id']);
    $genInfo->name=$req->vessel_name;
    $genInfo->owner_name=$req->owner_name;
    $genInfo->owner_address=$req->owner_address;
    $genInfo->manager_name=$req->manager_name;
    $genInfo->manager_address=$req->manager_address;
    $genInfo->master_name=$req->master_name;
    $genInfo->master_cert_no=$req->master_certificate_no;
    $genInfo->master_cert_validity=$req->master_certificate_validity;
    $genInfo->ch_eng_name=$req->cheif_engineer_name;
    $genInfo->ch_eng_cert_no=$req->cheif_engineer_certificate_no;
    $genInfo->ch_eng_cert_validity=$req->cheif_engineer_certificate_validity;
    $genInfo->prev_port_no=$req->prev_port_no;
    $genInfo->prev_reg_date=$req->prev_reg_date;

    $genInfo->update();

    $data ="Vessel's General Info has been Updated successfully.";
    return array($data,$genInfo);
  }

}

public function deleteVessel(Request $request){
  $vessel = Vessel::findOrFail($request->id);
  $vessel->status=false;
  $vessel->update();
  $vess_particular = VesselParticular::where('vessel_id',$request->id)->first();
  if(!empty($vess_particular)){
    $vess_particular->status=false;
    $vess_particular->update();
  }
  $vess_framework = FrameworkDescription::where('vessel_id',$request->id)->first();
  if(!empty($vess_framework)){
    $vess_framework->status=false;
    $vess_framework->update();
  }
  $vess_dimension = Dimension::where('vessel_id',$request->id)->first();
  if(!empty($vess_dimension)){
   $vess_dimension->status=false;
   $vess_dimension->update();
 }
 $vess_engine = Engine::where('vessel_id',$request->id)->first();
 if(!empty($vess_engine)){
   $vess_engine->status=false;
   $vess_engine->update();
 }
 $vess_boiler = Boiler::where('vessel_id',$request->id)->first();
 if(!empty($vess_boiler)){
   $vess_boiler->status=false;
   $vess_boiler->update();
 }
 $data ="Requested Vessel has been deleted successfully!";
 return array($data);
}
public function viewVesselDetail($id){
 $vessel=Vessel::findOrFail($id);
   // return $vessel->vesselDetail->type;
   // {{!empty($vessel->vesselDetail->type)?$vessel->vesselDetail->type:''}}
 return view('layouts.view-vessel-detail',compact('vessel'));
}
public function viewOrderDetail($id){
 $order=Order::findOrFail($id);
   // return $vessel->vesselDetail->type;
   // {{!empty($vessel->vesselDetail->type)?$vessel->vesselDetail->type:''}}
 return view('layouts.view-order-detail',compact('order'));
}

public function allTrash(){

 $orders =Order::orderBy('updated_at','desc')->where('ord_status',false)->get();;
 $categories =Category::orderBy('updated_at','desc')->where('status',false)->get();
 // $surveys =Survey::orderBy('updated_at','desc')->where('status',false)->get();
 $vessel_certificates =VesselCertificate::orderBy('updated_at','desc')->where('status',false)->get();
 $vessel_surveys =VesselSurvey::orderBy('updated_at','desc')->where('status',false)->get();
 $vessels =Vessel::orderBy('updated_at','desc')->where('status',false)->get();
 $items =Item::orderBy('updated_at','desc')->where('status',false)->get();
 $orderitems =OrderItem::orderBy('updated_at','desc')->where('status',false)->get();

 return view('layouts.trash-detail',compact(
  'orders',
  'categories',
  // 'surveys',
  'vessel_certificates',
  'vessel_surveys',
  'vessels',
  'items',
  'orderitems'
));
}

/**
 * Muntasir - Start
 */
private function restoreVessel($vessel_id){

  $vessel = Vessel::find($servey->vessel_id);
  $vessel->status  = true;
  $vessel->update();

  $vess_particular = VesselParticular::where('vessel_id',$vessel_id)->first();
  if(!empty($vess_particular)){
    $vess_particular->status=false;
    $vess_particular->update();
  }
  $vess_framework = FrameworkDescription::where('vessel_id',$vessel_id)->first();
  if(!empty($vess_framework)){
    $vess_framework->status=false;
    $vess_framework->update();
  }
  $vess_dimension = Dimension::where('vessel_id',$vessel_id)->first();
  if(!empty($vess_dimension)){
   $vess_dimension->status=false;
   $vess_dimension->update();
 }
 $vess_engine = Engine::where('vessel_id',$vessel_id)->first();
 if(!empty($vess_engine)){
   $vess_engine->status=false;
   $vess_engine->update();
 }
 $vess_boiler = Boiler::where('vessel_id',$vessel_id)->first();
 if(!empty($vess_boiler)){
   $vess_boiler->status=false;
   $vess_boiler->update();
 }
 return true;
}
public function restore(Request $r){
 if($r->type == 'survey'){
  $servey = VesselSurvey::find($r->id);
  $vessel = Vessel::find($servey->vessel_id);
  if(!$vessel->status){
    $this->restoreVessel($vessel->id);
  }
  $servey->status  = true;
  $servey->update();
  return response()->json(['url' => route('get.all.survey')]);
}
if($r->type == 'certificate'){

  $certificate = VesselCertificate::find($r->id);
  $vessel = Vessel::find($certificate->vessel_id);
  if(!$vessel->status){
    $this->restoreVessel($vessel->id);
  }
  $certificate->status  = true;
  $certificate->update();
  return response()->json(['url' => route('get.certificate')]);
}
if($r->type == 'order'){
  $order = Order::find($r->id);
  $vessel = Vessel::find($order->vessel_id);
  $category = Category::find($order->category_id);
  if(!$vessel->status){
    $this->restoreVessel($vessel->id);
  }
  if(!$category->status){
    $category->status  = true;
    $category->update();
  }
  $certificate->status  = true;
  $certificate->update();
  return response()->json(['url' => route('get.all.order')]);
}
if($r->type == 'item'){
  $item = Item::find($r->id);
  $category = Category::find($item->category_id);
  if(!$category->status){
    $category->status  = true;
    $category->update();
  }
  $item->status  = true;
  $item->update();
  return response()->json(['url' => route('get.all.item')]);
}

if($r->type == 'category'){
 $category = Category::find($r->id);
 $category->status  = true;
 $category->update();

 return response()->json(['url' => route('get.all.category')]);
}

if($r->type == 'vessel'){   
  $this->restoreVessel($r->id);
  return response()->json(['url' => route('get.vessels')]);
}
return response()->json(['error' => 'Please try again.']);
}
public function permanentDelete(Request $r){
 if($r->type == 'survey'){
  $servey = Survey::find($r->id);   
  $delete = $servey->delete();
}
if($r->type == 'certificate'){
  $certificate = Certificate::find($r->id);   
  $delete = $certificate->delete();
}
if($r->type == 'order'){
  $order = Order::find($r->id);   
  $delete = $order->delete();
}
if($r->type == 'item'){
  $item = Item::find($r->id);   
  $delete = $item->delete();
}
if($r->type == 'category'){
  $category = Category::find($r->id);   
  $delete = $category->delete();
}
if($r->type == 'vessel'){
  $vessel = Vessel::find($r->id);   
  $delete = $vessel->delete();
}
if(!$delete){
  return response()->json(['deleted' => false]);
}
return response()->json(['deleted' => true]);
}
/**
 * Muntasir - End
 */
public function getUser(){
 $data['vessels']=Vessel::orderBy('updated_at','desc')->where('status',true)->get();
 $data['roles']=Role::where('role','!=','super-admin')->where('status',true)->orderBy('created_at','desc')->get();
 return view('layouts.user',compact('data'));
}
public function storeUser(UserFormVal $request){

  $user= new User;
  $user->name= $request->User_Name;
  $user->email= $request->email;
  $user->password=Hash::make($request->password);
  $user->save();
  $role= new Role;
  $role->user_id= $user->id;
  if( $request->Vessel_Name!=0){
    $role->vessel_id= $request->Vessel_Name;
  }
  $role->role=$request->User_Role;
  $role->user_type=$request->user_type;
  $role->created_by=auth()->user()->name;
  $role->updated_by=auth()->user()->name;
  $role->save();
  $vessel_name=!empty($role->vessel->name)?$role->vessel->name:'';
  $data ="New user has been created successfully.";
  return array($data,$user,$role,$vessel_name);
}

public function deleteUser(Request $req){
  $user=User::findOrFail($req->id);
  $user->status=false;
  $user->update();
  $role=Role::findOrFail($user->role->id);
  $role->status=false;
  $role->update();
  $data ="Requested User has been deleted successfully!";
  return array($data);
}
public function getOneUser($id){
  $user = User::findOrFail($id);
  $role = Role::findOrFail($user->role->id);
  $vessel_name = !empty($role->vessel->name)?$role->vessel->name:'';
  return array ($user, $role, $vessel_name);
}
public function updateOneUser(updateUserFormVal $request){
  $user = User::findOrFail($request->user_id);
  $user->email=$request->email;
  $user->name=$request->User_Name;
  $user->update();
  $role = Role::findOrFail($user->role->id);
  $role->role = $request->User_Role;
  if( $request->Vessel_Name!=0){
    $role->vessel_id= $request->Vessel_Name;
  }else{
    $role->vessel_id=null;
  }
  $role->user_type=$request->user_type;
  $role->updated_by=auth()->user()->name;
  $role->update();
  $data ="Requested user has been updated successfully.";
  $vessel_name = !empty($role->vessel->name)?$role->vessel->name:'';
  return array ($data,$user, $role, $vessel_name);
}
public function addDelQty(Request $req){
  // return $req->all();
  $order= Order::findOrFail($req->orderId);
  $tempReq = null;
  $colname = '';
  if(!empty($req->deliver_qty && auth()->user()->role->role=='am-ssm')){
    $tempReq = $req->deliver_qty;
    $colname ='del_item_qty';
  }  
  elseif(!empty($req->rcv_qty  && auth()->user()->role->role=='second-engineer')){
    $tempReq=$req->rcv_qty;
    $colname ='rcv_item_qty';
  }  
  elseif(!empty($req->req_qty  && auth()->user()->role->role=='am-srd')){
    $tempReq=$req->req_qty;
    $colname ='item_qty';
  }
  foreach ($tempReq as $key => $value) {
    foreach ($order->orderItems as  $orderItem) {
     if($orderItem->id==$key){
      $orderitem=OrderItem::findOrFail($key);
      $orderitem->$colname=$value;
      $orderitem->update();
    }
  }
} 
if(auth()->user()->role->role=='am-ssm'){
  $data='Delivered Quantity Updated Successfully!';
}
elseif (auth()->user()->role->role=='second-engineer') {
  $data='Received Quantity Updated Successfully!';
}
elseif (auth()->user()->role->role=='am-srd') {
  $data='Required Quantity Updated Successfully!';
}
return array($data);
}
public function addsingleDelQty(Request $r){
  // return $r->all();
 $colname = '';
 if(auth()->user()->role->role=='am-ssm'){
  $colname ='del_item_qty';
  $data='Requested Delivered Quantity Updated Successfully!';
}elseif (auth()->user()->role->role=='second-engineer') {
 $colname ='rcv_item_qty';
 $data='Requested Received Quantity Updated Successfully!';
}elseif (auth()->user()->role->role=='am-srd') {
 $colname ='item_qty';
 $data='Requested Required Quantity Updated Successfully!';
}
$orderitem=OrderItem::findOrFail($r->itemId);
$orderitem->$colname=$r->itemValue;
$orderitem->update();

return array($data);
}
// public function deliverReq(){
//   $newObj=new RoleController;
//   $orders=$newObj->ssmAMApproved();
//   return view('layouts.order',compact('orders'));
// }
public function searchOrder(Request $req){
  $items =Item::orderBy('created_at','desc')->where('status',true)->get();
  $categories =Category::orderBy('created_at','desc')->where('status',true)->get();
  $vessels =Vessel::orderBy('created_at','desc')->where('status',true)->get();
  $ship_id=$req->ship_id;
  $cat_id=$req->cat_id;
  $item_id=$req->item_id;
  $dateBetween=null;
  $category=null;
  $from_date=null;
  $end_date=null;
  if(!empty($cat_id)){
    $category=Category::where('id',$cat_id)->first();
  }
  if($req->from_date!='' && $req->end_date==''){
   $from_date=$req->from_date;
 }
 if($req->from_date=='' && $req->end_date!=''){
  $end_date=$req->end_date;
}
if($req->from_date!='' && $req->end_date!=''){
  $dateBetween = array('from' =>$req->from_date,'to' => $req->end_date);
}
$orders = Order::where('ord_status',true)
->when($ship_id, function ($query, $ship_id) {
  return $query->where('vessel_id', $ship_id);
})
->when($cat_id, function ($query, $cat_id){
  return $query->where('category_id', $cat_id);
})
->when($dateBetween, function ($query, $dateBetween) {
  return $query->whereBetween('req_date', [$dateBetween['from'], $dateBetween['to']]);
})
->when($from_date, function ($query, $from_date) {
  return $query->whereDate('req_date', $from_date);
})
->when($end_date, function ($query, $end_date) {
  return $query->whereDate('req_date', $end_date);
})
->when($item_id, function ($query, $item_id) {
  return $query->whereHas('orderItems', function ($query1) use ($item_id) {
    return $query1->where('item_id', $item_id);
  })->orWhereDoesntHave('orderItems');

})
->orderBy('created_at','desc')
->get();
return view(!empty($item_id)?'layouts.order-item':'layouts.order',compact('orders','items','categories','vessels','item_id','ship_id','cat_id','from_date','end_date','category'));
}
public function getProfile(){
  $profile=User::findOrFail(auth()->id());
  return view('profile',compact('profile'));
}
public function changePassword(Request $request){
  $this->validate($request,[
    'current_password' => 'required',
    'new_password' => 'required|confirmed|min:6'
  ]);
  $user=User::findOrFail(auth()->user()->id);
  if(Hash::check($request->current_password, $user['password']) && $request->new_password==$request->new_password_confirmation){
    $user->password=bcrypt($request->new_password);
    $user->update();
    return back()->with('success','Password Changed Successfully!');
  }
  else{
    return back()->with('error','Old Password does not matched!');
  }
}
public function changeFile(Request $request){
  // return $request->all();
  if($request->photo=='' && $request->signature==''){
   return back()->with('warning','Photo & Signature required');
 }
 $user=User::findOrFail(auth()->user()->id);
 if($user->photo=='' && $user->sign==''){
  $this->validate($request,[
    'photo' => 'required',
    'signature' => 'required'
  ]);
}elseif($user->photo==''){
 $this->validate($request,[
  'photo' => 'required',
]);
}
elseif($user->sign==''){
  $this->validate($request,[
    'signature' => 'required',
  ]);
}
if($request->hasFile('photo')){
 $name ='images/userphoto/'. time() . '.' . $request->photo->getClientOriginalExtension();
 $request->photo->move(base_path('images/userphoto'), $name);
 $user->photo = $name;
}
if($request->hasFile('signature')){
 $name ='images/signature/'. time() . '.' . $request->signature->getClientOriginalExtension();
 $request->signature->move(base_path('images/signature'), $name);
 $user->sign = $name;
}
$user->update();
return back()->with('success','Photo & Signature Updated Successfully!');
}
public function deliverReqForAll(){
  $items =Item::orderBy('created_at','desc')->where('status',true)->get();
  $categories =Category::orderBy('created_at','desc')->where('status',true)->get();
  $vessels =Vessel::orderBy('created_at','desc')->where('status',true)->get();
  if(auth()->user()->role->user_type=='ship'){
   $orders=Order::
   where('ord_status',true)
			->whereHas('orderApproval', function($q){
				$q->where(function($query){
          $query->where('master_app','!=',null)
          ->orWhere('chief_eng_app','!=',null);
        }) 
         ->where('status','delivered')
         ->where('cheif_ofcr_app','!=',null)
         ->where('ord_status',true)
         ->where('ast_m_app','!=',null)
         ->where('agm_app','!=',null)
         ->where('gm_app','!=',null)
         ->where('dgm_app_ssm','!=',null)
         ->where('agm_app_ssm','!=',null)
         ->where('am_app_ssm','!=',null);
			})
   
   ->where('vessel_id', auth()->user()->role->vessel->id)
   ->orderBy('created_at','desc')
   ->get();
 }else{
  $orders=Order::
  where('ord_status',true)
  ->whereHas('orderApproval', function($q){
    $q->where(function($query){
      $query->where('master_app','!=',null)
      ->orWhere('chief_eng_app','!=',null);
    })
    ->where('status','delivered')
    ->where('cheif_ofcr_app','!=',null)
    ->where('ord_status',true)
    ->where('ast_m_app','!=',null)
    ->where('agm_app','!=',null)
    ->where('gm_app','!=',null)
    ->where('dgm_app_ssm','!=',null)
    ->where('agm_app_ssm','!=',null)
    ->where('am_app_ssm','!=',null);
  })
  ->orderBy('created_at','desc')
  ->get();
}
return view('layouts.order',compact('orders','items','categories','vessels'));
}
public function rcvReqForAll(){
  $items =Item::orderBy('created_at','desc')->where('status',true)->get();
  $categories =Category::orderBy('created_at','desc')->where('status',true)->get();
  $vessels =Vessel::orderBy('created_at','desc')->where('status',true)->get();
  if(auth()->user()->role->user_type=='ship'){
   $orders=Order::
   where('ord_status',true)
   ->whereHas('orderApproval', function($q){
     $q->where(function($query){
      $query->where('master_app','!=',null)
      ->orWhere('chief_eng_app','!=',null);
    }) 
     ->where('status','received')
     ->where('cheif_ofcr_app','!=',null)
     ->where('ord_status',true)
     ->where('ast_m_app','!=',null)
     ->where('agm_app','!=',null)
     ->where('gm_app','!=',null)
     ->where('dgm_app_ssm','!=',null)
     ->where('agm_app_ssm','!=',null)
     ->where('am_app_ssm','!=',null);
   })
   ->where('vessel_id', auth()->user()->role->vessel->id)
   ->orderBy('created_at','desc')
   ->get();
 }else{
  $orders=Order::
  where('ord_status',true)
  ->whereHas('orderApproval', function($q){
    $q->where(function($query){
      $query->where('master_app','!=',null)
      ->orWhere('chief_eng_app','!=',null);
    })
    ->where('status','received')
    ->where('cheif_ofcr_app','!=',null)
    ->where('ord_status',true)
    ->where('ast_m_app','!=',null)
    ->where('agm_app','!=',null)
    ->where('gm_app','!=',null)
    ->where('dgm_app_ssm','!=',null)
    ->where('agm_app_ssm','!=',null)
    ->where('am_app_ssm','!=',null);
  })
  ->orderBy('created_at','desc')
  ->get();
}
return view('layouts.order',compact('orders','items','categories','vessels'));
}
public function updateStatusByAM(Request $req){
  $order=Order::findOrFail($req->id);
  $order->status = $req->status;
  $order->update();
  $data ="Requested order status has been updated successfully!";
  return array($data);
}
public function searchSurvey(Request $r)
{
  $vessel_surveys=VesselSurvey::orderBy('created_at','desc')->where('status',true)->where('vessel_id',$r->ship_id)->get();
 $vessels=Vessel::orderBy('created_at','desc')->where('status',true)->get();
 $surveys=Survey::orderBy('created_at','desc')->where('status',true)->get();
 return view('layouts.survey',compact('vessel_surveys','vessels','surveys'))->with('ship_id',$r->ship_id);
}
public function searchCertificate(Request $r)
{
  $vessel_certificates=VesselCertificate::orderBy('created_at','desc')->where('status',true)->where('vessel_id',$r->ship_id)->get();
 $vessels=Vessel::orderBy('created_at','desc')->where('status',true)->get();
 $certificates=Certificate::orderBy('created_at','desc')->where('status',true)->get();
 return view('layouts.certificate',compact('vessel_certificates','vessels','certificates'))->with('ship_id',$r->ship_id);
}
}