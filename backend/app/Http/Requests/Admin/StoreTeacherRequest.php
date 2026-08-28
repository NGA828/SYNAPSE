<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreTeacherRequest extends FormRequest
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
            'staff_no' => ['nullable', 'string', 'max:50', 'unique:teachers,staff_no'],
        ];
    }
}
