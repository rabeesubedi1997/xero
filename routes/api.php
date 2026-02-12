<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\XeroAuthController;
use App\Http\Controllers\AccountController;

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
Route::get('/test', function (Request $request) {

    $headers = $request->headers->all();
    echo "test";
    dd($headers);
 
});

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
});
