<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Only the content is editable — never the target class/subject/year, so a
 * published lesson cannot be silently moved to a different group of students.
 */
class UpdateLessonRequest extends FormRequest
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
            'title' => ['sometimes', 'required', 'string', 'max:180'],
            'topic' => ['nullable', 'string', 'max:120'],
            'summary' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string', 'max:50000'],
            'minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'sequence' => ['nullable', 'integer', 'min:0', 'max:9999'],

            'attachments' => ['nullable', 'array', 'max:'.config('synapse.attachments.max_per_record')],
            'attachments.*' => [
                'file',
                'max:'.(int) ceil(config('synapse.attachments.max_size', 10485760) / 1024),
                'mimes:'.implode(',', config('synapse.attachments.mimes')),
            ],
        ];
    }
}
