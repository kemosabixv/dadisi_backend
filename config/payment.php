<?php

return [
    // Default gateway driver: 'mock' for testing, 'pesapal' for production
    'gateway' => env('PAYMENT_GATEWAY', 'mock'),

    'pesapal' => [
        'consumer_key' => env('PESAPAL_CONSUMER_KEY', null),
        'consumer_secret' => env('PESAPAL_CONSUMER_SECRET', null),
        'environment' => env('PESAPAL_ENV', 'sandbox'), // sandbox|production

        // API 3.0 Base URLs
        'api_base' => env('PESAPAL_ENV', 'sandbox') === 'production' || env('PESAPAL_ENV', 'sandbox') === 'live'
            ? env('PESAPAL_API_URL_LIVE', 'https://pay.pesapal.com/v3/api')
            : env('PESAPAL_API_URL_SANDBOX', 'https://cybqa.pesapal.com/pesapalv3/api'),

        // Callback URL for customer redirect after payment
        'callback_url' => env('PESAPAL_CALLBACK_URL', config('app.url').'/payment/callback'),

        // IPN webhook configuration
        'ipn_url' => env('PESAPAL_IPN_URL', config('app.url').'/api/webhooks/pesapal'),
        'ipn_notification_type' => env('PESAPAL_IPN_TYPE', 'POST'), // POST or GET
        'webhook_secret' => env('PESAPAL_WEBHOOK_SECRET', null),
    ],
];
