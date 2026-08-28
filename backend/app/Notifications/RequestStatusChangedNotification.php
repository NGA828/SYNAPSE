<?php

namespace App\Notifications;

use App\Models\DocumentRequest;

class RequestStatusChangedNotification extends SynapseNotification
{
    public function __construct(
        public readonly DocumentRequest $documentRequest,
        public readonly string $status,
    ) {
        parent::__construct();
    }

    public function type(): string
    {
        return 'request_updated';
    }

    public function title(mixed $notifiable): string
    {
        return 'Request '.$this->documentRequest->reference.' updated';
    }

    public function body(mixed $notifiable): string
    {
        return match ($this->status) {
            DocumentRequest::STATUS_UNDER_REVIEW => "Your request {$this->documentRequest->reference} is now under review.",
            DocumentRequest::STATUS_APPROVED => "Your request {$this->documentRequest->reference} has been approved.",
            DocumentRequest::STATUS_READY => "Your request {$this->documentRequest->reference} is ready to download.",
            DocumentRequest::STATUS_REJECTED => "Your request {$this->documentRequest->reference} was declined.",
            default => "Your request {$this->documentRequest->reference} was updated.",
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(mixed $notifiable): array
    {
        return ['request_id' => $this->documentRequest->id, 'status' => $this->status];
    }

    public function actionUrl(mixed $notifiable): ?string
    {
        return $this->spa('/student/requests');
    }

    /**
     * A ready document is worth an SMS; intermediate steps are not.
     *
     * @return array<int, string>
     */
    public function channels(): array
    {
        return $this->status === DocumentRequest::STATUS_READY
            ? ['bell', 'mail', 'sms']
            : ['bell', 'mail'];
    }
}
