<?php

namespace App\Notifications;

use App\Models\Quiz;

class QuizPublishedNotification extends SynapseNotification
{
    public function __construct(
        public readonly Quiz $quiz,
    ) {
        parent::__construct();
    }

    public function type(): string
    {
        return 'quiz_published';
    }

    public function title(mixed $notifiable): string
    {
        return 'New quiz: '.$this->quiz->title;
    }

    public function body(mixed $notifiable): string
    {
        $questions = $this->quiz->questions_count ?? $this->quiz->questions()->count();

        $parts = ["New {$this->quiz->subject?->name} quiz for {$this->quiz->schoolClass?->name}"];

        $parts[] = "{$questions} question".($questions === 1 ? '' : 's');

        if ($this->quiz->time_limit_minutes) {
            $parts[] = "{$this->quiz->time_limit_minutes} minutes";
        }

        if ($this->quiz->closes_at) {
            $parts[] = 'closes '.$this->quiz->closes_at->format('j M, H:i');
        }

        return implode(' · ', $parts).'.';
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(mixed $notifiable): array
    {
        return ['quiz_id' => $this->quiz->id];
    }

    public function actionUrl(mixed $notifiable): ?string
    {
        return $this->spa('/student/quizzes');
    }
}
