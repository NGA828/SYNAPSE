<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSchoolRequest extends FormRequest
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
        $school = $this->route('school');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'unique:schools,slug,'.$school->id],
            'code' => ['nullable', 'string', 'max:50', 'unique:schools,code,'.$school->id],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'in:active,trial,suspended,expired'],
            'timezone' => ['nullable', 'string', 'max:100'],
            'primary_color' => ['nullable', 'string', 'max:20'],
        ];
    }
}
