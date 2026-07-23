<?php

namespace App\Http\Requests\Enrollment;

use Illuminate\Foundation\Http\FormRequest;

class CompletionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'agent_identity' => ['required', 'uuid'],
            'agent_version' => ['required', 'string', 'max:64'],
            'protocol_version' => ['required', 'string', 'max:32'],
            'services' => ['required', 'array', 'max:16'],
            'services.*' => ['in:ok,error'],
            'services.docker' => ['required', 'in:ok,error'],
            'services.postgresql' => ['required', 'in:ok,error'],
            'services.traefik' => ['required', 'in:ok,error'],
            'services.agent' => ['required', 'in:ok,error'],
            'healthcheck' => ['required', 'in:ok,error'],
            'resources' => ['required', 'array'],
            'resources.memory_bytes' => ['required', 'integer', 'min:1'],
            'resources.disk_bytes' => ['required', 'integer', 'min:1'],
            'validations' => ['required', 'array', 'min:1', 'max:128'],
            'validations.*.name' => ['required', 'string', 'max:64'],
            'validations.*.status' => ['required', 'in:ok,error'],
        ];
    }
}
