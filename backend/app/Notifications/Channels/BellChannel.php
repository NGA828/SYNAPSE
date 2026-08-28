<?php

namespace App\Notifications\Channels;

use App\Models\Notification as BellNotification;
use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * Writes the in-app "bell" row into SYNAPSE's own `notifications` table
 * (tenant-scoped and user-owned) rather than Laravel's generic one, so the
 * existing frontend contract is preserved.
 */
class BellChannel
{
    public function send(mixed $notifiable, Notification $notification): ?BellNotification
    {
        if (! $notifiable instanceof User || ! method_exists($notification, 'toBell')) {
            return null;
        }

        $payload = $notification->toBell($notifiable);

        if ($payload === null) {
            return null;
        }

        return BellNotification::create([
            'school_id' => $notifiable->school_id,
            'user_id' => $notifiable->id,
            'type' => $payload['type'] ?? 'general',
            'title' => $payload['title'] ?? 'Notification',
            'message' => $payload['message'] ?? null,
            'data' => $payload['data'] ?? [],
        ]);
    }
}
