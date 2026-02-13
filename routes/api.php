<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\XeroAuthController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\UserController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('api')->group(function () {
    // Xero Authentication Routes
    Route::prefix('oauth')->group(function () {
        Route::get('/connect', [XeroAuthController::class, 'connect']);
        Route::get('/callback', [XeroAuthController::class, 'callback']);
        Route::get('/tenants', [XeroAuthController::class, 'tenants']);
        Route::get('/token-status', [XeroAuthController::class, 'tokenStatus']);
        Route::match(['get', 'post'], '/logout', [XeroAuthController::class, 'logout']);
    });

    // Xero Accounts API Routes
    Route::middleware('xero.tenant')->prefix('accounts')->group(function () {
        Route::get('/in', [AccountController::class, 'index']);
        Route::get('/{accountId}', [AccountController::class, 'show']);
        Route::post('/', [AccountController::class, 'store']);
        Route::put('/{accountId}', [AccountController::class, 'update']);
        Route::delete('/{accountId}', [AccountController::class, 'destroy']);
    });

    // Xero Users API Routes
    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::get('/{userId}', [UserController::class, 'show']);
    });
    
    // Xero Users API Routes (without middleware for testing)
    Route::prefix('users-test')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::get('/{userId}', [UserController::class, 'show']);
    });
    Route::get('/test', function (Request $request) {

    $headers = $request->headers->all();
    $urlParams = $request->query();
    
    // Check database tokens
    $tokens = \App\Models\XeroToken::all();
    $firstToken = \App\Models\XeroToken::first();
    
    // Generate auth URL if no tokens exist
    $authUrl = null;
    if ($tokens->isEmpty()) {
        $authUrl = "https://login.xero.com/identity/connect/authorize?" . http_build_query([
            'response_type' => 'code',
            'client_id' => config('services.xero.client_id'),
            'redirect_uri' => config('services.xero.redirect_uri'),
            'scope' => config('services.xero.scope')
        ]);
    }
    
    return response()->json([
        'headers' => $headers,
        'url_params' => $urlParams,
        'xero_tenant_id_header' => $request->header('Xero-Tenant-ID'),
        'xero_tenant_id_url' => $request->input('Xero-Tenant-ID'),
        'total_tokens' => $tokens->count(),
        'first_token' => $firstToken ? [
            'tenant_id' => $firstToken->tenant_id,
            'tenant_name' => $firstToken->tenant_name,
            'expires_at' => $firstToken->expires_at,
            'is_expired' => $firstToken->isExpired()
        ] : null,
        'all_tenant_ids' => $tokens->pluck('tenant_id')->toArray(),
        'needs_auth' => $tokens->isEmpty(),
        'auth_url' => $authUrl,
        'next_steps' => $tokens->isEmpty() ? [
            '1. Visit: ' . $authUrl,
            '2. Complete OAuth flow',
            '3. Tokens will be stored automatically'
        ] : 'Tokens exist - API ready'
    ]);

});

});
