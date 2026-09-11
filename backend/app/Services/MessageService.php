<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Conversation;
use App\Models\Enrollment;
use App\Models\Message;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Notifications\MessageReceivedNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Direct messages between users who share an explicitly permitted relationship.
 *
 * The recipient policy is deliberately enforced here, rather than only in the
 * recipient picker, so a crafted request cannot bypass the UI.
 */
class MessageService
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    /**
     * The user's conversations, newest activity first, each with the other
     * participant, a preview, and how many messages are waiting for them.
     *
     * @return LengthAwarePaginator<Conversation>
     */
    public function conversationsFor(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return Conversation::query()
            ->with(['participantA', 'participantB'])
            ->withCount([
                'messages as unread_count' => fn ($query) => $query
                    ->whereNull('read_at')
                    ->where('sender_id', '!=', $user->id),
            ])
            ->forParticipant($user->id)
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Open — or start — a conversation with another user.
     *
     * Idempotent: asking twice returns the same thread rather than a duplicate,
     * which is what the ordered-pair unique index guarantees.
     */
    public function conversationWith(User $user, int $otherUserId): Conversation
    {
        $otherQuery = User::query();

        if ($user->isAdmin()) {
            $otherQuery->withoutGlobalScope(\App\Models\Scopes\TenantScope::class);
        }

        $other = $otherQuery->findOrFail($otherUserId);

        abort_if($other->id === $user->id, 422, 'You cannot start a conversation with yourself.');

        abort_unless(
            $other->school_id === $user->school_id
                || ($user->isAdmin() && $other->isSuperAdmin()),
            403,
            'That person is not available to you.',
        );

        $this->assertMayMessage($user, $other);

        [$a, $b] = $this->orderedPair($user->id, $other->id);

        return Conversation::query()->firstOrCreate(
            [
                'school_id' => $user->school_id,
                'participant_a_id' => $a,
                'participant_b_id' => $b,
            ],
        );
    }

    /**
     * A page of one thread, oldest first.
     *
     * Reading marks the other person's messages as read — that is the whole
     * point of opening the thread, so a separate call would only be a way to
     * get the counters wrong.
     *
     * @return LengthAwarePaginator<Message>
     */
    public function threadFor(User $user, Conversation $conversation, int $perPage = 50): LengthAwarePaginator
    {
        $this->assertParticipant($user, $conversation);

        $thread = $conversation->messages()
            ->with('sender')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        $this->markRead($user, $conversation);

        // Restore chronological order after paginating newest-first.
        return $thread->setCollection($thread->getCollection()->reverse()->values());
    }

    public function send(User $user, Conversation $conversation, string $body): Message
    {
        $this->assertParticipant($user, $conversation);

        return DB::transaction(function () use ($user, $conversation, $body) {
            $message = $conversation->messages()->create([
                'school_id' => $conversation->school_id,
                'sender_id' => $user->id,
                'body' => $body,
            ]);

            $conversation->update(['last_message_at' => $message->created_at]);

            $recipientId = $conversation->otherParticipantId($user->id);
            $recipient = $recipientId ? User::query()->find($recipientId) : null;

            if ($recipient) {
                $this->notifications->notify($recipient, new MessageReceivedNotification($user, $message));
            }

            return $message->load('sender');
        });
    }

    public function markRead(User $user, Conversation $conversation): int
    {
        $this->assertParticipant($user, $conversation);

        return $conversation->messages()
            ->whereNull('read_at')
            ->where('sender_id', '!=', $user->id)
            ->update(['read_at' => now()]);
    }

    /**
     * Total unread across every conversation — the badge in the sidebar.
     */
    public function unreadCountFor(User $user): int
    {
        return Message::query()
            ->whereIn('conversation_id', Conversation::query()->forParticipant($user->id)->select('id'))
            ->whereNull('read_at')
            ->where('sender_id', '!=', $user->id)
            ->count();
    }

    /**
     * People this user may start a conversation with.
     *
     * @return Collection<int, User>
     */
    public function recipientsFor(User $user, ?string $search = null, int $limit = 25): Collection
    {
        $allowedIds = $this->allowedRecipientIds($user);

        $query = User::query()->whereIn('id', $allowedIds);

        // Super admins have no school tenant, so their records are hidden by
        // the tenant scope during an admin request. The policy explicitly
        // allows an admin to contact them.
        if ($user->isAdmin()) {
            $query = User::query()
                ->withoutGlobalScope(\App\Models\Scopes\TenantScope::class)
                ->whereIn('id', $allowedIds);
        }

        if ($search) {
            $term = trim($search);
            $query->where('name', 'like', "%{$term}%");
        }

        return $query->orderBy('name')->limit($limit)->get();
    }

    /**
     * Put the lower id in `a`. This is the one place the ordering is decided,
     * so the unique index on (school, a, b) always sees a canonical pair.
     *
     * @return array{int, int}
     */
    private function orderedPair(int $first, int $second): array
    {
        return $first < $second ? [$first, $second] : [$second, $first];
    }

    private function assertMayMessage(User $user, User $other): void
    {
        abort_unless(
            $this->canMessage($user, $other),
            403,
            'You are not allowed to message this person.',
        );
    }

    private function canMessage(User $user, User $other): bool
    {
        return $this->allowedRecipientIds($user)->contains((int) $other->id);
    }

    /**
     * Build the recipient ids for the current academic year.
     *
     * Students may contact classmates and teachers assigned to their class.
     * Teachers may contact their students, teachers sharing one of their
     * subjects, and school administrators. Administrators may contact school
     * teachers and platform super administrators.
     *
     * @return Collection<int, int>
     */
    private function allowedRecipientIds(User $user): Collection
    {
        $year = AcademicYear::current();

        if (! $year) {
            return collect();
        }

        if ($user->isAdmin()) {
            $teacherIds = User::query()
                ->where('school_id', $user->school_id)
                ->where('role', User::ROLE_TEACHER)
                ->pluck('id');

            $superAdminIds = User::query()
                ->withoutGlobalScope(\App\Models\Scopes\TenantScope::class)
                ->where('role', User::ROLE_SUPER_ADMIN)
                ->pluck('id');

            return $teacherIds
                ->merge($superAdminIds)
                ->reject(fn (int $id): bool => $id === (int) $user->id)
                ->unique()
                ->values();
        }

        if ($user->isStudent()) {
            $studentId = $user->student?->id;

            if (! $studentId) {
                return collect();
            }

            $classIds = Enrollment::query()
                ->where('student_id', $studentId)
                ->where('academic_year_id', $year->id)
                ->pluck('class_id');

            if ($classIds->isEmpty()) {
                return collect();
            }

            $teacherIds = TeachingAssignment::query()
                ->whereIn('class_id', $classIds)
                ->where('academic_year_id', $year->id)
                ->join('teachers', 'teachers.id', '=', 'teaching_assignments.teacher_id')
                ->pluck('teachers.user_id');

            $classmateIds = User::query()
                ->where('role', User::ROLE_STUDENT)
                ->whereHas('student.enrollments', function ($query) use ($classIds, $year): void {
                    $query->whereIn('class_id', $classIds)
                        ->where('academic_year_id', $year->id);
                })
                ->pluck('id');

            return $teacherIds
                ->merge($classmateIds)
                ->reject(fn (int $id): bool => $id === (int) $user->id)
                ->unique()
                ->values();
        }

        if ($user->isTeacher()) {
            $teacher = $user->teacher;

            if (! $teacher) {
                return collect();
            }

            $assignments = TeachingAssignment::query()
                ->where('teacher_id', $teacher->id)
                ->where('academic_year_id', $year->id)
                ->get(['class_id', 'subject_id']);

            if ($assignments->isEmpty()) {
                return collect();
            }

            $classIds = $assignments->pluck('class_id');
            $subjectIds = $assignments->pluck('subject_id');

            $studentIds = User::query()
                ->where('role', User::ROLE_STUDENT)
                ->whereHas('student.enrollments', function ($query) use ($classIds, $year): void {
                    $query->whereIn('class_id', $classIds)
                        ->where('academic_year_id', $year->id);
                })
                ->pluck('id');

            $sharedSubjectTeacherIds = TeachingAssignment::query()
                ->whereIn('subject_id', $subjectIds)
                ->where('academic_year_id', $year->id)
                ->join('teachers', 'teachers.id', '=', 'teaching_assignments.teacher_id')
                ->where('teachers.id', '!=', $teacher->id)
                ->pluck('teachers.user_id');

            $adminIds = User::query()
                ->where('school_id', $user->school_id)
                ->where('role', User::ROLE_ADMIN)
                ->pluck('id');

            return $studentIds
                ->merge($sharedSubjectTeacherIds)
                ->merge($adminIds)
                ->reject(fn (int $id): bool => $id === (int) $user->id)
                ->unique()
                ->values();
        }

        return collect();
    }

    private function assertParticipant(User $user, Conversation $conversation): void
    {
        abort_unless(
            $conversation->includes($user->id),
            403,
            'You are not part of this conversation.',
        );

        $otherId = $conversation->otherParticipantId($user->id);
        $other = $otherId
            ? User::query()
                ->withoutGlobalScope(\App\Models\Scopes\TenantScope::class)
                ->find($otherId)
            : null;

        abort_unless(
            $other && ($this->canMessage($user, $other) || $this->canMessage($other, $user)),
            403,
            'You are not allowed to use this conversation.',
        );
    }
}
