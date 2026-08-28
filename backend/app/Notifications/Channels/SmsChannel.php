<?php

namespace App\Notifications\Channels;

use App\Services\Sms\SmsManager;
use Illuminate\Notifications\Notification;

/**
 * Delivers `toSms()` output through the configured SMS provider.
 */
class SmsChannel
{
    public function __construct(
        private readonly SmsManager $sms,
    ) {}

    public function send(mixed $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toSms')) {
            return;
        }

        $to = method_exists($notifiable, 'routeNotificationForSms')
            ? $notifiable->routeNotificationForSms()
            : ($notifiable->phone ?? null);

        $message = $notification->toSms($notifiable);

        if (! $to || ! $message) {
            return;
        }

        $this->sms->send($to, $message);
    }
}
