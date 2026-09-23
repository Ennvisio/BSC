<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Creating/renaming a certificate category. Super-admin's own list - see
 * HomeController::storeCertificateCategory.
 */
class CertificateCategoryFormValidate extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => [
                'required', 'string', 'max:191',
                Rule::unique('certificate_categories', 'name')->ignore($this->input('category_id')),
            ],
        ];
    }
}
