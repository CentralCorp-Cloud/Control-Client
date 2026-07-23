<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApproveNodeEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-infrastructure') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'environment' => ['required', 'string', 'max:64'],
            'region' => ['nullable', 'string', 'max:80'],
            'agent_fqdn' => ['required', 'string', 'max:255', 'regex:/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i'],
            'agent_endpoint' => ['required', 'url:https', 'max:500'],
            'published_address' => ['nullable', 'ip'],
            'agent_channel' => ['required', Rule::in(['stable', 'beta'])],
            'agent_version' => ['required', 'regex:/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/', 'max:64'],
            'allowed_source_cidrs' => ['required', 'array', 'min:1', 'max:32'],
            'allowed_source_cidrs.*' => ['required', 'string', 'max:64', function (string $attribute, mixed $value, \Closure $fail): void {
                [$address, $bits] = array_pad(explode('/', (string) $value, 2), 2, null);
                $packed = @inet_pton($address);
                $maximum = $packed === false ? -1 : strlen($packed) * 8;
                if ($bits === null || ! ctype_digit($bits) || (int) $bits > $maximum) {
                    $fail('Le CIDR Control Plane est invalide.');
                }
            }],
            'initial_maintenance' => ['sometimes', 'boolean'],
            'maximum_deployments' => ['nullable', 'integer', 'between:1,10000'],
        ];
    }
}
