<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Services\Ai\StudentTutor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * The student AI study assistant.
 *
 * The conversation is stateless by design: the client sends its recent turns
 * with every request and keeps the full transcript for display. Nothing a
 * student says is stored, which keeps a free provider tier cheap, keeps
 * minors' chat off our servers, and makes "delete the conversation" a
 * purely client-side act.
 *
 * Two ceilings protect the free provider quota: a named per-minute limiter
 * (`assistant`, registered in AppServiceProvider) and a per-student daily
 * counter, checked here before the provider is called.
 */
class AssistantController extends Controller
{
    public function __construct(
        private readonly StudentTutor $tutor,
    ) {}

    public function store(Request $request): JsonResponse
    {
        if (! $this->tutor->available()) {
            return response()->json([
                'message' => 'The AI study assistant is not available right now.',
            ], 503);
        }

        $data = $request->validate([
            'message' => ['required', 'string', 'max:'.config('ai.tutor.max_message_chars', 2000)],
            'history' => ['sometimes', 'array', 'max:'.config('ai.tutor.max_history', 12)],
            'history.*.role' => ['required', 'string', 'in:user,assistant'],
            'history.*.content' => ['required', 'string', 'max:'.config('ai.tutor.max_message_chars', 2000)],
        ]);

        $daily = (int) config('ai.tutor.per_day', 60);
        $key = 'assistant-daily:'.$request->user()->id;

        if (RateLimiter::tooManyAttempts($key, $daily)) {
            return response()->json([
                'message' => 'You have used up your study help for today. Come back tomorrow — or ask your teacher in the meantime.',
            ], 429);
        }

        /*
        | Oldest first, newest last, trimmed to the cap from the front so the
        | most recent context survives an over-long client transcript.
        */
        $history = collect($data['history'] ?? [])
            ->slice(-1 * (int) config('ai.tutor.max_history', 12))
            ->values()
            ->map(fn (array $turn) => [
                'role' => (string) $turn['role'],
                'content' => trim((string) $turn['content']),
            ])
            ->all();

        try {
            $reply = $this->tutor->reply(trim((string) $data['message']), $history);
        } catch (Throwable) {
            /*
            | The service has already logged the reason. A tutor that is down
            | must read as "try again later", never as an error page.
            */
            return response()->json([
                'message' => 'The AI study assistant could not answer just now. Please try again in a moment.',
            ], 503);
        }

        /*
        | Counted against the end of the local day, so "come back tomorrow"
        | means midnight, not this hour plus twenty-four. The floor keeps a
        | hit at the day's last second from expiring instantly.
        */
        RateLimiter::hit($key, max(60, (int) now()->secondsUntilEndOfDay()));

        return response()->json([
            'data' => [
                'reply' => $reply,
                'remaining' => max(0, $daily - RateLimiter::attempts($key)),
            ],
        ]);
    }
}
