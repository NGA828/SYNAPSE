<?php

namespace App\Notifications;

use App\Models\Event;

class EventPublishedNotification extends SynapseNotification
{
    public function __construct(
        public readonly Event $event,
    ) {
        parent::__construct();
    }

    public function type(): string
    {
        return 'event';
    }

    public function title(mixed $notifiable): string
    {
        return $this->event->title;
    }

    public function body(mixed $notifiable): string
    {
        $when = $this->event->starts_at->format('D j M, H:i');

        return $this->event->location
            ? ucfirst($this->event->type).' · '.$when.' · '.$this->event->location
            : ucfirst($this->event->type).' · '.$when;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(mixed $notifiable): array
    {
        return ['event_id' => $this->event->id];
    }

    public function actionUrl(mixed $notifiable): ?string
    {
        return $this->spa('/calendar');
    }
}
