<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePlanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->user()?->can('manage-billing') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:100'], 'slug' => ['required', 'alpha_dash', 'max:100'], 'description' => ['nullable', 'string', 'max:2000'], 'is_free' => ['required', 'boolean'], 'price' => ['required', 'integer', 'min:0'], 'currency' => ['required', 'string', 'size:3'], 'billing_interval' => ['required', 'in:month,year'], 'stripe_product_id' => ['nullable', 'string', 'max:255'], 'stripe_price_id' => ['nullable', 'string', 'max:255'], 'memory_bytes' => ['required', 'integer', 'min:67108864'], 'cpu_limit' => ['required', 'numeric', 'gt:0'], 'maximum_projects' => ['nullable', 'integer', 'min:1', 'max:100'], 'active' => ['required', 'boolean']];
    }

    protected function prepareForValidation(): void
    {
        $free = $this->boolean('is_free');
        $this->merge([
            'is_free' => $free,
            'active' => $this->boolean('active'),
            'price' => $free ? 0 : $this->input('price'),
            'stripe_product_id' => $free ? null : $this->input('stripe_product_id'),
            'stripe_price_id' => $free ? null : $this->input('stripe_price_id'),
            'maximum_projects' => $free ? 1 : $this->input('maximum_projects'),
        ]);
    }
}
