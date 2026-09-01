<?php

namespace App\Http\Requests\Analytics;

use App\Support\AtRiskSignal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AtRiskFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'severity' => ['nullable', 'string', Rule::in([
                AtRiskSignal::SEVERITY_WARNING,
                AtRiskSignal::SEVERITY_CRITICAL,
            ])],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
