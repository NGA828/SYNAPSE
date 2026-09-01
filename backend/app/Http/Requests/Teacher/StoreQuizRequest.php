<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuizRequest extends FormRequest
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
        $maxQuestions = config('synapse.quizzes.max_questions');
        $maxOptions = config('synapse.quizzes.max_options');
        $minOptions = config('synapse.quizzes.min_options');

        return [
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
            'title' => ['required', 'string', 'max:180'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'max_score' => ['required', 'integer', 'min:1', 'max:100'],
            'closes_at' => ['nullable', 'date', 'after:now'],
            'time_limit_minutes' => [
                'nullable', 'integer',
                'min:'.config('synapse.quizzes.min_time_limit'),
                'max:'.config('synapse.quizzes.max_time_limit'),
            ],
            'attempts_allowed' => ['nullable', 'integer', 'min:1', 'max:5'],

            // The whole paper arrives in one request so a teacher never ends up
            // with a half-built quiz they cannot publish.
            'questions' => ['nullable', 'array', 'min:'.config('synapse.quizzes.min_questions'), 'max:'.$maxQuestions],
            'questions.*.prompt' => ['required', 'string', 'max:2000'],
            'questions.*.options' => ['required', 'array', 'min:'.$minOptions, 'max:'.$maxOptions],
            'questions.*.options.*' => ['required', 'string', 'max:500'],
            // An index, not the answer text — see the migration.
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

    /**
     * The answer key must point at an option that actually exists. Laravel
     * cannot express "less than the length of a sibling array" as a rule, so
     * it is checked here.
     */
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
            'questions.*.options.min' => 'Each question needs at least '.config('synapse.quizzes.min_options').' options.',
        ];
    }
}
