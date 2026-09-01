<?php

namespace Tests\Unit;

use App\Models\Announcement;
use App\Services\Ai\AnnouncementBrief;
use App\Services\Ai\DeterministicAnnouncementDrafter;
use Tests\TestCase;

/**
 * The deterministic announcement drafter.
 *
 * This is not a stand-in awaiting a model: it is what runs on a fresh install
 * with no key, and what a provider falls back to. So it has to be genuinely
 * useful — correct language, correct register, correct structure — and it has
 * to be honest about what it cannot do (it cannot translate prose the author
 * typed in the other language).
 */
class DeterministicAnnouncementDrafterTest extends TestCase
{
    private function drafter(): DeterministicAnnouncementDrafter
    {
        return new DeterministicAnnouncementDrafter();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function brief(array $overrides = []): AnnouncementBrief
    {
        return AnnouncementBrief::fromRequest(array_merge([
            'subject' => 'the mid-term examinations begin on Monday',
            'key_points' => ['Bring your student card', 'Phones are not permitted'],
            'date_text' => 'Monday 14 September',
            'venue' => 'the main hall',
            'audience' => Announcement::AUDIENCE_ALL,
            'tone' => AnnouncementBrief::TONE_FORMAL,
            'locale' => 'en',
        ], $overrides), 'en');
    }

    // ---------------------------------------------------------------- language

    public function test_the_english_brief_is_framed_in_english(): void
    {
        $draft = $this->drafter()->draft($this->brief());

        $this->assertStringContainsString('Dear students and staff,', $draft['body']);
        $this->assertStringContainsString('This is to inform you that', $draft['body']);
        $this->assertStringContainsString('Thank you for your attention.', $draft['body']);
    }

    public function test_the_french_brief_is_framed_in_french(): void
    {
        $draft = $this->drafter()->draft($this->brief(locale: 'fr'));

        $this->assertStringContainsString('Chers élèves et membres du personnel,', $draft['body']);
        $this->assertStringContainsString('Nous vous informons que', $draft['body']);
        $this->assertStringContainsString('Merci de votre attention.', $draft['body']);
        $this->assertStringNotContainsString('Dear students', $draft['body']);
    }

    public function test_the_caller_locale_wins_over_an_unknown_one(): void
    {
        $brief = AnnouncementBrief::fromRequest(
            ['subject' => 'a test', 'locale' => 'de'],
            'fr',
        );

        $this->assertSame('fr', $brief->locale);
        $this->assertStringContainsString('Chers', $this->drafter()->draft($brief)['body']);
    }

    // ---------------------------------------------------------------- audience

    public function test_the_salutation_follows_the_audience(): void
    {
        $cases = [
            Announcement::AUDIENCE_STUDENTS => 'Dear students,',
            Announcement::AUDIENCE_TEACHERS => 'Dear colleagues,',
            Announcement::AUDIENCE_ALL => 'Dear students and staff,',
        ];

        foreach ($cases as $audience => $salutation) {
            $draft = $this->drafter()->draft($this->brief(audience: $audience));

            $this->assertStringContainsString($salutation, $draft['body'], $audience);
        }
    }

    public function test_the_french_salutation_follows_the_audience(): void
    {
        $draft = $this->drafter()->draft($this->brief(
            audience: Announcement::AUDIENCE_TEACHERS,
            locale: 'fr',
        ));

        $this->assertStringContainsString('Chers collègues,', $draft['body']);
    }

    // ------------------------------------------------------------------ tone

    public function test_a_friendly_brief_reads_differently_from_a_formal_one(): void
    {
        $formal = $this->drafter()->draft($this->brief(tone: AnnouncementBrief::TONE_FORMAL));
        $friendly = $this->drafter()->draft($this->brief(tone: AnnouncementBrief::TONE_FRIENDLY));

        $this->assertNotSame($formal['body'], $friendly['body']);
        $this->assertStringContainsString('A quick note about', $friendly['body']);
        $this->assertStringContainsString('Thanks — see you there!', $friendly['body']);
    }

    // -------------------------------------------------------------- structure

    public function test_key_points_are_rendered_as_a_list(): void
    {
        $draft = $this->drafter()->draft($this->brief());

        $this->assertStringContainsString('Key details:', $draft['body']);
        $this->assertStringContainsString('• Bring your student card', $draft['body']);
        $this->assertStringContainsString('• Phones are not permitted', $draft['body']);
    }

    public function test_a_brief_with_no_key_points_omits_the_heading(): void
    {
        $draft = $this->drafter()->draft($this->brief(key_points: []));

        $this->assertStringNotContainsString('Key details:', $draft['body']);
        $this->assertStringNotContainsString('Points importants', $draft['body']);
    }

    public function test_blank_key_points_are_dropped_rather_than_rendered_empty(): void
    {
        $brief = AnnouncementBrief::fromRequest([
            'subject' => 'a test',
            'key_points' => ['   ', '', 'A real point'],
        ], 'en');

        $this->assertSame(['A real point'], $brief->keyPoints);

        $draft = $this->drafter()->draft($brief);

        $this->assertSame(1, substr_count($draft['body'], '•'));
    }

    /**
     * @return array<int, array{0: array<string, mixed>, 1: string, 2: list<string>}>
     */
    public static function logisticsProvider(): array
    {
        return [
            'both' => [
                ['date_text' => 'Monday 14 September', 'venue' => 'the main hall'],
                'It takes place on Monday 14 September at the main hall.',
                ['It takes place on', 'at the main hall'],
            ],
            'date only' => [
                ['date_text' => 'Monday 14 September', 'venue' => null],
                'It takes place on Monday 14 September.',
                ['It takes place on Monday 14 September.'],
            ],
            'venue only' => [
                ['date_text' => null, 'venue' => 'the main hall'],
                'It takes place at the main hall.',
                ['It takes place at the main hall.'],
            ],
            'neither' => [
                ['date_text' => null, 'venue' => null],
                '',
                [],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  list<string>  $expected
     *
     * @dataProvider logisticsProvider
     */
    public function test_logistics_are_worded_for_what_was_actually_supplied(
        array $fields,
        string $sentence,
        array $expected,
    ): void {
        $draft = $this->drafter()->draft($this->brief($fields));

        foreach ($expected as $fragment) {
            $this->assertStringContainsString($fragment, $draft['body']);
        }

        if ($sentence === '') {
            $this->assertStringNotContainsString('It takes place', $draft['body']);
        }
    }

    public function test_french_logistics_use_french_prepositions(): void
    {
        $draft = $this->drafter()->draft($this->brief(locale: 'fr'));

        $this->assertStringContainsString('Cela aura lieu le Monday 14 September, à the main hall.', $draft['body']);
    }

    public function test_the_action_line_appears_only_when_there_is_an_action(): void
    {
        $with = $this->drafter()->draft($this->brief(action_required: 'Reply by Friday'));
        $without = $this->drafter()->draft($this->brief(action_required: null));

        $this->assertStringContainsString('Action required: Reply by Friday', $with['body']);
        $this->assertStringNotContainsString('Action required', $without['body']);
    }

    public function test_the_french_action_line_is_french(): void
    {
        $draft = $this->drafter()->draft($this->brief(
            action_required: 'Répondez avant vendredi',
            locale: 'fr',
        ));

        $this->assertStringContainsString('Action requise : Répondez avant vendredi', $draft['body']);
    }

    public function test_the_title_is_the_subject_with_a_capital(): void
    {
        $draft = $this->drafter()->draft($this->brief(subject: '  library hours change next week  '));

        $this->assertSame('Library hours change next week', $draft['title']);
    }

    // ----------------------------------------------------------------- limits

    public function test_the_short_form_fits_the_notification_ceiling(): void
    {
        $draft = $this->drafter()->draft($this->brief(key_points: [
            str_repeat('A fairly long point about arrangements. ', 20),
        ]));

        $this->assertLessThanOrEqual(
            240,
            mb_strlen($draft['short_body']),
            $draft['short_body'],
        );

        // The short form is one line: a preview, not a paragraph.
        $this->assertStringNotContainsString("\n", $draft['short_body']);
    }

    public function test_the_body_never_exceeds_what_publish_would_accept(): void
    {
        $draft = $this->drafter()->draft($this->brief(key_points: [
            str_repeat('x ', 4000),
        ]));

        $this->assertLessThanOrEqual(5000, mb_strlen($draft['body']));
    }

    // ---------------------------------------------------------------- privacy

    public function test_the_payload_carries_no_school_or_recipient_identity(): void
    {
        $brief = $this->brief();

        $encoded = (string) json_encode($brief->payload());

        foreach (['school', 'author', 'recipient', 'user_id', 'email'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $encoded, "payload leaks {$forbidden}");
        }
    }

    // ------------------------------------------------------------ normalising

    public function test_an_unknown_audience_falls_back_to_everyone(): void
    {
        $brief = AnnouncementBrief::fromRequest([
            'subject' => 'a test',
            'audience' => 'parents',
        ], 'en');

        $this->assertSame(Announcement::AUDIENCE_ALL, $brief->audience);
    }

    public function test_an_unknown_tone_falls_back_to_formal(): void
    {
        $brief = AnnouncementBrief::fromRequest(['subject' => 'a test', 'tone' => 'savage'], 'en');

        $this->assertSame(AnnouncementBrief::TONE_FORMAL, $brief->tone);
    }

    public function test_a_non_string_locale_defaults_to_english(): void
    {
        $brief = AnnouncementBrief::fromRequest(['subject' => 'a test', 'locale' => null], null);

        $this->assertSame('en', $brief->locale);
    }
}
