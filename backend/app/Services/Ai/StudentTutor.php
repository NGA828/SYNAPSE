<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * The student AI study assistant.
 *
 * A general tutor for the student portal. It shares the `http` driver — any
 * OpenAI-compatible chat-completions endpoint, Groq's free tier included —
 * with the report-card writer and the announcement drafter, and it keeps the
 * same three guarantees those writers make:
 *
 * - **It knows no school records.** The only input is the conversation the
 *   student typed. No name, class, mark, timetable or school identifier is
 *   ever attached, so there is nothing about any pupil to leak.
 * - **It cannot overrun.** The reply is stripped and word-capped locally, the
 *   way the drafter constrains a draft — the model's idea of a length budget
 *   is a request, not a guarantee.
 * - **It cannot break the portal.** Unlike the writers there is no
 *   deterministic fallback for a conversation, so failures surface as a
 *   typed exception; the controller degrades to a friendly "unavailable"
 *   message instead of an error page.
 */
class StudentTutor
{
    /**
     * Whether the assistant may reach a provider at all. Same three
     * conditions gate every AI feature in the platform.
     */
    public function available(): bool
    {
        return (bool) config('ai.enabled')
            && config('ai.driver') === 'http'
            && (bool) config('ai.key')
            && (bool) config('ai.model');
    }

    /**
     * One conversational turn.
     *
     * @param  array<int, array{role: string, content: string}>  $history
     *         Recent turns, oldest first, already validated and capped.
     * @param  string  $message  The student's new message.
     * @return string The assistant's reply, plain text.
     */
    public function reply(string $message, array $history = []): string
    {
        if (! $this->available()) {
            throw new RuntimeException('The study assistant is not configured.');
        }

        try {
            $reply = $this->constrain($this->complete($message, $history));
        } catch (Throwable $exception) {
            Log::warning('Study assistant turn failed.', [
                'reason' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        if ($reply === '') {
            throw new RuntimeException('Provider returned an empty completion.');
        }

        return $reply;
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     */
    private function complete(string $message, array $history): string
    {
        $response = Http::withToken((string) config('ai.key'))
            ->timeout((int) config('ai.timeout', 15))
            ->connectTimeout((int) config('ai.connection_timeout', 5))
            ->acceptJson()
            ->post(config('ai.base_url').'/chat/completions', [
                'model' => config('ai.model'),
                'temperature' => (float) config('ai.tutor.temperature', 0.4),
                'max_tokens' => (int) config('ai.tutor.max_tokens', 900),
                'messages' => [
                    ['role' => 'system', 'content' => $this->systemPrompt()],
                    ...$history,
                    ['role' => 'user', 'content' => $message],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Provider returned HTTP '.$response->status());
        }

        return trim(strip_tags((string) $response->json('choices.0.message.content', '')));
    }

    /**
     * The persona and its guard rails. The tutor is general on purpose: it is
     * told what it does not know (this pupil's records) so it cannot pretend
     * otherwise, and it steers itself back to schoolwork.
     */
    private function systemPrompt(): string
    {
        $words = (int) config('ai.tutor.max_reply_words', 350);

        return <<<PROMPT
        You are the SYNAPSE study assistant, a patient study tutor for secondary-school students in Cameroon.

        Rules:
        - Reply in the language the student's latest message is written in — English or French. If it mixes both, mirror the dominant one.
        - Be warm and encouraging. Answer in at most {$words} words: short paragraphs or a short numbered list, no markdown headings, no links.
        - Teach step by step, with a concrete example where one helps. Guide the student towards the answer; if they ask you to do graded work for them, help them understand it instead of handing over a finished piece.
        - You are a general tutor. You have not been told anything about this student's school, class, subjects, marks or timetable — never guess or imply any of it, and never ask for personal details.
        - Stay on schoolwork and school life: subjects, study methods, exam preparation, motivation. If asked for anything else, say kindly that you can only help with school learning.
        - If you are not sure of something, say so plainly and suggest how to check.
        PROMPT;
    }

    /**
     * Enforce the ceiling locally. A chatty model would otherwise put a
     * document where a chat bubble belongs.
     */
    private function constrain(string $reply): string
    {
        $reply = trim(preg_replace('/[ \t]+/u', ' ', $reply) ?? '');
        $reply = trim(preg_replace("/\n{3,}/u", "\n\n", $reply) ?? '');

        $limit = (int) config('ai.tutor.max_reply_words', 350);
        $words = preg_split('/\s+/u', $reply) ?: [];

        if (count($words) > $limit) {
            $reply = rtrim(implode(' ', array_slice($words, 0, $limit)), ",;:");
            $reply = Str::finish($reply, '…');
        }

        return $reply;
    }
}
