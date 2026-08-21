<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeacherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $teacher = $this->route('teacher');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($teacher?->user_id)],
            'password' => ['nullable', 'string', 'min:8'],
            'staff_no' => ['nullable', 'string', 'max:50', Rule::unique('teachers', 'staff_no')->ignore($teacher?->id)],
        ];
    }
}