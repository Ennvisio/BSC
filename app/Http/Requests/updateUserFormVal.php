<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class updateUserFormVal extends FormRequest
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
            'User_Name' => 'required|string|max:255',
            'Vessel_Name' => 'required',
            'User_Role' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,'.$_POST['user_id'].',id',
            // Optional: editing a user's details should never force a
            // password change. 'confirmed' only fires validation when
            // 'password' is actually present, so leaving both blank passes.
            'password' => 'nullable|string|min:6|confirmed',
        ];
    }
}
