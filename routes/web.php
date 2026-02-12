<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'message' => 'Laravel Xero API',
        'endpoints' => [
            'oauth_connect' => '/api/oauth/connect',
            'oauth_callback' => '/api/oauth/callback',
            'oauth_tenants' => '/api/oauth/tenants',
            'accounts' => '/api/accounts'
        ]
    ]);
});
