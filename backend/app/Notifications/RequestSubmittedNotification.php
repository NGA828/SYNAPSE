<?php

namespace App\Notifications;

use App\Models\DocumentRequest;

class RequestSubmittedNotification extends SynapseNotification
{
    public function __construct(
        public readonly DocumentRequest $documentRequest,
        public readonly string $studentName,
    ) {
        parent::__construct();
    }

    public function type(): string
    {
        return 'request_created';
    }

    public function title(mixed $notifiable): string
    {
        return 'New document request';
    }

    public function body(mixed $notifiable): string
    {
        return "{$this->studentName} submitted a \"{$this->documentRequest->type}\" request ({$this->documentRequest->reference}).";
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(mixed $notifiable): array
    {
        return ['request_id' => $this->documentRequest->id];
    }

    public function actionUrl(mixed $notifiable): ?string
    {
        return $this->spa('/admin/requests');
    }
}
