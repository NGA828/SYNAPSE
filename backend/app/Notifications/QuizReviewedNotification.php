<?php

namespace App\Notifications;

use App\Models\QuizAttempt;

class QuizReviewedNotification extends SynapseNotification
{
    public function __construct(
        public readonly QuizAttempt $attempt,
    ) {
        parent::__construct();
    }

    public function type(): string
    {
        return 'quiz_reviewed';
    }

    public function title(mixed $notifiable): string
    {
        return 'Your quiz result is ready';
    }

    public function body(mixed $notifiable): string
    {
        $quiz = $this->attempt->quiz;

        return "\"{$quiz?->title}\" was marked "
            .number_format((float) $this->attempt->score, 2)
            ."/{$quiz?->max_score} ({$this->attempt->correct_count}/{$this->attempt->total_questions} correct).";
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(mixed $notifiable): array
    {
        return [
            'quiz_id' => $this->attempt->quiz_id,
            'attempt_id' => $this->attempt->id,
            'score' => $this->attempt->score,
        ];
    }

    public function actionUrl(mixed $notifiable): ?string
    {
        return $this->spa('/student/quizzes');
    }

    /**
     * @return array<int, string>
     */
    public function channels(): array
    {
        return ['bell', 'mail'];
    }
}
