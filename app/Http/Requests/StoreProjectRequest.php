<?php

namespace App\Http\Requests;

use App\Enums\DomainMode;
use App\Models\Deployment;
use App\Models\Plan;
use App\Models\Project;
use App\Services\DomainNameService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProjectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')->where('active', true)],
            'domain_mode' => ['required', Rule::enum(DomainMode::class)],
            'central_subdomain' => ['nullable', 'string', 'max:63'],
            'custom_url' => ['nullable', 'string', 'max:253'],
            'admin_email' => ['required', 'email:rfc', 'max:255'],
            'admin_password' => ['required', 'string', 'min:12', 'max:4096', 'confirmed'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'domain_mode' => strtoupper((string) $this->input('domain_mode', DomainMode::CentralCloud->value)),
            'central_subdomain' => DomainNameService::normalizeLabel($this->input('central_subdomain') ?: Str::slug((string) $this->input('name'))),
            'custom_url' => DomainNameService::normalizeCustomInput($this->input('custom_url')),
        ]);
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $plan = Plan::query()->where('active', true)->find($this->integer('plan_id'));
            if (! $plan) {
                return;
            }

            $mode = $this->string('domain_mode')->toString();
            if ($plan->is_free && $mode !== DomainMode::CentralCloud->value) {
                $validator->errors()->add('domain_mode', 'Le plan gratuit utilise obligatoirement un domaine CentralCloud.');

                return;
            }

            if ($mode === DomainMode::CentralCloud->value) {
                $label = $this->input('central_subdomain');
                if (! DomainNameService::isValidLabel($label)) {
                    $validator->errors()->add('central_subdomain', 'Choisissez un sous-domaine valide, non réservé, avec lettres, chiffres ou tirets.');

                    return;
                }
                $hostname = DomainNameService::centralHostname($label);
                if (Project::withTrashed()->where('canonical_hostname', $hostname)->exists() || Deployment::withTrashed()->where('hostname', $hostname)->exists()) {
                    $validator->errors()->add('central_subdomain', 'Ce sous-domaine CentralCloud est déjà utilisé.');
                }

                return;
            }

            $hostname = $this->input('custom_url');
            $suffix = DomainNameService::suffix();
            if (! DomainNameService::isValidHostname($hostname) || $hostname === $suffix || str_ends_with((string) $hostname, '.'.$suffix)) {
                $validator->errors()->add('custom_url', 'Saisissez un sous-domaine personnalisé valide, par exemple panel.example.com.');

                return;
            }
            if (Project::withTrashed()->where('custom_hostname', $hostname)->exists()) {
                $validator->errors()->add('custom_url', 'Ce domaine personnalisé est déjà associé à un projet.');
            }
        }];
    }
}
