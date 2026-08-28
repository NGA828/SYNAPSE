<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreStudentRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            // Optional: when omitted the platform generates a one-time
            // password and e-mails/SMSs it to the new user.
            'password' => ['nullable', 'string', 'min:8'],
            'phone' => ['nullable', 'string', 'max:32'],
            'matricule' => ['required', 'string', 'max:50', 'unique:students,matricule'],
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
        ];
    }
}
