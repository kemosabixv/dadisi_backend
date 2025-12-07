<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'pesapal' => [
        'environment' => env('PESAPAL_ENVIRONMENT', 'sandbox'),
        'sandbox_url' => env('PESAPAL_API_URL_SANDBOX'),
        'live_url' => env('PESAPAL_API_URL_LIVE'),
        'consumer_key' => env('PESAPAL_CONSUMER_KEY'),
        'consumer_secret' => env('PESAPAL_CONSUMER_SECRET'),
        'callback_url' => env('PESAPAL_CALLBACK_URL'),
        'webhook_url' => env('PESAPAL_WEBHOOK_URL'),
    ],

    'exchange_rate_api' => [
        'key' => env('EXCHANGE_RATE_API_KEY'),
        'base_url' => env('EXCHANGE_RATE_API_BASE_URL', 'https://v6.exchangerate-api.com/v6/'),
    ],

];
