<?php

namespace App\Notifications;

use App\Models\Document;

class ReportCardReadyNotification extends SynapseNotification
{
    public function __construct(
        public readonly Document $document,
        public readonly ?string $period = null,
    ) {
        parent::__construct();
    }

    public function type(): string
    {
        return 'report_card_ready';
    }

    public function title(mixed $notifiable): string
    {
        return 'Your report card is available';
    }

    public function body(mixed $notifiable): string
    {
        return 'The report card'.($this->period ? ' for '.$this->period : '')
            .' has been published and can be downloaded from your documents.';
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(mixed $notifiable): array
    {
        return ['document_id' => $this->document->id, 'period' => $this->period];
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
