<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Only the content of a homework is editable — never its target
 * class/subject/year, so a published item cannot be silently moved to a
 * different group of students.
 */
class UpdateHomeworkRequest extends FormRequest
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
            'instructions' => ['nullable', 'string', 'max:5000'],
            'max_score' => ['sometimes', 'required', 'integer', 'min:1', 'max:100'],
            'due_at' => ['sometimes', 'required', 'date'],

            // Additional brief documents may be attached when editing.
            'attachments' => ['nullable', 'array', 'max:'.config('synapse.attachments.max_per_record')],
            'attachments.*' => [
                'file',
                'max:'.(int) ceil(config('synapse.attachments.max_size', 10485760) / 1024),
                'mimes:'.implode(',', config('synapse.attachments.mimes')),
            ],
        ];
    }
}
