<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
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

     'jubelio' => [
        'base_url' => env('JUBELIO_BASE_URL', 'https://api2.jubelio.com'),
        'email' => env('JUBELIO_EMAIL'),
        'password' => env('JUBELIO_PASSWORD'),
        'webhook_secret' => env('JUBELIO_WEBHOOK_SECRET'),
        'location_id' => env('JUBELIO_LOCATION_ID'),
        'token_ttl_minutes' => 660, // token berlaku 12 jam, simpan 11 jam
        'timeout' => 30,
        'retries' => 3,
        'retry_sleep_ms' => 1000,
    ],

];
