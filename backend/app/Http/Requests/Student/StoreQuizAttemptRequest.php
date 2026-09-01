<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A submitted answer sheet.
 *
 * `answers` maps question id to the chosen option index. The key set is not
 * validated against the paper here — the service intersects it with the real
 * question ids, so an unknown or hostile key is ignored rather than trusted.
 */
class StoreQuizAttemptRequest extends FormRequest
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
            'answers' => ['required', 'array', 'min:1', 'max:'.config('synapse.quizzes.max_questions')],
            'answers.*' => ['nullable', 'integer', 'min:0', 'max:'.(config('synapse.quizzes.max_options') - 1)],
        ];
    }

    /**
     * The keys must be question ids, not arbitrary strings — a non-numeric key
     * would silently become an unmarked answer.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach (array_keys((array) $this->input('answers', [])) as $key) {
                if (! is_numeric($key)) {
                    $validator->errors()->add('answers', 'Answers must be keyed by question id.');

                    return;
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'answers.required' => 'Answer at least one question before submitting.',
        ];
    }
}
