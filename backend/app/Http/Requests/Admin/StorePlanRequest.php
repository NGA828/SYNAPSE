<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StorePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $plan = $this->route('plan');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:subscription_plans,slug'.($plan ? ','.$plan->id : '')],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0'],
            'billing_interval' => ['required', 'string', 'in:monthly,yearly'],
            'currency' => ['required', 'string', 'max:10'],
            'max_students' => ['nullable', 'integer', 'min:0'],
            'max_teachers' => ['nullable', 'integer', 'min:0'],
            'max_classes' => ['nullable', 'integer', 'min:0'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string'],
            'status' => ['sometimes', 'string', 'in:active,archived'],
        ];
    }
}
