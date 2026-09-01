<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Notifications\MessageReceivedNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Direct messages between members of one school.
 *
 * Who may message whom is a deliberate policy, not an accident of the schema.
 * A student may write to a teacher or an administrator — that covers "I have a
 * question about my homework" — but not to another student. A school platform
 * should not quietly provide an unsupervised student-to-student channel, and
 * the one rule below is easier for staff to reason about than a matrix of
 * allowed pairs.
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
        $other = User::query()->findOrFail($otherUserId);

        abort_if($other->id === $user->id, 422, 'You cannot start a conversation with yourself.');

        abort_unless($other->school_id === $user->school_id, 403, 'That person is not at your school.');

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
     * Students see staff only; staff see the whole school. Super admins are
     * never listed: they belong to the platform, not to the school.
     *
     * @return Collection<int, User>
     */
    public function recipientsFor(User $user, ?string $search = null, int $limit = 25): Collection
    {
        $query = User::query()
            ->where('school_id', $user->school_id)
            ->where('id', '!=', $user->id)
            ->where('role', '!=', User::ROLE_SUPER_ADMIN);

        if ($user->role === User::ROLE_STUDENT) {
            $query->whereIn('role', [User::ROLE_TEACHER, User::ROLE_ADMIN]);
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
        if ($user->role === User::ROLE_STUDENT) {
            abort_unless(
                in_array($other->role, [User::ROLE_TEACHER, User::ROLE_ADMIN], true),
                403,
                'Students can message teachers and administrators only.',
            );
        }
    }

    private function assertParticipant(User $user, Conversation $conversation): void
    {
        abort_unless(
            $conversation->includes($user->id),
            403,
            'You are not part of this conversation.',
        );
    }
}
