<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\XeroAuthController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ErplyController;
use App\Http\Controllers\CustomerController;

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

    // Xero ERPLY API Routes
    Route::prefix('erply')->group(function () {
        Route::match(['get', 'post'], '/sync/customers', [ErplyController::class, 'syncCustomers']);
        Route::post('/sync/products', [ErplyController::class, 'syncProducts']);
        Route::post('/sync/full', [ErplyController::class, 'syncFull']);
        Route::post('/sync/to-xero', [ErplyController::class, 'syncToXero']);
        Route::post('/sync/customers-to-xero', [ErplyController::class, 'syncCustomersToXero']);
        Route::post('/sync/products-to-xero', [ErplyController::class, 'syncProductsToXero']);
        Route::post('/sync/retry-failed', [ErplyController::class, 'retryFailed']);
        Route::get('/customers', [ErplyController::class, 'getCustomers']);
        Route::get('/products', [ErplyController::class, 'getProducts']);
        Route::get('/products/{id}/variations', [ErplyController::class, 'getVariations']);
        Route::get('/matrices', [ErplyController::class, 'getMatrices']);
        Route::get('/status', [ErplyController::class, 'getStatus']);
        Route::get('/sync-history', [ErplyController::class, 'getSyncHistory']);
        Route::post('/sync/retry/{id}', [ErplyController::class, 'retryFailed']);
    });

    // Xero Users API Routes
    Route::middleware('xero.tenant')->prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::get('/{userId}', [UserController::class, 'show']);
    });

    // Customer Management Routes
    Route::prefix('customers')->group(function () {
        Route::get('/', [CustomerController::class, 'index']);
        Route::get('/pending', [CustomerController::class, 'pending']);
        Route::get('/synced', [CustomerController::class, 'synced']);
        Route::match(['get', 'post'], '/sync/all', [CustomerController::class, 'syncAllPending']);
        Route::match(['get', 'post'], '/{customer}/sync', [CustomerController::class, 'syncToXero']);
        Route::post('/', [CustomerController::class, 'store']);
        Route::get('/{customer}', [CustomerController::class, 'show']);
        Route::put('/{customer}', [CustomerController::class, 'update']);
        Route::delete('/{customer}', [CustomerController::class, 'destroy']);
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
