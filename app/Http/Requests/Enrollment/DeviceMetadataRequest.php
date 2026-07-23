<?php

namespace App\Http\Requests\Enrollment;

use Illuminate\Foundation\Http\FormRequest;

class DeviceMetadataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hostname' => ['required', 'string', 'max:255'],
            'os' => ['required', 'in:debian,ubuntu'],
            'os_version' => ['required', 'string', 'max:32'],
            'architecture' => ['required', 'in:amd64,arm64'],
            'memory_bytes' => ['required', 'integer', 'min:0'],
            'disk_bytes' => ['required', 'integer', 'min:0'],
            'installer_version' => ['required', 'regex:/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/', 'max:64'],
            'nonce' => ['required', 'string', 'min:16', 'max:128'],
            'requested_channel' => ['sometimes', 'in:stable,beta'],
            'capabilities' => ['sometimes', 'array', 'max:32'],
            'capabilities.*' => ['string', 'max:64', 'distinct'],
            'ip_addresses' => ['sometimes', 'array', 'max:32'],
            'ip_addresses.*' => ['ip'],
        ];
    }
}
