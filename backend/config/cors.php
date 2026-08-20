<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Phase 1 uses Bearer-token authentication, so credentials are disabled
    | and all origins are allowed for local development. Restrict
    | `allowed_origins` to your frontend domain in production.
    |
    */

    'paths' => ['api/*', 'login', 'logout', 'user', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
