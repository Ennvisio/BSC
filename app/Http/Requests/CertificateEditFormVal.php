<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CertificateEditFormVal extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
     return [
         'Vessel_Name'=>'required',
         'category_id'=>'required|exists:certificate_categories,id',
         'title'=>'required|string|max:191',
         'Issuing_Authority'=>'nullable|string|max:100|min:2',
         'Issue_Date'=>'required|date',
         // Either of these is enough for a certificate that expires: give the
         // renewal period and the date is worked out (HomeController::expiryDateFor),
         // or give the date outright - which is how every record predating this
         // form was entered, and they still have to be editable.
         'Certificate_Expire_Date'=>['nullable', 'date'],
         'validity_years'=>['nullable', 'integer', 'min:1', 'max:50'],
         'is_permanent'=>['nullable', 'boolean'],
         'Certificate_Copy'=>'nullable|mimes:pdf|max:10000'
     ];
 }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->boolean('is_permanent')) {
                if ($this->filled('validity_years')) {
                    $validator->errors()->add('validity_years', 'A permanent certificate has no renewal cycle - leave this blank.');
                }

                return;
            }

            if (! $this->filled('validity_years') && ! $this->filled('Certificate_Expire_Date')) {
                $validator->errors()->add('validity_years', 'Give how many years it runs for, or an expiry date - or tick "No expiry (Permanent)".');
            }
        });
    }

    /** Field names as they read on the form, so errors don't say "category id". */
    public function attributes()
    {
        return [
            'category_id' => 'category',
            'title' => 'certificate title',
            'Issuing_Authority' => 'issuing authority',
            'Issue_Date' => 'issue date',
            'Certificate_Expire_Date' => 'expire date',
            'validity_years' => 'renewal period (years)',
            'Certificate_Copy' => 'certificate copy',
            'Vessel_Name' => 'vessel',
        ];
    }
}
