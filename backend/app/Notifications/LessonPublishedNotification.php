<?php

namespace App\Notifications;

use App\Models\Lesson;

class LessonPublishedNotification extends SynapseNotification
{
    public function __construct(
        public readonly Lesson $lesson,
    ) {
        parent::__construct();
    }

    public function type(): string
    {
        return 'lesson_published';
    }

    public function title(mixed $notifiable): string
    {
        return 'New lesson: '.$this->lesson->title;
    }

    public function body(mixed $notifiable): string
    {
        $files = $this->lesson->attachments->count();

        return "New {$this->lesson->subject?->name} material for "
            .$this->lesson->schoolClass?->name
            .($files ? ", with {$files} file".($files === 1 ? '' : 's').' to download.' : '.');
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(mixed $notifiable): array
    {
        return ['lesson_id' => $this->lesson->id];
    }

    public function actionUrl(mixed $notifiable): ?string
    {
        return $this->spa('/student/materials');
    }
}
