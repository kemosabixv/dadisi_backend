<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost',
        'http://127.0.0.1',
        'http://localhost:3000',
        'https://dadisi-five.vercel.app',
        'https://dadisilab.com',
        'https://www.dadisilab.com',

        // Add the local dev server ports so the Scribe "Try it out" UI can call the API
        'http://127.0.0.1:8000',
        'http://localhost:8000',
        'https://api.dadisilab.com'
    ],

    // Allow localhost/127.0.0.1 with any port (useful for local dev environments)
    'allowed_origins_patterns' => [
        '/^https?:\/\/localhost(:[0-9]+)?$/',
        '/^https?:\/\/127\\.0\\.0\\.1(:[0-9]+)?$/'
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];
