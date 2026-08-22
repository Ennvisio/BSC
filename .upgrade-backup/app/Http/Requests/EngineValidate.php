<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EngineValidate extends FormRequest
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
            'vessel_id' =>'required|integer',
            'manu_name' =>'nullable|string|max:100|min:1',
            'manu_address'=>'nullable|string|max:100|min:1',    
            'type'=>'nullable|string|max:100|min:1',    
            'mod_num'=>'nullable|string|max:100|min:1', 
            'sets_no'=>'nullable|string|max:100|min:1', 
            'no_cyl_set'=>'nullable|string|max:100|min:1',  
            'diam_cyl'=>'nullable|string|max:100|min:1',    
            'length_stroke'=>'nullable|string|max:100|min:1',
            'power_kw'=>'nullable|string|max:100|min:1',    
            'rpm'=>'nullable|string|max:100|min:1', 
            'speed'=>'nullable|string|max:100|min:1',
            'charger'=>'nullable|string|max:100|min:1', 
            'fuel'=>'nullable|string|max:100|min:1',
        ];
    }
}
