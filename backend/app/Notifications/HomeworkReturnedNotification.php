<?php

namespace App\Notifications;

use App\Models\HomeworkSubmission;

class HomeworkReturnedNotification extends SynapseNotification
{
    public function __construct(
        public readonly HomeworkSubmission $submission,
    ) {
        parent::__construct();
    }

    public function type(): string
    {
        return 'homework_graded';
    }

    public function title(mixed $notifiable): string
    {
        return 'Your homework has been graded';
    }

    public function body(mixed $notifiable): string
    {
        $homework = $this->submission->homework;

        return "\"{$homework?->title}\" was marked "
            .number_format((float) $this->submission->score, 2)
            ."/{$homework?->max_score}.";
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(mixed $notifiable): array
    {
        return [
            'homework_id' => $this->submission->homework_assignment_id,
            'score' => $this->submission->score,
        ];
    }

    public function actionUrl(mixed $notifiable): ?string
    {
        return $this->spa('/student/homework');
    }

    /**
     * @return array<int, string>
     */
    public function channels(): array
    {
        return ['bell', 'mail'];
    }
}
