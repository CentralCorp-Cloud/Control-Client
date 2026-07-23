<?php

namespace App\Http\Requests\Enrollment;

use Illuminate\Foundation\Http\FormRequest;

class CertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['csr' => ['required', 'string', 'max:65536', 'starts_with:-----BEGIN CERTIFICATE REQUEST-----']];
    }
}
