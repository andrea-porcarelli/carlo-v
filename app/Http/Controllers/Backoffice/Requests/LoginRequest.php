<?php

namespace App\Http\Controllers\Backoffice\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize() : bool {
        return true;
    }

    public function rules() : array {
        return [
            'email' => ['required', 'email:rfc', 'exists:users'],
            'password' => ['required'],
        ];
    }
}
