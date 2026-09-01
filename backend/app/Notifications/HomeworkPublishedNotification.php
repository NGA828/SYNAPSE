<?php

namespace App\Notifications;

use App\Models\HomeworkAssignment;

class HomeworkPublishedNotification extends SynapseNotification
{
    public function __construct(
        public readonly HomeworkAssignment $homework,
    ) {
        parent::__construct();
    }

    public function type(): string
    {
        return 'homework_published';
    }

    public function title(mixed $notifiable): string
    {
        return 'New homework: '.$this->homework->title;
    }

    public function body(mixed $notifiable): string
    {
        return "New {$this->homework->subject?->name} homework for "
            .$this->homework->schoolClass?->name
            .", due {$this->homework->due_at?->format('d M Y \a\t H:i')}.";
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(mixed $notifiable): array
    {
        return ['homework_id' => $this->homework->id];
    }

    public function actionUrl(mixed $notifiable): ?string
    {
        return $this->spa('/student/homework');
    }
}
