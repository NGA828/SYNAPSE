<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\School;
use App\Models\User;
use App\Notifications\SynapseNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification as LaravelNotification;

/**
 * Fans notifications out to the in-app bell, e-mail and SMS.
 *
 * Delivery is handled by queued Laravel notifications (see App\Notifications);
 * this service is the single entry point the rest of the app talks to, so no
 * caller has to know which channels a given event uses.
 */
class NotificationService
{
    /**
     * Deliver a notification to one user.
     */
    public function notify(?User $user, SynapseNotification $notification): void
    {
        if (! $user) {
            return;
        }

        $user->notify($notification);
    }

    /**
     * Deliver to many users at once (a single queued batch per channel).
     *
     * @param  iterable<int, User>  $users
     */
    public function notifyMany(iterable $users, SynapseNotification $notification): void
    {
        $recipients = collect($users)->filter()->values();

        if ($recipients->isEmpty()) {
            return;
        }

        LaravelNotification::send($recipients, $notification);
    }

    /**
     * Deliver to every user holding a role. Always scoped to a school so a
     * queued job can never leak across tenants.
     */
    public function notifyRole(School|int|null $school, string $role, SynapseNotification $notification): void
    {
        $schoolId = $school instanceof School ? $school->id : $school;

        if (! $schoolId) {
            return;
        }

        User::query()
            ->where('school_id', $schoolId)
            ->where('role', $role)
            ->chunkById(200, fn (Collection $users) => $this->notifyMany($users, $notification));
    }

    /**
     * Write an in-app notification directly, without e-mail or SMS.
     *
     * @param  array<string, mixed>  $data
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
     * Paginated bell feed plus the unread count for a user.
     *
     * @return array{data: LengthAwarePaginator, unread_count: int}
     */
    public function forUser(User $user, int $perPage = 20): array
    {
        return [
            'data' => $user->notifications()->latest()->paginate($perPage),
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

    /**
     * Delete read notifications older than the retention window.
     */
    public function prune(int $days = 90): int
    {
        return Notification::query()
            ->withoutTenant()
            ->whereNotNull('read_at')
            ->where('created_at', '<', now()->subDays($days))
            ->delete();
    }
}
