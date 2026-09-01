<?php

namespace App\Services\Ai;

use App\Contracts\AnnouncementDrafter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * An OpenAI-compatible announcement drafter.
 *
 * Same three guarantees as `HttpCommentWriter`:
 *
 * - **It cannot see the school or the recipients.** The payload is
 *   `AnnouncementBrief::payload()` — the administrator's content and nothing
 *   identifying.
 * - **It cannot invent facts.** The brief's dates, venues and key points are the
 *   only facts; the prompt forbids adding any that are absent.
 * - **It cannot break publishing.** Any failure falls through to the
 *   deterministic drafter, which always returns usable text.
 */
class HttpAnnouncementDrafter implements AnnouncementDrafter
{
    public function __construct(
        private readonly DeterministicAnnouncementDrafter $fallback,
    ) {}

    public function name(): string
    {
        return 'http';
    }

    /**
     * @return array{title: string, body: string, short_body: string}
     */
    public function draft(AnnouncementBrief $brief): array
    {
        if (! config('ai.enabled') || ! config('ai.key') || ! config('ai.model')) {
            return $this->fallback->draft($brief);
        }

        try {
            $result = $this->constrain($this->complete($brief));
        } catch (Throwable $exception) {
            return $this->degrade($exception, $brief);
        }

        // An empty or malformed completion is a failure, not a draft.
        return ($result['title'] === '' || $result['body'] === '')
            ? $this->fallback->draft($brief)
            : $result;
    }

    /**
     * @return array{title: string, body: string}
     */
    private function complete(AnnouncementBrief $brief): array
    {
        $response = Http::withToken((string) config('ai.key'))
            ->timeout((int) config('ai.timeout', 15))
            ->connectTimeout((int) config('ai.connection_timeout', 5))
            ->acceptJson()
            ->post(config('ai.base_url').'/chat/completions', [
                'model' => config('ai.model'),
                'temperature' => 0.5,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $this->systemPrompt($brief)],
                    ['role' => 'user', 'content' => $this->payload($brief)],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Provider returned HTTP '.$response->status());
        }

        $content = trim((string) $response->json('choices.0.message.content', ''));

        if ($content === '') {
            throw new RuntimeException('Provider returned an empty completion.');
        }

        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Provider completion was not valid JSON.');
        }

        return [
            'title' => trim(strip_tags((string) ($decoded['title'] ?? ''))),
            'body' => trim(strip_tags((string) ($decoded['body'] ?? ''))),
        ];
    }

    private function systemPrompt(AnnouncementBrief $brief): string
    {
        $language = $brief->isFrench() ? 'French' : 'English';
        $register = $brief->isFriendly()
            ? 'warm and plain, the way a form teacher would write'
            : 'formal and courteous, the way a school administration would write';

        $words = (int) config('ai.announcements.max_words', 180);

        return <<<PROMPT
        You draft one school announcement for a school in Cameroon.

        Rules:
        - Write in {$language}, in a {$register} register.
        - Reply with a JSON object of exactly two keys: "title" and "body".
        - Use ONLY the facts given. Never invent a date, a time, a venue, a name or a rule.
        - If a fact is absent, leave it out. Do not fill the gap with something plausible.
        - Keep every proper noun, date and figure exactly as written in the input.
        - The body is at most {$words} words. Plain paragraphs; no markdown, no headings.
        - You have not been told the school's name or any recipient's name; do not imply either.
        PROMPT;
    }

    private function payload(AnnouncementBrief $brief): string
    {
        $facts = $brief->payload();

        if (config('ai.pseudonymise', true)) {
            $facts['note'] = 'No school or recipient identity is provided and none may be implied.';
        }

        return (string) json_encode($facts, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * Enforce the ceilings locally. A model that overruns the word budget or
     * emits HTML would otherwise produce a draft the publish endpoint rejects.
     *
     * @param  array{title: string, body: string}  $result
     * @return array{title: string, body: string, short_body: string}
     */
    private function constrain(array $result): array
    {
        $title = Str::limit(preg_replace('/\s+/u', ' ', $result['title']) ?? '', 255, '');

        $body = trim(preg_replace('/\r\n|\r/u', "\n", strip_tags($result['body'])) ?? '');

        $limit = (int) config('ai.announcements.max_words', 180);
        $words = preg_split('/\s+/u', $body) ?: [];

        if (count($words) > $limit) {
            $body = rtrim(implode(' ', array_slice($words, 0, $limit)), ",;:");
            $body = Str::finish($body, '.');
        }

        /*
        | Bounded by hand rather than with Str::limit, which appends the end
        | marker after truncating and so can return one character more than the
        | publish endpoint's max:5000 will accept.
        */
        $bodyLimit = (int) config('ai.announcements.max_body_length', 5000);

        if (mb_strlen($body) > $bodyLimit) {
            $body = rtrim(mb_substr($body, 0, $bodyLimit - 1)).'…';
        }

        return [
            'title' => $title,
            'body' => $body,
            'short_body' => Str::limit(
                Str::of($body)->squish()->toString(),
                (int) config('ai.announcements.short_length', 240),
            ),
        ];
    }

    /**
     * @return array{title: string, body: string, short_body: string}
     */
    /**
     * Fall back with the administrator's own brief, so the degraded draft is
     * still about the announcement they asked for.
     *
     * @return array{title: string, body: string, short_body: string}
     */
    private function degrade(Throwable $exception, AnnouncementBrief $brief): array
    {
        if (! config('ai.fallback_on_error', true)) {
            throw $exception;
        }

        Log::warning('Announcement drafting fell back to the deterministic writer.', [
            'reason' => $exception->getMessage(),
        ]);

        return $this->fallback->draft($brief);
    }
}
