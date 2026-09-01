<?php

namespace App\Services\Ai;

use App\Models\Announcement;

/**
 * The facts an announcement is built from.
 *
 * Deliberately a `final readonly` value object holding only what the
 * administrator typed as *content*: no school name, no sender name, no
 * recipient list. When this is serialised for a provider there is nothing
 * identifying in scope to leak — the same guarantee `CommentEvidence` gives for
 * report cards, and for the same reason.
 *
 * @property-read string $subject
 * @property-read list<string> $keyPoints
 * @property-read string|null $actionRequired
 * @property-read string|null $dateText
 * @property-read string|null $venue
 * @property-read string $audience
 * @property-read string $tone
 * @property-read string $locale
 */
final readonly class AnnouncementBrief
{
    public const TONE_FORMAL = 'formal';

    public const TONE_FRIENDLY = 'friendly';

    public const TONES = [self::TONE_FORMAL, self::TONE_FRIENDLY];

    /**
     * @param  array<int, string>  $keyPoints
     */
    public function __construct(
        public string $subject,
        public array $keyPoints = [],
        public ?string $actionRequired = null,
        public ?string $dateText = null,
        public ?string $venue = null,
        public string $audience = Announcement::AUDIENCE_ALL,
        public string $tone = self::TONE_FORMAL,
        public string $locale = 'en',
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromRequest(array $input, ?string $locale = null): self
    {
        $points = collect($input['key_points'] ?? [])
            ->map(fn ($point) => is_string($point) ? trim($point) : '')
            ->filter(fn (string $point) => $point !== '')
            ->values()
            ->all();

        return new self(
            subject: trim((string) ($input['subject'] ?? '')),
            keyPoints: $points,
            actionRequired: self::nullable($input['action_required'] ?? null),
            dateText: self::nullable($input['date_text'] ?? null),
            venue: self::nullable($input['venue'] ?? null),
            audience: in_array($input['audience'] ?? null, Announcement::AUDIENCES, true)
                ? $input['audience']
                : Announcement::AUDIENCE_ALL,
            tone: in_array($input['tone'] ?? null, self::TONES, true)
                ? $input['tone']
                : self::TONE_FORMAL,
            locale: str_starts_with(strtolower((string) ($locale ?? $input['locale'] ?? 'en')), 'fr') ? 'fr' : 'en',
        );
    }

    /**
     * Everything worth sending to a model. Kept explicit so adding a field here
     * is a decision rather than an accident.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'subject' => $this->subject,
            'key_points' => $this->keyPoints,
            'action_required' => $this->actionRequired,
            'date' => $this->dateText,
            'venue' => $this->venue,
            'audience' => $this->audience,
            'tone' => $this->tone,
            'language' => $this->locale === 'fr' ? 'French' : 'English',
        ];
    }

    public function isFrench(): bool
    {
        return $this->locale === 'fr';
    }

    public function isFriendly(): bool
    {
        return $this->tone === self::TONE_FRIENDLY;
    }

    private static function nullable(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
