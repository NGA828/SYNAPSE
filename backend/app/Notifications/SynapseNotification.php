<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Channels\BellChannel;
use App\Notifications\Channels\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Base class for every SYNAPSE notification.
 *
 * Subclasses declare their content once (title/message/action) and this class
 * fans it out to the in-app bell, e-mail and SMS, honouring the recipient's
 * per-channel preferences. Everything is queued, so no HTTP request ever waits
 * on an SMTP or SMS round-trip.
 */
abstract class SynapseNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** Retry a failed delivery three times with a growing back-off. */
    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public function __construct()
    {
        $this->onQueue('notifications');
    }

    /**
     * Short machine-readable key, e.g. `request_updated`.
     */
    abstract public function type(): string;

    abstract public function title(mixed $notifiable): string;

    abstract public function body(mixed $notifiable): string;

    /**
     * Extra payload stored on the bell notification.
     *
     * @return array<string, mixed>
     */
    public function payload(mixed $notifiable): array
    {
        return [];
    }

    /**
     * Deep link into the SPA, appended to the mail button.
     */
    public function actionUrl(mixed $notifiable): ?string
    {
        return null;
    }

    public function actionLabel(): string
    {
        return 'Open SYNAPSE';
    }

    /**
     * Channels this notification supports; intersected with user preferences.
     *
     * @return array<int, string>
     */
    public function channels(): array
    {
        return ['bell', 'mail'];
    }

    /**
     * @return array<int, class-string|string>
     */
    public function via(mixed $notifiable): array
    {
        $wanted = $this->channels();
        $via = [];

        if (in_array('bell', $wanted, true) && $notifiable instanceof User) {
            $via[] = BellChannel::class;
        }

        if (in_array('mail', $wanted, true) && $this->wantsMail($notifiable)) {
            $via[] = 'mail';
        }

        if (in_array('sms', $wanted, true) && $this->wantsSms($notifiable)) {
            $via[] = SmsChannel::class;
        }

        return $via;
    }

    /**
     * @return array<string, mixed>
     */
    public function toBell(mixed $notifiable): array
    {
        return [
            'type' => $this->type(),
            'title' => $this->title($notifiable),
            'message' => $this->body($notifiable),
            'data' => $this->payload($notifiable),
        ];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $mail = (new MailMessage())
            ->subject($this->title($notifiable))
            ->greeting('Hello '.($notifiable->name ?? 'there').',')
            ->line($this->body($notifiable));

        if ($url = $this->actionUrl($notifiable)) {
            $mail->action($this->actionLabel(), $url);
        }

        return $mail->salutation('— '.($notifiable->school?->name ?? config('app.name')));
    }

    public function toSms(mixed $notifiable): string
    {
        return trim($this->title($notifiable).': '.$this->body($notifiable));
    }

    protected function wantsMail(mixed $notifiable): bool
    {
        return ! empty($notifiable->email)
            && ($notifiable->notify_email ?? true);
    }

    protected function wantsSms(mixed $notifiable): bool
    {
        return ! empty($notifiable->phone)
            && ($notifiable->notify_sms ?? false);
    }

    /**
     * Base URL of the SPA, used to build deep links in e-mails.
     */
    protected function spa(string $path = ''): string
    {
        return rtrim((string) config('app.frontend_url', config('app.url')), '/').'/'.ltrim($path, '/');
    }
}
