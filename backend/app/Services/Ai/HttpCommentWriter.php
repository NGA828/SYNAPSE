<?php

namespace App\Services\Ai;

use App\Contracts\CommentWriter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * An OpenAI-compatible chat-completions writer.
 *
 * Three properties matter more than the prompt:
 *
 * - **It cannot see the student.** The payload is the evidence object, which
 *   holds numbers and subject names only. There is no name, matricule or school
 *   in scope to leak.
 * - **It cannot compute.** The prompt asks the model to phrase figures that are
 *   already in the payload and forbids introducing any number not present.
 * - **It cannot break a report card.** Any failure — timeout, auth, malformed
 *   JSON, empty text — falls through to the deterministic writer.
 */
class HttpCommentWriter implements CommentWriter
{
    public function __construct(
        private readonly DeterministicCommentWriter $fallback,
    ) {}

    public function name(): string
    {
        return 'http';
    }

    public function write(CommentEvidence $evidence): string
    {
        if (! config('ai.enabled') || ! config('ai.key') || ! config('ai.model')) {
            return $this->fallback->write($evidence);
        }

        try {
            $text = $this->complete($evidence);
        } catch (Throwable $exception) {
            return $this->degrade($exception, $evidence);
        }

        $text = $this->constrain($text);

        return $text === '' ? $this->fallback->write($evidence) : $text;
    }

    private function complete(CommentEvidence $evidence): string
    {
        $response = Http::withToken((string) config('ai.key'))
            ->timeout((int) config('ai.timeout', 15))
            ->connectTimeout((int) config('ai.connection_timeout', 5))
            ->acceptJson()
            ->post(config('ai.base_url').'/chat/completions', [
                'model' => config('ai.model'),
                'temperature' => 0.4,
                'messages' => [
                    ['role' => 'system', 'content' => $this->systemPrompt($evidence)],
                    ['role' => 'user', 'content' => $this->payload($evidence)],
                ],
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('Provider returned HTTP '.$response->status());
        }

        return trim((string) $response->json('choices.0.message.content', ''));
    }

    private function systemPrompt(CommentEvidence $evidence): string
    {
        $language = $evidence->locale === 'fr' ? 'French' : 'English';

        return <<<PROMPT
        You write one report-card comment for a school in Cameroon.

        Rules:
        - Write in {$language}.
        - Use ONLY the figures given. Never invent, round differently, or add a number.
        - Do not compute anything: the average, rank and mention are already correct.
        - Name the subjects given, exactly as spelled.
        - One paragraph, at most 60 words, no headings, no bullet points.
        - Address the record, not the person: you have not been told who the student is.
        - Be specific and kind. Do not praise vaguely and do not scold.
        PROMPT;
    }

    /**
     * Serialised evidence. `pseudonymise` is on by default and the evidence
     * object carries no identity anyway; the flag exists so a host can prove
     * what leaves the server by reading one config value.
     */
    private function payload(CommentEvidence $evidence): string
    {
        $facts = [
            'overall_average' => $evidence->average,
            'scale' => $evidence->scale,
            'pass_mark' => $evidence->passMark,
            'mention' => $evidence->mention,
            'rank' => $evidence->hasMeaningfulRank() ? $evidence->rank : null,
            'class_size' => $evidence->hasMeaningfulRank() ? $evidence->classSize : null,
            'subjects' => $evidence->subjects,
            'subjects_below_pass_mark' => $evidence->failing,
        ];

        if (config('ai.pseudonymise', true)) {
            $facts['note'] = 'No student identity is provided and none may be implied.';
        }

        return json_encode($facts, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * Enforce the length ceiling locally rather than trusting the model to
     * respect it, and strip anything that is not plain prose.
     */
    private function constrain(string $text): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? '');

        $limit = (int) config('ai.max_words', 60);
        $words = preg_split('/\s+/u', $text) ?: [];

        if (count($words) > $limit) {
            $text = rtrim(implode(' ', array_slice($words, 0, $limit)), ",;:");
            $text = Str::finish($text, '.');
        }

        $maxLength = (int) config('synapse.comments.max_length', 400);

        return Str::limit($text, $maxLength, '');
    }

    private function degrade(Throwable $exception, CommentEvidence $evidence): string
    {
        if (! config('ai.fallback_on_error', true)) {
            throw $exception;
        }

        Log::warning('AI comment generation fell back to the deterministic writer.', [
            'reason' => $exception->getMessage(),
        ]);

        return $this->fallback->write($evidence);
    }
}
