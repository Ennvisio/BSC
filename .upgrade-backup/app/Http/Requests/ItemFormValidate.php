<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ItemFormValidate extends FormRequest
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
         'Item_Name'=>'required|string|max:255|min:4',
         'Impa_Code_No'=>'required|string|max:255',
         'Measurement_Unit'=>'required|string|max:50',
         'Category_Name'=>'required|numeric'
     ];
 }
}
