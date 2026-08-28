<?php

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Support\Str;

class AnnouncementPublishedNotification extends SynapseNotification
{
    public function __construct(
        public readonly Announcement $announcement,
    ) {
        parent::__construct();
    }

    public function type(): string
    {
        return 'announcement';
    }

    public function title(mixed $notifiable): string
    {
        return $this->announcement->title;
    }

    public function body(mixed $notifiable): string
    {
        return Str::limit(strip_tags((string) $this->announcement->body), 240);
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(mixed $notifiable): array
    {
        return ['announcement_id' => $this->announcement->id];
    }

    public function actionUrl(mixed $notifiable): ?string
    {
        return $this->spa('/announcements');
    }
}
