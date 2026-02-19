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
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'erply' => [
        'api_url' => env('ERPLY_API_URL', 'https://606950.erply.com/api/'),
        'username' => env('ERPLY_USERNAME', 'support@retailcare.com.au'),
        'password' => env('ERPLY_PASSWORD', 'NF7c8XUFv0!C'),
        'client_code' => env('ERPLY_CLIENT_CODE', '606950'),
        'session_timeout' => env('ERPLY_SESSION_TIMEOUT', 3600),
        'batch_size' => env('ERPLY_BATCH_SIZE', 100),
        'rate_limit' => env('ERPLY_RATE_LIMIT', 60),
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

    'xero' => [
        'client_id' => env('XERO_CLIENT_ID'),
        'client_secret' => env('XERO_CLIENT_SECRET'),
        'redirect_uri' => env('XERO_REDIRECT_URI'),
        'scope' => env('XERO_SCOPE'),
    ],

];
