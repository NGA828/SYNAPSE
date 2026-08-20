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
