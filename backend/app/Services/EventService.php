<?php

namespace App\Services;

use App\Models\Event;
use App\Models\User;
use App\Notifications\EventPublishedNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * School events, written by administrators and read by everyone the audience
 * includes.
 *
 * Unlike announcements, an event occupies a span of time, which is what makes
 * it usable by the calendar.
 */
class EventService
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    /**
     * Published events this user should see, soonest first.
     *
     * @return Collection<int, Event>
     */
    public function upcomingFor(User $user, int $days = 60, ?string $type = null): Collection
    {
        return Event::query()
            ->published()
            ->visibleToRole($user->role)
            ->when($type, fn ($query) => $query->where('type', $type))
            ->where(
                fn ($query) => $query
                    ->where('starts_at', '>=', now()->startOfDay())
                    ->orWhere('ends_at', '>=', now()),
            )
            ->where('starts_at', '<=', now()->addDays($days))
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * The administrator's own list, drafts included.
     *
     * @return LengthAwarePaginator<Event>
     */
    public function forAdmin(int $perPage = 15, ?string $search = null): LengthAwarePaginator
    {
        return Event::query()
            ->with('author')
            ->when($search, function ($query) use ($search) {
                $term = trim($search);

                $query->where(
                    fn ($inner) => $inner
                        ->where('title', 'like', "%{$term}%")
                        ->orWhere('description', 'like', "%{$term}%")
                        ->orWhere('location', 'like', "%{$term}%"),
                );
            })
            ->orderByDesc('starts_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(User $admin, array $data): Event
    {
        $this->assertWindow($data['starts_at'], $data['ends_at'] ?? null);

        $event = Event::create([
            'school_id' => $admin->school_id,
            'user_id' => $admin->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'] ?? Event::TYPE_OTHER,
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'] ?? null,
            'all_day' => (bool) ($data['all_day'] ?? false),
            'location' => $data['location'] ?? null,
            'audience' => $data['audience'] ?? Event::AUDIENCE_ALL,
            'is_published' => false,
        ]);

        return $event->load('author');
    }

    public function update(User $admin, Event $event, array $data): Event
    {
        $this->assertOwnsSchool($admin, $event);

        $startsAt = $data['starts_at'] ?? $event->starts_at;
        $endsAt = array_key_exists('ends_at', $data) ? $data['ends_at'] : $event->ends_at;

        $this->assertWindow($startsAt, $endsAt);

        $event->update(array_filter([
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'type' => $data['type'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $endsAt,
            'location' => $data['location'] ?? null,
            'audience' => $data['audience'] ?? null,
            'all_day' => isset($data['all_day']) ? (bool) $data['all_day'] : null,
        ], fn ($value) => $value !== null));

        return $event->fresh('author');
    }

    public function delete(User $admin, Event $event): void
    {
        $this->assertOwnsSchool($admin, $event);

        $event->delete();
    }

    /**
     * Publish, and tell the audience it is on the calendar.
     */
    public function publish(User $admin, Event $event): Event
    {
        $this->assertOwnsSchool($admin, $event);

        if ($event->is_published) {
            return $event;
        }

        $event->update(['is_published' => true, 'published_at' => now()]);

        $this->notifications->notifyRole(
            $admin->school_id,
            $this->notificationRole($event->audience),
            new EventPublishedNotification($event),
        );

        return $event->fresh('author');
    }

    public function unpublish(User $admin, Event $event): Event
    {
        $this->assertOwnsSchool($admin, $event);

        $event->update(['is_published' => false]);

        return $event->fresh('author');
    }

    /**
     * `notifyRole` takes one role or null for everyone; an `all` audience maps
     * to null so no group is missed.
     */
    private function notificationRole(string $audience): ?string
    {
        return match ($audience) {
            Event::AUDIENCE_STUDENTS => User::ROLE_STUDENT,
            Event::AUDIENCE_TEACHERS => User::ROLE_TEACHER,
            default => null,
        };
    }

    private function assertWindow(string $startsAt, ?string $endsAt): void
    {
        if ($endsAt === null) {
            return;
        }

        abort_if(
            strtotime($endsAt) <= strtotime($startsAt),
            422,
            'An event must end after it starts.',
        );
    }

    private function assertOwnsSchool(User $admin, Event $event): void
    {
        abort_unless($event->school_id === $admin->school_id, 403, 'This event belongs to another school.');
    }
}
