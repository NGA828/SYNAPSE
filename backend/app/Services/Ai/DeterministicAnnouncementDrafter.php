<?php

namespace App\Services\Ai;

use App\Contracts\AnnouncementDrafter;
use App\Models\Announcement;
use Illuminate\Support\Str;

/**
 * The default announcement drafter.
 *
 * This is not a stub standing in for a model — it is the thing that runs on a
 * fresh install with no key configured, and the thing a provider falls back to.
 * It assembles a properly structured, correctly-language'd announcement from
 * the administrator's factual inputs.
 *
 * What it cannot do is translate free prose. If an administrator types the
 * subject in English and asks for French, the framing will be French and the
 * subject will still be English. That limitation is surfaced to the caller as
 * `source: deterministic` rather than hidden.
 */
class DeterministicAnnouncementDrafter implements AnnouncementDrafter
{
    public function name(): string
    {
        return 'deterministic';
    }

    /**
     * @return array{title: string, body: string, short_body: string}
     */
    public function draft(AnnouncementBrief $brief): array
    {
        $fr = $brief->isFrench();
        $friendly = $brief->isFriendly();

        $title = $this->title($brief);
        $parts = [];

        $parts[] = $this->salutation($brief);
        $parts[] = $this->opening($brief, $fr, $friendly);

        if ($brief->keyPoints !== []) {
            $parts[] = $this->keyPoints($brief, $fr);
        }

        $logistics = $this->logistics($brief, $fr);

        if ($logistics !== null) {
            $parts[] = $logistics;
        }

        if ($brief->actionRequired !== null) {
            $parts[] = $fr
                ? 'Action requise : '.$brief->actionRequired
                : 'Action required: '.$brief->actionRequired;
        }

        $parts[] = $this->closing($fr, $friendly);

        $body = $this->constrain(implode("\n\n", $parts));

        return [
            'title' => $title,
            'body' => $body,
            'short_body' => $this->shorten($body),
        ];
    }

    private function title(AnnouncementBrief $brief): string
    {
        return Str::of($brief->subject)->trim()->ucfirst()->toString();
    }

    private function salutation(AnnouncementBrief $brief): string
    {
        if ($brief->isFrench()) {
            return match ($brief->audience) {
                Announcement::AUDIENCE_STUDENTS => 'Chers élèves,',
                Announcement::AUDIENCE_TEACHERS => 'Chers collègues,',
                default => 'Chers élèves et membres du personnel,',
            };
        }

        return match ($brief->audience) {
            Announcement::AUDIENCE_STUDENTS => 'Dear students,',
            Announcement::AUDIENCE_TEACHERS => 'Dear colleagues,',
            default => 'Dear students and staff,',
        };
    }

    private function opening(AnnouncementBrief $brief, bool $fr, bool $friendly): string
    {
        $subject = Str::of($brief->subject)->trim()->lcfirst()->toString();

        if ($fr) {
            return $friendly
                ? 'Petit mot au sujet de '.$subject.'.'
                : 'Nous vous informons que '.$subject.'.';
        }

        return $friendly
            ? 'A quick note about '.$subject.'.'
            : 'This is to inform you that '.$subject.'.';
    }

    /**
     * @return string
     */
    private function keyPoints(AnnouncementBrief $brief, bool $fr)
    {
        $heading = $fr ? 'Points importants :' : 'Key details:';

        $lines = collect($brief->keyPoints)
            ->map(fn (string $point) => '• '.Str::of($point)->trim()->ucfirst()->toString())
            ->all();

        return $heading."\n".implode("\n", $lines);
    }

    private function logistics(AnnouncementBrief $brief, bool $fr): ?string
    {
        $date = $brief->dateText;
        $venue = $brief->venue;

        if ($date === null && $venue === null) {
            return null;
        }

        if ($fr) {
            return match (true) {
                $date !== null && $venue !== null => 'Cela aura lieu le '.$date.', à '.$venue.'.',
                $date !== null => 'Cela aura lieu le '.$date.'.',
                default => 'Cela aura lieu à '.$venue.'.',
            };
        }

        return match (true) {
            $date !== null && $venue !== null => 'It takes place on '.$date.' at '.$venue.'.',
            $date !== null => 'It takes place on '.$date.'.',
            default => 'It takes place at '.$venue.'.',
        };
    }

    private function closing(bool $fr, bool $friendly): string
    {
        if ($fr) {
            return $friendly ? 'Merci et à bientôt !' : 'Merci de votre attention.';
        }

        return $friendly ? 'Thanks — see you there!' : 'Thank you for your attention.';
    }

    /**
     * Enforce the ceiling on our own output rather than trusting the caller.
     * 5000 characters is what `StoreAnnouncementRequest` will accept, so a draft
     * that exceeds it could not be published even though it drafted fine.
     */
    private function constrain(string $body): string
    {
        $limit = (int) config('ai.announcements.max_body_length', 5000);
        $end = '…';

        if (mb_strlen($body) <= $limit) {
            return $body;
        }

        /*
        | Not Str::limit on purpose: it truncates to $limit and *then* appends
        | the end marker, so it can return $limit + 1 characters — one more than
        | StoreAnnouncementRequest's max:5000 accepts. That would make a draft
        | the admin cannot publish, which is worse than no draft. Truncate to
        | the space actually left for the text.
        */
        return rtrim(mb_substr($body, 0, $limit - mb_strlen($end))).$end;
    }

    /**
     * A preview at the length `AnnouncementPublishedNotification` already
     * truncates to. This is a preview only — announcements are not sent by SMS.
     */
    private function shorten(string $body): string
    {
        $flat = Str::of($body)->squish()->toString();

        return Str::limit($flat, (int) config('ai.announcements.short_length', 240));
    }
}
