<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SurveyFormValidate extends FormRequest
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
            'Vessel_Name'=>'required',
            'Survey_Name'=>'required',
            'Survey_Society'=>'required|string|max:100|min:2',
            'Survey_Date'=>'required|date',
            'Survey_Expire_Date'=>'required|date'
        ];
    }
}
