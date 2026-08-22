<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VesselParticularDetailValidate extends FormRequest
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
            'type'=>'nullable|string|max:100|min:1',
            'flag'=>'nullable|string|max:100|min:1',
            'call_sign'=>'nullable|string|max:100|min:1',
            'imo_no'=>'nullable|string|max:100|min:1',
            'grt'=>'nullable|string|max:100|min:1',
            'nrt'=>'nullable|string|max:100|min:1',
            'dwt'=>'nullable|string|max:100|min:1',
            'off_no'=>'nullable|string|max:100|min:1',
            'keel_lay_date'=>'nullable|date',
            'launch_date'=>'nullable|date',
            'delivery_date'=>'nullable|date',
            'cert_date'=>'nullable|date',
            'built_year'=>'nullable|string|max:100|min:1',
            'built_loc'=>'nullable|string|max:100|min:1',
            'steam_motor_propelled'=>'nullable|string|max:100|min:1',
            'builder_name'=>'nullable|string|max:100|min:1',
            'builder_address'=>'nullable|string|max:100|min:1',
            'deck_no'=>'nullable|string|max:100|min:1',
            'mast_no'=>'nullable|string|max:100|min:1',
            'rigged'=>'nullable|string|max:100|min:1',
            'stem'=>'nullable|string|max:100|min:1',
            'stern'=>'nullable|string|max:100|min:1',
            'build'=>'nullable|string|max:100|min:1',
        ];
    }
}
