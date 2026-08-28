<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SYNAPSE platform configuration
    |--------------------------------------------------------------------------
    |
    | Central place for SaaS settings so values are never hard-coded across
    | the codebase.
    |
    */

    // Free-trial length for newly onboarded schools.
    'trial_days' => env('SYNAPSE_TRIAL_DAYS', 14),

    // Default billing currency (Cameroon-first).
    'currency' => env('SYNAPSE_CURRENCY', 'XAF'),

    // Available payment providers (mock is dev-only).
    'payment_providers' => ['mock', 'mtn_momo', 'orange_money', 'card'],

    // Default country for generated documents.
    'country' => env('SYNAPSE_COUNTRY', 'Cameroon'),

    /*
    |--------------------------------------------------------------------------
    | Grading
    |--------------------------------------------------------------------------
    |
    | `scale` is the maximum mark (20 in the Francophone system, 100 for the
    | Anglophone one). Mentions are matched from the highest threshold down.
    |
    */
    'grading' => [
        'scale' => env('SYNAPSE_GRADE_SCALE', 20),
        'pass_mark' => env('SYNAPSE_PASS_MARK', 10),
        'mentions' => [
            ['min' => 18, 'label' => 'Excellent'],
            ['min' => 16, 'label' => 'Very Good'],
            ['min' => 14, 'label' => 'Good'],
            ['min' => 12, 'label' => 'Fairly Good'],
            ['min' => 10, 'label' => 'Average'],
            ['min' => 0, 'label' => 'Insufficient'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Documents
    |--------------------------------------------------------------------------
    */
    'documents' => [
        // Private disk: generated PDFs must never be publicly listable.
        'disk' => env('SYNAPSE_DOCUMENT_DISK', 'local'),

        // Public URL pattern used on the PDF footer for authenticity checks.
        'verification_url' => env('SYNAPSE_VERIFICATION_URL', env('APP_URL', 'http://localhost').'/verify'),
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS
    |--------------------------------------------------------------------------
    |
    | `log` writes to the application log (default, safe for dev). Configure a
    | provider below to send real messages.
    |
    */
    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
        'from' => env('SMS_FROM', 'SYNAPSE'),

        // Used to expand local numbers (6XXXXXXXX) into E.164 (+2376XXXXXXXX).
        'country_code' => env('SMS_COUNTRY_CODE', '237'),
    ],

    // Pagination defaults applied by the HandlesPagination concern.
    'pagination' => [
        'per_page' => env('SYNAPSE_PER_PAGE', 15),
        'max_per_page' => env('SYNAPSE_MAX_PER_PAGE', 100),
    ],

    // Days before expiry that a school starts receiving renewal reminders.
    'renewal_reminder_days' => [14, 7, 3, 1],

    // Tenant feature flags exposed through subscription plans.
    'features' => [
        'basic_academics',
        'report_cards',
        'document_management',
        'notifications',
        'custom_branding',
        'advanced_analytics',
    ],

];
