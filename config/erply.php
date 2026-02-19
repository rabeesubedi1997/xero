<?php

return [
    'api_url' => env('ERPLY_API_URL', 'https://your-account.erply.com/api/'),
    'username' => env('ERPLY_USERNAME'),
    'password' => env('ERPLY_PASSWORD'),
    'client_code' => env('ERPLY_CLIENT_CODE'),
    'session_timeout' => env('ERPLY_SESSION_TIMEOUT', 3600),
    'batch_size' => env('ERPLY_BATCH_SIZE', 100),
    'rate_limit' => env('ERPLY_RATE_LIMIT', 60),
    'sync_intervals' => [
        'customers' => '0 */6 * *', // Every 6 hours
        'products' => '0 2 * *',     // Daily at 2 AM
        'full_sync' => '0 3 * *'      // Daily at 3 AM
    ]
];
