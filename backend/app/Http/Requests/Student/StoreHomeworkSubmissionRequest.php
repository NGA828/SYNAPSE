<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreHomeworkSubmissionRequest extends FormRequest
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
            // Either an answer or an attached file is required; `withValidator`
            // below enforces the "at least one" rule.
            'content' => ['nullable', 'string', 'max:20000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => [
                'file',
                'max:'.(int) ceil(config('synapse.attachments.max_size', 10485760) / 1024),
                'mimes:'.implode(',', config('synapse.attachments.mimes')),
            ],
        ];
    }

    /**
     * A blank submission is not a submission: require text, a file, or both.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (blank($this->input('content')) && ! $this->hasFile('attachments')) {
                $validator->errors()->add(
                    'content',
                    'Write your answer or attach a file before submitting.',
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'attachments.*.mimes' => 'Allowed file types: '
                .implode(', ', config('synapse.attachments.mimes')).'.',
            'attachments.*.max' => 'Each file must be 10 MB or smaller.',
        ];
    }
}
