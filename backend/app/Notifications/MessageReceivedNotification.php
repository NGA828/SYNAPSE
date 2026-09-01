<?php

namespace App\Notifications;

use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Str;

class MessageReceivedNotification extends SynapseNotification
{
    public function __construct(
        public readonly User $sender,
        public readonly Message $message,
    ) {
        parent::__construct();
    }

    public function type(): string
    {
        return 'message';
    }

    public function title(mixed $notifiable): string
    {
        return 'New message from '.$this->sender->name;
    }

    public function body(mixed $notifiable): string
    {
        return Str::limit($this->message->body, 140);
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(mixed $notifiable): array
    {
        return [
            'conversation_id' => $this->message->conversation_id,
            'message_id' => $this->message->id,
            'sender_id' => $this->sender->id,
        ];
    }

    public function actionUrl(mixed $notifiable): ?string
    {
        return $this->spa('/messages/'.$this->message->conversation_id);
    }
}
