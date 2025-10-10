<?php

namespace App\Http\Controllers\Backoffice\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UploadRequest extends FormRequest
{
    public function authorize() : bool {
        return Auth::check();
    }

    public function rules() : array {
        $type = $this->request->get('type');
        $maxsize = $this->request->get('maxsize', 8);
        $mimes = null;
        if ($type == 'images') {
            $mimes = [
                'image/jpeg',
                'image/png',
                'image/gif',
            ];
        }
        if ($type == 'file') {
            $mimes = [
                'image/jpeg',
                'image/png',
                'image/gif',
                'text/csv',
                'text/plain',
                'application/pdf',
                'application/pkcs7',
                'application/zip',
                'multipart/x-zip',
                'application/x-zip-compressed',
                'application/x-compressed',
                'multipart/x-zip',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/msword',
                'application/pkcs7-mime',
                'application/x-pkcs7-mime',
                'application/pkcs7-mime',
                'application/x-pkcs7-mime',
                'application/x-rar-compressed',
                'application/octet-stream',
                'application/rtf',
                'application/vnd.oasis.opendocument.text',
            ];
        }
        if ($type == 'pdf') {
            $mimes = [
                'application/pdf',
            ];
        }
        if ($type == 'xml') {
            $mimes = [
                'text/xml',
                'application/xml',
                'text/html',
                'application/xhtml+xml',
                'text/plain' // Alcuni server servono XML come text/plain
            ];
        }
        $rules[] = 'required';
        $rules[] = 'mimetypes:' . implode(',', $mimes);
        if ($maxsize > 0) {
            $rules[] = 'max:' . ($maxsize * 1024 );
        }
        if (is_array($this->request->get('file'))) {
            return [
                'file' => $rules,
            ];
        } else {
            return [
                'file.*' => $rules,
            ];
        }
    }

    public function messages() : array
    {
        return [
            'password.regex' => 'La password deve contenere almeno una lettera, almeno un numero e lunga tra 10 e 32 caratteri'
        ];
    }
}
