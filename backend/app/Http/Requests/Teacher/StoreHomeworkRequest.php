<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;

class StoreHomeworkRequest extends FormRequest
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
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
            'title' => ['required', 'string', 'max:180'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            // 20 is the Francophone default; 100 covers the Anglophone scale.
            'max_score' => ['required', 'integer', 'min:1', 'max:100'],
            'due_at' => ['required', 'date', 'after:now'],

            // Optional brief document(s) for the class to download. The form
            // posts multipart when files are chosen; these rules are identical
            // either way, so JSON callers keep working.
            'attachments' => ['nullable', 'array', 'max:'.config('synapse.attachments.max_per_record')],
            'attachments.*' => [
                'file',
                'max:'.(int) ceil(config('synapse.attachments.max_size', 10485760) / 1024),
                'mimes:'.implode(',', config('synapse.attachments.mimes')),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'due_at.after' => 'The deadline must be in the future.',
        ];
    }
}
