<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;

class NotificationService
{
    /**
     * Send a notification to a single user.
     */
    public function send(User $user, string $type, string $title, ?string $message = null, array $data = []): Notification
    {
        return Notification::create([
            'school_id' => $user->school_id,
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
        ]);
    }

    /**
     * Notify every user with the given role.
     */
    public function sendToRole(string $role, string $type, string $title, ?string $message = null, array $data = []): void
    {
        User::query()->where('role', $role)->get()->each(
            fn (User $user) => $this->send($user, $type, $title, $message, $data),
        );
    }

    /**
     * Recent notifications plus the unread count for a user.
     *
     * @return array{data: mixed, unread_count: int}
     */
    public function forUser(User $user, int $limit = 30): array
    {
        return [
            'data' => $user->notifications()->latest()->take($limit)->get(),
            'unread_count' => $user->notifications()->unread()->count(),
        ];
    }

    public function markRead(User $user, Notification $notification): Notification
    {
        abort_unless($notification->user_id === $user->id, 403, 'This notification does not belong to you.');

        $notification->markAsRead();

        return $notification;
    }

    public function markAllRead(User $user): void
    {
        $user->notifications()->unread()->update(['read_at' => now()]);
    }
}
