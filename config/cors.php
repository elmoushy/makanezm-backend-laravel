<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | This config is required for production when your frontend (Vercel, etc.)
    | calls the API on a different domain. Without it, browsers may block
    | requests that send the Authorization header (Bearer tokens).
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Comma-separated list in .env, or "*" for all.
    'allowed_origins' => array_filter(array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', env('FRONTEND_URL', '*'))))),

    'allowed_origins_patterns' => [],

    // Important: allow Authorization header for Bearer tokens.
    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // If you only use Bearer tokens, keep this false.
    // If you use Sanctum SPA cookies, set true and DO NOT use "*" origins.
    'supports_credentials' => false,

];
