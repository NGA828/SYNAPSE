<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Services\DocumentService;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Confirms a successful payment and attaches the PDF receipt.
 */
class PaymentReceiptNotification extends SynapseNotification
{
    public function __construct(
        public readonly Payment $payment,
    ) {
        parent::__construct();
    }

    public function type(): string
    {
        return 'payment_received';
    }

    public function title(mixed $notifiable): string
    {
        return 'Payment received — '.number_format((float) $this->payment->amount, 0, '.', ' ').' '.$this->payment->currency;
    }

    public function body(mixed $notifiable): string
    {
        return 'We have received your payment (reference '.$this->payment->reference.'). '
            .'Your receipt is attached to this e-mail and available from the billing page.';
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(mixed $notifiable): array
    {
        return ['payment_id' => $this->payment->id, 'reference' => $this->payment->reference];
    }

    public function actionUrl(mixed $notifiable): ?string
    {
        return $this->spa('/admin/billing');
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $mail = parent::toMail($notifiable);

        $pdf = app(DocumentService::class)->receiptBytes($this->payment);

        return $mail->attachData($pdf, 'receipt-'.$this->payment->reference.'.pdf', [
            'mime' => 'application/pdf',
        ]);
    }
}
