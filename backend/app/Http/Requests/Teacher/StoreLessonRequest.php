<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;

class StoreLessonRequest extends FormRequest
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
            'topic' => ['nullable', 'string', 'max:120'],
            'summary' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string', 'max:50000'],
            'minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'sequence' => ['nullable', 'integer', 'min:0', 'max:9999'],

            // Slides, notes, worksheets — same limits as homework briefs.
            'attachments' => ['nullable', 'array', 'max:'.config('synapse.attachments.max_per_record')],
            'attachments.*' => [
                'file',
                'max:'.(int) ceil(config('synapse.attachments.max_size', 10485760) / 1024),
                'mimes:'.implode(',', config('synapse.attachments.mimes')),
            ],
        ];
    }
}
