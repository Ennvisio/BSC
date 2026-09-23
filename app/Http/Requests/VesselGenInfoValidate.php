<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
     * Normalised before validating so the value that's checked for uniqueness
     * is the value that gets stored - "joy " and "JOY" are the same acronym.
     */
    protected function prepareForValidation()
    {
        if ($this->has('vessel_acronym')) {
            $this->merge(['vessel_acronym' => strtoupper(trim((string) $this->vessel_acronym))]);
        }
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
         // Prefixes every requisition number (JOY/DK/STR/08/2026). vessel_id is
         // only present when editing - ignoring it lets a vessel keep its own.
         'vessel_acronym'=>['required','alpha_num','min:2','max:5',Rule::unique('vessels','acronym')->ignore($this->input('vessel_id'))],
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
        'vessel_acronym.unique' => 'That acronym is already used by another vessel.',
        'vessel_acronym.alpha_num' => 'The vessel acronym may only contain letters and numbers.',
    ];
}
}
