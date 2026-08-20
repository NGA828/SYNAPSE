<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreGradeComponentRequest extends FormRequest
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
            'weight' => ['required', 'numeric', 'min:0', 'max:100'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
        ];
    }
}
