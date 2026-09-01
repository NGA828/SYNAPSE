<?php

namespace App\Services;

use App\Contracts\AnnouncementDrafter;
use App\Models\User;
use App\Services\Ai\AnnouncementBrief;
use App\Services\Ai\DeterministicAnnouncementDrafter;

/**
 * Drafts announcements. Does not publish them.
 *
 * `AnnouncementService::create()` is untouched by this class, so the audience
 * fan-out, the notification channels and the existing audit trail are exactly as
 * they were. An administrator always reads the draft and presses Publish
 * themselves — a draft is a suggestion, and nothing here persists one.
 *
 * The gating below is deliberately identical to `CommentService::aiAvailable()`:
 * the `ai_assistant` plan flag decides whether an external model may be used,
 * never whether the school gets drafting at all. Every school gets a structured,
 * correctly-language'd draft from the deterministic writer.
 */
class AnnouncementDraftService
{
    public function __construct(
        private readonly DeterministicAnnouncementDrafter $deterministic,
        private readonly AnnouncementDrafter $configured,
        private readonly SubscriptionService $subscriptions,
        private readonly AuditService $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function draft(User $author, array $input): array
    {
        $brief = AnnouncementBrief::fromRequest($input, $author->locale);

        $aiAvailable = $this->aiAvailable($author);
        $drafter = $aiAvailable ? $this->configured : $this->deterministic;

        $draft = $drafter->draft($brief);

        $this->audit->log(
            $author->school,
            $author,
            'announcement.drafted',
            'announcement',
            null,
            [
                // Provenance, recorded before the text reaches anybody.
                'ai_generated' => $drafter->name() !== 'deterministic',
                'drafter' => $drafter->name(),
                'locale' => $brief->locale,
                'audience' => $brief->audience,
                'tone' => $brief->tone,
                'characters' => mb_strlen($draft['body']),
            ],
        );

        return [
            'title' => $draft['title'],
            'body' => $draft['body'],
            'short_body' => $draft['short_body'],
            'source' => $drafter->name(),
            'locale' => $brief->locale,
            'ai_available' => $aiAvailable,
        ];
    }

    /**
     * Whether this school may draft with an external model.
     *
     * Mirrors `CommentService::aiAvailable()`. The three config conditions and
     * the driver check must stay in step with it — if this ever gains a
     * condition, that one needs it too.
     */
    public function aiAvailable(User $author): bool
    {
        if (! config('ai.enabled') || ! config('ai.key') || ! config('ai.model')) {
            return false;
        }

        if (config('ai.driver', 'deterministic') !== 'http') {
            return false;
        }

        $school = $author->school;

        return $school !== null && $this->subscriptions->hasFeature($school, 'ai_assistant');
    }
}
