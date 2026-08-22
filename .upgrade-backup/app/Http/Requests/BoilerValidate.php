<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BoilerValidate extends FormRequest
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
            'boiler_num'=>'nullable|string|max:100|min:1',  
            'manu_name'=>'nullable|string|max:100|min:1',   
            'manu_address'=>'nullable|string|max:100|min:1',    
            'loaded_pressure'=>'nullable|string|max:100|min:1', 
            'boiler_type'=>'nullable|string|max:100|min:1', 

        ];
    }
}
