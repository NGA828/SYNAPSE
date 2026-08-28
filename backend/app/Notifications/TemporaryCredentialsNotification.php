<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * Sent when an administrator creates or bulk-imports an account. Carries the
 * one-time password the user must change at first sign-in.
 */
class TemporaryCredentialsNotification extends SynapseNotification
{
    public function __construct(
        public readonly string $temporaryPassword,
        public readonly ?string $schoolName = null,
    ) {
        parent::__construct();
    }

    public function type(): string
    {
        return 'account_created';
    }

    public function title(mixed $notifiable): string
    {
        return 'Your '.($this->schoolName ?? config('app.name')).' account is ready';
    }

    public function body(mixed $notifiable): string
    {
        return 'An account has been created for you. Sign in with your e-mail address and the '
            .'temporary password provided, then choose a new password.';
    }

    public function actionUrl(mixed $notifiable): ?string
    {
        return $this->spa('/login');
    }

    public function actionLabel(): string
    {
        return 'Sign in';
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject($this->title($notifiable))
            ->greeting('Welcome '.($notifiable->name ?? '').',')
            ->line($this->body($notifiable))
            ->line('E-mail: '.$notifiable->email)
            ->line('Temporary password: '.$this->temporaryPassword)
            ->action('Sign in', (string) $this->actionUrl($notifiable))
            ->line('For your security you will be asked to change this password immediately.')
            ->salutation('— '.($this->schoolName ?? config('app.name')));
    }

    public function toSms(mixed $notifiable): string
    {
        return ($this->schoolName ?? config('app.name')).': your account is ready. '
            .'Login '.$notifiable->email.' / temporary password '.$this->temporaryPassword
            .'. Change it after signing in.';
    }

    /**
     * @return array<int, string>
     */
    public function channels(): array
    {
        return ['mail', 'sms'];
    }

    protected function wantsSms(mixed $notifiable): bool
    {
        // Credentials always go by SMS when we have a number — a new user
        // cannot yet have opted in through the UI.
        return ! empty($notifiable->phone);
    }
}
