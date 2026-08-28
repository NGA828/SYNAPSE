<?php

namespace App\Notifications;

use App\Models\Document;

class DocumentReadyNotification extends SynapseNotification
{
    public function __construct(
        public readonly Document $document,
    ) {
        parent::__construct();
    }

    public function type(): string
    {
        return 'document_ready';
    }

    public function title(mixed $notifiable): string
    {
        return 'Your document is ready';
    }

    public function body(mixed $notifiable): string
    {
        return "\"{$this->document->title}\" has been issued and is available for download. "
            ."Verification code: {$this->document->verification_code}.";
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(mixed $notifiable): array
    {
        return [
            'document_id' => $this->document->id,
            'verification_code' => $this->document->verification_code,
        ];
    }

    public function actionUrl(mixed $notifiable): ?string
    {
        return $this->spa('/student/documents');
    }

    /**
     * @return array<int, string>
     */
    public function channels(): array
    {
        return ['bell', 'mail', 'sms'];
    }
}
