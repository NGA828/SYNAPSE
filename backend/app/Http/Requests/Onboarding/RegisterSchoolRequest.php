<?php

namespace App\Http\Requests\Onboarding;

use Illuminate\Foundation\Http\FormRequest;

class RegisterSchoolRequest extends FormRequest
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
        return [
            'school.name' => ['required', 'string', 'max:255'],
            'school.slug' => ['required', 'string', 'max:255', 'unique:schools,slug'],
            'school.email' => ['nullable', 'email', 'max:255'],
            'school.phone' => ['nullable', 'string', 'max:50'],
            'school.address' => ['nullable', 'string', 'max:255'],
            'school.logo' => ['nullable', 'string', 'max:5000000'],
            'admin.name' => ['required', 'string', 'max:255'],
            'admin.email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'admin.password' => ['required', 'string', 'min:8'],
            'plan_id' => ['required', 'integer', 'exists:subscription_plans,id'],
        ];
    }
}
