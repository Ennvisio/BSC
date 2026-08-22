<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DimensionValidate extends FormRequest
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
            'vessel_id'=>'required|integer',
            'length_LL'=>'nullable|string|max:100|min:1',   
            'length_OA'=>'nullable|string|max:100|min:1',   
            'breadth'=>'nullable|string|max:100|min:1', 
            'depth'=>'nullable|string|max:100|min:1',   
            'length_eng_room'=>'nullable|string|max:100|min:1', 
            'draft'=>'nullable|string|max:100|min:1',   
            'suez_geo_ton'=>'nullable|string|max:100|min:1',    
            'suez_net_ton'=>'nullable|string|max:100|min:1',    
            'pana_ton'=>'nullable|string|max:100|min:1',    
            'class'=>'nullable|string|max:100|min:1',   
            'class_not'=>'nullable|string|max:100|min:1',   
            'hp'=>'nullable|string|max:100|min:1',  
            'spreed'=>'nullable|string|max:100|min:1',  
            'hold_cap'=>'nullable|string|max:100|min:1',    
            'car_gear'=>'nullable|string|max:100|min:1',    
            'car_hold'=>'nullable|string|max:100|min:1',    
            'bunk_cap'=>'nullable|string|max:100|min:1',    
            'ball_cap'=>'nullable|string|max:100|min:1',    
            'water_cap'=>'nullable|string|max:100|min:1',
        ];
    }
}
