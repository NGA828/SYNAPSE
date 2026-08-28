<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\User;
use App\Notifications\AnnouncementPublishedNotification;
use Illuminate\Support\Collection;

class AnnouncementService
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    /**
     * Publish an announcement and notify its audience.
     */
    public function create(User $author, array $data): Announcement
    {
        $announcement = Announcement::create([
            'school_id' => $author->school_id,
            'user_id' => $author->id,
            'title' => $data['title'],
            'body' => $data['body'],
            'audience' => $data['audience'] ?? Announcement::AUDIENCE_ALL,
            'published_at' => now(),
        ]);

        $recipients = match ($announcement->audience) {
            Announcement::AUDIENCE_STUDENTS => User::query()->where('role', 'student')->get(),
            Announcement::AUDIENCE_TEACHERS => User::query()->where('role', 'teacher')->get(),
            default => User::query()->whereIn('role', ['student', 'teacher', 'admin'])->get(),
        };

        $this->notifications->notifyMany(
            $recipients,
            new AnnouncementPublishedNotification($announcement),
        );

        return $announcement->load('author');
    }

    /**
     * Announcements visible to a given user (audience-filtered by role).
     *
     * @return Collection<int, Announcement>
     */
    public function forUser(User $user): Collection
    {
        return Announcement::query()
            ->with('author')
            ->where(function ($query) use ($user) {
                if ($user->isAdmin()) {
                    return;
                }

                $audiences = $user->isTeacher()
                    ? [Announcement::AUDIENCE_ALL, Announcement::AUDIENCE_TEACHERS]
                    : [Announcement::AUDIENCE_ALL, Announcement::AUDIENCE_STUDENTS];

                $query->whereIn('audience', $audiences);
            })
            ->latest()
            ->get();
    }
}
