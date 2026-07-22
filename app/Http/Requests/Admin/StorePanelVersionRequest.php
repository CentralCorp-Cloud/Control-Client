<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePanelVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-infrastructure') === true;
    }

    public function rules(): array
    {
        $id = $this->route('panel_version')?->id;

        return [
            'version' => ['required', 'string', 'max:80', Rule::unique('panel_versions')->ignore($id)],
            'image_reference' => ['required', 'string', 'max:255', 'regex:/^ghcr\.io\/centralcorp\/centralpanel@sha256:[a-f0-9]{64}$/', Rule::unique('panel_versions')->ignore($id)],
            'active' => ['nullable', 'boolean'],
            'recommended' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['active' => $this->boolean('active'), 'recommended' => $this->boolean('recommended')]);
    }
}
