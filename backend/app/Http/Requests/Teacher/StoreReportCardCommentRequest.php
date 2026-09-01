<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;

class StoreReportCardCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'body' => [
                'required',
                'string',
                'max:'.(int) config('synapse.comments.max_length', 400),
            ],

            // Optional: a null subject_id means the overall comment on the card.
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'semester_id' => ['nullable', 'integer', 'exists:semesters,id'],

            /*
            | Locking is what makes a comment final. It is a separate, explicit
            | flag rather than implied by saving, because a teacher drafts and
            | re-reads before committing text to a PDF.
            */
            'lock' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('lock') && ! is_bool($this->input('lock'))) {
            $this->merge(['lock' => filter_var($this->input('lock'), FILTER_VALIDATE_BOOLEAN)]);
        }
    }
}
