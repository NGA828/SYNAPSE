<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Editing a quiz. The question rules match StoreQuizRequest; the service
 * additionally refuses a question edit once the paper is locked.
 */
class UpdateQuizRequest extends FormRequest
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
        $maxOptions = config('synapse.quizzes.max_options');
        $minOptions = config('synapse.quizzes.min_options');

        return [
            'title' => ['sometimes', 'required', 'string', 'max:180'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'max_score' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'closes_at' => ['nullable', 'date', 'after:now'],
            'time_limit_minutes' => [
                'nullable', 'integer',
                'min:'.config('synapse.quizzes.min_time_limit'),
                'max:'.config('synapse.quizzes.max_time_limit'),
            ],
            'attempts_allowed' => ['nullable', 'integer', 'min:1', 'max:5'],

            'questions' => ['nullable', 'array', 'min:'.config('synapse.quizzes.min_questions'), 'max:'.config('synapse.quizzes.max_questions')],
            'questions.*.prompt' => ['required', 'string', 'max:2000'],
            'questions.*.options' => ['required', 'array', 'min:'.$minOptions, 'max:'.$maxOptions],
            'questions.*.options.*' => ['required', 'string', 'max:500'],
            'questions.*.correct_option' => ['required', 'integer', 'min:0', 'max:'.($maxOptions - 1)],
            'questions.*.points' => ['nullable', 'integer', 'min:1', 'max:100'],
            'questions.*.sequence' => ['nullable', 'integer', 'min:0', 'max:9999'],

            'attachments' => ['nullable', 'array', 'max:'.config('synapse.attachments.max_per_record')],
            'attachments.*' => [
                'file',
                'max:'.(int) ceil(config('synapse.attachments.max_size', 10485760) / 1024),
                'mimes:'.implode(',', config('synapse.attachments.mimes')),
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach ((array) $this->input('questions', []) as $index => $question) {
                $options = $question['options'] ?? [];
                $correct = $question['correct_option'] ?? null;

                if (! is_array($options) || $correct === null) {
                    continue;
                }

                if (! array_key_exists((int) $correct, $options)) {
                    $validator->errors()->add(
                        "questions.{$index}.correct_option",
                        'Select one of the listed options as the correct answer.',
                    );
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
            'closes_at.after' => 'The closing time must be in the future.',
        ];
    }
}
