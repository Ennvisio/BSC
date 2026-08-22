<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OrderFormValidate extends FormRequest
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
            'Port_Name'=>'required|string|max:255|min:4',
            'Requisition_Date'=>'required|date',
            'Vessel_Name'=>'required',
            'Requisition_No'=>'required|string'
        ];
    }
}
