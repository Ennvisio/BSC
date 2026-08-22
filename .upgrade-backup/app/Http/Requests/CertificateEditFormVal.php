<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CertificateEditFormVal extends FormRequest
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
         'Certificate_Name'=>'required',
         'Issuing_Authority'=>'required|string|max:100|min:2',
         'Issue_Date'=>'required|date',
         'Certificate_Expire_Date'=>'required|date',
         'Certificate_Copy'=>'mimes:pdf|max:10000'
     ];
 }
}
