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

// ERPLY Debug Route (outside API middleware for easier access)
Route::get('/erply-debug', function () {
    // Force load .env file
    $dotenv = Dotenv\Dotenv::createImmutable(base_path());
    $dotenv->load();
    
    $env = [
        'ERPLY_API_URL' => $_ENV['ERPLY_API_URL'] ?? env('ERPLY_API_URL', 'https://606950.erply.com/api/'),
        'ERPLY_USERNAME' => $_ENV['ERPLY_USERNAME'] ?? env('ERPLY_USERNAME', 'support@retailcare.com.au'),
        'ERPLY_PASSWORD' => str_repeat('*', strlen($_ENV['ERPLY_PASSWORD'] ?? env('ERPLY_PASSWORD', 'NF7c8XUFv0!C'))),
        'ERPLY_CLIENT_CODE' => $_ENV['ERPLY_CLIENT_CODE'] ?? env('ERPLY_CLIENT_CODE', '606950'),
    ];

    echo "=== ERPLY API Debug ===\n\n";
    echo "Environment Check:\n";
    echo "Base Path: " . base_path() . "\n";
    echo ".env file exists: " . (file_exists(base_path() . '/.env') ? 'YES' : 'NO') . "\n";
    echo ".env file readable: " . (is_readable(base_path() . '/.env') ? 'YES' : 'NO') . "\n\n";
    
    echo "Configuration:\n";
    foreach ($env as $key => $value) {
        echo "$key: $value\n";
    }
    echo "\n";

    // Test 1: Authentication
    echo "=== Test 1: Authentication ===\n";
    try {
        $apiUrl = $_ENV['ERPLY_API_URL'] ?? env('ERPLY_API_URL', 'https://606950.erply.com/api/');
        $username = $_ENV['ERPLY_USERNAME'] ?? env('ERPLY_USERNAME', 'support@retailcare.com.au');
        $password = $_ENV['ERPLY_PASSWORD'] ?? env('ERPLY_PASSWORD', 'NF7c8XUFv0!C');
        $clientCode = $_ENV['ERPLY_CLIENT_CODE'] ?? env('ERPLY_CLIENT_CODE', '606950');
        
        echo "Using API URL: $apiUrl\n";
        echo "Using Username: $username\n";
        echo "Using Client Code: $clientCode\n\n";
        
        // Test ERPLY API access and account status
        echo "=== ERPLY Account Status Check ===\n";
        
        // Test 1: Check if API is accessible
        echo "Testing API accessibility...\n";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        echo "API Base URL Status: $httpCode\n";
        echo "API Response: " . substr($response, 0, 200) . "...\n\n";
        
        // Test 2: Try different ERPLY API versions
        $apiVersions = [
            'https://606950.erply.com/api/',
            'https://606950.erply.com/api/v1/',
            'https://606950.erply.com/api/v2/',
            'https://api.erply.com/api/',
            'https://api.erply.com/v1/',
            'https://api.erply.com/v2/'
        ];
        
        echo "=== Testing Different ERPLY API Versions ===\n";
        
        foreach ($apiVersions as $index => $versionUrl) {
            echo "Testing API Version " . ($index + 1) . ": $versionUrl\n";
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $versionUrl . 'login');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                'request' => json_encode([
                    'username' => $username,
                    'password' => $password,
                    'clientCode' => $clientCode
                ])
            ]));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json'
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            echo "  HTTP Status: $httpCode\n";
            echo "  Response: " . substr($response, 0, 200) . "...\n\n";
            
            if ($httpCode == 200) {
                $data = json_decode($response, true);
                if ($data['status']['responseStatus'] !== 'error') {
                    echo "  SUCCESS! This API version works.\n";
                    break;
                }
            }
        }
        
        // Test 3: Check account status without authentication
        echo "=== Account Status Check ===\n";
        
        // Try to get account info (some ERPLY APIs allow this without auth)
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl . 'getAccountInfo');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'request' => json_encode([
                'getAccountInfo' => []
            ])
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        echo "Account Info Status: $httpCode\n";
        echo "Account Info Response: " . substr($response, 0, 200) . "...\n\n";
        
        // Test 4: Try with different client codes
        echo "=== Testing Different Client Codes ===\n";
        
        $possibleClientCodes = [
            $clientCode,
            '606950',
            'retailcare',
            'support@retailcare.com.au',
            ''
        ];
        
        foreach ($possibleClientCodes as $index => $testClientCode) {
            echo "Testing Client Code " . ($index + 1) . ": '" . ($testClientCode ?: 'EMPTY') . "'\n";
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiUrl . 'login');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                'request' => json_encode([
                    'username' => $username,
                    'password' => $password,
                    'clientCode' => $testClientCode
                ])
            ]));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json'
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            echo "  HTTP Status: $httpCode\n";
            echo "  Response: " . substr($response, 0, 200) . "...\n\n";
            
            if ($httpCode == 200) {
                $data = json_decode($response, true);
                if ($data['status']['responseStatus'] !== 'error') {
                    echo "  SUCCESS! Client code '$testClientCode' works.\n";
                    break;
                }
            }
        }
        
        echo "\n=== Final Troubleshooting Steps ===\n";
        echo "Since credentials are confirmed correct, please check:\n\n";
        echo "1. ERPLY Admin Panel → Settings → API Access\n";
        echo "   - Ensure API access is ENABLED\n";
        echo "   - Check if IP restrictions are blocking this server\n";
        echo "   - Verify API key/secret if required\n\n";
        echo "2. ERPLY Admin Panel → Users → support@retailcare.com.au\n";
        echo "   - Check if account is ACTIVE (not locked)\n";
        echo "   - Verify user has API permissions\n";
        echo "   - Check if account is expired\n\n";
        echo "3. ERPLY Admin Panel → Account Settings\n";
        echo "   - Verify client code is correct and active\n";
        echo "   - Check if account has API subscription\n";
        echo "   - Verify account is not suspended\n\n";
        echo "4. Server Environment:\n";
        echo "   - Server IP: " . $_SERVER['SERVER_ADDR'] ?? 'Unknown' . "\n";
        echo "   - Check if this IP is whitelisted in ERPLY\n";
        echo "   - Verify SSL certificate is valid\n\n";
        echo "5. Contact ERPLY Support:\n";
        echo "   - Account ID: 606950\n";
        echo "   - Username: support@retailcare.com.au\n";
        echo "   - Error Code: 1009 (Authentication failed)\n";
        echo "   - Server IP: " . $_SERVER['SERVER_ADDR'] ?? 'Unknown' . "\n";
            
    } catch (Exception $e) {
        echo " Exception: " . $e->getMessage() . "\n";
    }

    echo "\n=== Debug Complete ===\n";
    return response('Debug complete');
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

    // Xero ERPLY API Routes
    Route::prefix('erply')->group(function () {
        // ERPLY Customer Sync Routes
        Route::match(['GET', 'POST'], '/sync/customers', [ErplyController::class, 'syncCustomers']);
        Route::match(['GET', 'POST'], '/sync/customers-incremental', [ErplyController::class, 'syncCustomersIncremental']);
        Route::match(['GET', 'POST'], '/sync/products-incremental', [ErplyController::class, 'syncProductsIncremental']);
        Route::post('/send/customers', [ErplyController::class, 'sendCustomersToErply']);
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
        Route::get('/sync-status', [ErplyController::class, 'getSyncStatus']);
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
