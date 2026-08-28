<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreImportRequest extends FormRequest
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
            'type' => ['required', 'string', 'in:students,teachers'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.name' => ['required', 'string', 'max:255'],
            'rows.*.email' => ['required', 'email', 'max:255'],
            'rows.*.matricule' => ['nullable', 'string', 'max:50'],
            'rows.*.staff_no' => ['nullable', 'string', 'max:50'],
            'rows.*.class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'rows.*.password' => ['nullable', 'string', 'min:8'],
            'rows.*.phone' => ['nullable', 'string', 'max:32'],
        ];
    }
}
