<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;

class StoreGradesRequest extends FormRequest
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
            'grades' => ['required', 'array', 'min:1'],
            'grades.*.student_id' => ['required', 'integer', 'exists:students,id'],
            'grades.*.test1' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'grades.*.test2' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'grades.*.exam' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'grades.*.scores' => ['nullable', 'array'],
            'grades.*.scores.*.component_id' => ['required', 'integer', 'exists:grade_components,id'],
            'grades.*.scores.*.score' => ['nullable', 'numeric', 'min:0', 'max:20'],
        ];
    }
}
