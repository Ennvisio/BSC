<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CategoryFormValidate extends FormRequest
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
             'name' => 'required|string|unique:categories|max:150',
             'symbol' => 'required|string|unique:categories|max:150',
        ];
    }
     public function messages(){
        return [
             'name.unique' => 'The Category Name is already exist',
             'symbol.unique' => 'The Category Symbol is already exist',
        ];
     }
}
