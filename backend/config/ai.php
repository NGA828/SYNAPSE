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

];
