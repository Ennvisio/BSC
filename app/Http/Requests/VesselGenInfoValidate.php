<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VesselGenInfoValidate extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
         'vessel_name'=>'required|string|max:100|min:1',
         'owner_name'=>'required|string|max:100|min:1',
         'owner_address'=>'required|string|max:300|min:1',
         'manager_name'=>'required|string|max:100|min:1',
         'manager_address'=>'required|string|max:300|min:1',
         'master_name'=>'required|string|max:100|min:1',
         'master_certificate_no'=>'required|string|max:100|min:1',
         'master_certificate_validity'=>'required|date',
         'cheif_engineer_name'=>'required|string|max:100|min:1',
         'cheif_engineer_certificate_no'=>'required|string|max:100|min:1',
         'cheif_engineer_certificate_validity'=>'required|date'
     ];
 }
 public function messages(){
    return [  
        //
    ];
}
}
