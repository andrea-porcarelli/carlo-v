<?php

namespace App\Http\Controllers\Backoffice\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreDishRequest extends FormRequest
{
    public function authorize() : bool {
        return Auth::check() && Auth::user()->role == 'admin';
    }

    public function rules() : array {
        return [
            'label' => 'required|string',
            'price' => 'required|string',
            'category_id' => 'required|int|exists:categories,id',
        ];
    }

    public function messages() : array
    {
        return [
            'password.regex' => 'La password deve contenere almeno una lettera, almeno un numero e lunga tra 10 e 32 caratteri'
        ];
    }
}
