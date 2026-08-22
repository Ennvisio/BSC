<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VesselFrameworkAndDescriptionValidate extends FormRequest
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

            'bulk_no'=>'nullable|string|max:100|min:1',

            'length_stem_rudder'=>'nullable|string|max:100|min:1',

            'main_breadth'=>'nullable|string|max:100|min:1',

            'dept_tonnag_ceil'=>'nullable|string|max:100|min:1',

            'eng_set_no'=>'nullable|string|max:100|min:1',

            'shaft_no'=>'nullable|string|max:100|min:1',


            'loaded_pressure'=>'nullable|string|max:100|min:1',

            'gro_ton'=>'nullable|string|max:100|min:1',

            'net_ton'=>'nullable|string|max:100|min:1',

            'cert_accom'=>'nullable|string|max:100|min:1',

            'lifeboat_num'=>'nullable|string|max:100|min:1',

            'rafts_num'=>'nullable|string|max:100|min:1',

            'per_accom_num'=>'nullable|string|max:100|min:1',

            'rafts_req_num'=>'nullable|string|max:100|min:1',

            'buoys_num'=>'nullable|string|max:100|min:1',

            'jack_num'=>'nullable|string|max:100|min:1',

            'imm_suit_num'=>'nullable|string|max:100|min:1',

            'therm_pro_num'=>'nullable|string|max:100|min:1',

            'trans_rud_num'=>'nullable|string|max:100|min:1',

            'propeller'=>'nullable|string|max:100|min:1',

        ];
    }
}
