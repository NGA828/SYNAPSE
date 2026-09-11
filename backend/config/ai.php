<?php

/*
|--------------------------------------------------------------------------
| AI assistance
|--------------------------------------------------------------------------
|
| The platform's AI use is deliberately narrow: it *phrases* numbers that
| GradeService has already computed. It never derives an average, a rank or a
| mention, because those appear on a legal document and are already correct.
|
| The default driver is `deterministic`. Nothing in the product requires an
| external model, and with no key configured the deterministic writer produces
| the same evidence-based comments it always does — so a fresh install works
| and a provider outage degrades instead of failing.
|
*/

return [

    /*
    | Master switch. When false, the deterministic writer is used regardless of
    | the driver below. Lets a host disable outbound AI calls entirely.
    */
    'enabled' => (bool) env('AI_ENABLED', false),

    /*
    | `deterministic` — rule-based, offline, no data leaves the server.
    | `http`        — an OpenAI-compatible chat completions endpoint.
    */
    'driver' => env('AI_DRIVER', 'deterministic'),

    'model' => env('AI_MODEL'),

    'key' => env('AI_API_KEY'),

    'base_url' => rtrim((string) env('AI_BASE_URL', 'https://api.openai.com/v1'), '/'),

    /*
    | Seconds. Kept short: a report card that takes a minute to render is worse
    | than one with a slightly plainer comment.
    */
    'timeout' => (int) env('AI_TIMEOUT', 15),

    'connection_timeout' => (int) env('AI_CONNECT_TIMEOUT', 5),

    /*
    | Hard ceiling on draft length, enforced on the generated text rather than
    | merely requested of the model.
    */
    'max_words' => (int) env('AI_MAX_WORDS', 60),

    /*
    | On any provider failure, fall back to the deterministic writer instead of
    | surfacing the error. A report card must always render.
    */
    'fallback_on_error' => (bool) env('AI_FALLBACK_ON_ERROR', true),

    /*
    | Student records are minors' data. When true (the default) the evidence
    | sent to a provider carries only the numeric facts and subject names — no
    | name, no matricule, no school identifier.
    */
    'pseudonymise' => (bool) env('AI_PSEUDONYMISE', true),

    'announcements' => [

        /*
        | Ceiling on a drafted announcement body, applied to our own output
        | rather than merely requested of the model. 5000 matches what
        | StoreAnnouncementRequest will accept on publish, so a draft can never
        | be longer than something the admin could actually publish.
        */
        'max_words' => (int) env('AI_ANNOUNCEMENT_MAX_WORDS', 180),

        'max_body_length' => 5000,

        /*
        | 240 matches the Str::limit already applied by
        | AnnouncementPublishedNotification::body(). Announcements are delivered
        | over bell and mail only — SMS is not wired for them — so the shortened
        | form is a length preview for the author, not a message anyone sends.
        */
        'short_length' => 240,
    ],

    /*
    |--------------------------------------------------------------------------
    | Student AI study assistant
    |--------------------------------------------------------------------------
    |
    | A general study tutor for the student portal: it explains concepts,
    | coaches revision and answers schoolwork questions. It is deliberately
    | *general* — it never receives school records, because a chat that can
    | discuss one pupil's marks is a data-leak surface as well as a fairness
    | problem. Every turn costs provider tokens, so the conversation is
    | stateless (the client sends recent turns) and both a per-minute and a
    | per-student daily ceiling apply.
    |
    | Groq's free tier speaks the OpenAI chat-completions dialect, so it works
    | through the same `http` driver as the writers above:
    |
    |   AI_BASE_URL=https://api.groq.com/openai/v1
    |   AI_MODEL=llama-3.3-70b-versatile
    |
    */

    'tutor' => [

        /*
        | Conversation turns remembered from the client, oldest last. The cap
        | bounds both the request size and how much transcript one turn can
        | carry; the client keeps full history for display.
        */
        'max_history' => (int) env('AI_TUTOR_MAX_HISTORY', 12),

        /*
        | Characters accepted for one student message or one history turn.
        | 2000 is roughly a page of text — far past what a chat question needs.
        */
        'max_message_chars' => (int) env('AI_TUTOR_MAX_MESSAGE_CHARS', 2000),

        /*
        | Soft target given to the model and the hard ceiling enforced on its
        | output. A chat bubble longer than ~350 words is a document, not help.
        */
        'max_reply_words' => (int) env('AI_TUTOR_MAX_REPLY_WORDS', 350),

        /*
        | Provider-side completion budget. Generous next to the word ceiling:
        | tokens overrun words for punctuated, accented or list-heavy text, and
        | truncation by the provider is not an error we want to handle.
        */
        'max_tokens' => (int) env('AI_TUTOR_MAX_TOKENS', 900),

        'temperature' => (float) env('AI_TUTOR_TEMPERATURE', 0.4),

        /*
        | Rate limits per student. The per-minute ceiling is a named limiter
        | (`assistant`) registered in AppServiceProvider and applied as route
        | middleware; the daily one is a counter in the controller that resets
        | at the end of the local day, so a free provider tier cannot be
        | exhausted in a burst or ground down over the day.
        */
        'per_minute' => (int) env('AI_TUTOR_PER_MINUTE', 6),

        'per_day' => (int) env('AI_TUTOR_PER_DAY', 60),
    ],

];
