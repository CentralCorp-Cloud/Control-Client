<?php

namespace App\Http\Requests\Enrollment;

use App\Enums\NodeEnrollmentStep;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'step' => ['required', Rule::enum(NodeEnrollmentStep::class)],
            'percentage' => ['required', 'integer', 'between:0,100'],
            'message' => ['required', 'string', 'max:500'],
            'error_code' => ['nullable', 'string', 'max:64', 'regex:/^[A-Z0-9_]+$/'],
            'error_message' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
