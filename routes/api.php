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
        
        // Test correct ERPLY endpoint with proper parameters
        echo "Testing correct ERPLY endpoint: $apiUrl" . "login\n";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl . 'login');
        curl_setopt($ch, CURLOPT_POST, true);
        
        // Try different parameter formats based on ERPLY documentation
        $parameterSets = [
            // Format 1: Standard form data
            [
                'username' => $username,
                'password' => $password,
                'clientCode' => $clientCode
            ],
            // Format 2: With request wrapper
            [
                'request' => json_encode([
                    'username' => $username,
                    'password' => $password,
                    'clientCode' => $clientCode
                ])
            ],
            // Format 3: Direct login parameters
            [
                'user' => $username,
                'pass' => $password,
                'clientCode' => $clientCode
            ],
            // Format 4: ERPLY standard format
            [
                'username' => $username,
                'password' => $password,
                'client_code' => $clientCode
            ]
        ];
        
        foreach ($parameterSets as $index => $params) {
            echo "Testing parameter set " . ($index + 1) . ":\n";
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            echo "  HTTP Status: $httpCode\n";
            echo "  Response: " . substr($response, 0, 200) . "...\n\n";
            
            if ($httpCode == 200) {
                $data = json_decode($response, true);
                $sessionToken = $data['session'] ?? $data['session_token'] ?? $data['token'] ?? null;
                
                if ($sessionToken) {
                    echo "  ✅ SUCCESS! Session token: " . substr($sessionToken, 0, 10) . "...\n\n";
                    
                    // Test 2: Get Customers with this successful endpoint
                    echo "=== Test 2: Get Customers ===\n";
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $apiUrl . 'customers');
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                        'session' => $sessionToken,
                        'request' => json_encode([
                            'getCustomers' => [
                                'page' => 1,
                                'limit' => 10
                            ]
                        ])
                    ]));
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Content-Type: application/x-www-form-urlencoded'
                    ]);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                        
                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                        
                    echo "  HTTP Status: $httpCode\n";
                    echo "  Response: " . substr($response, 0, 200) . "...\n\n";
                        
                    $customerData = json_decode($response, true);
                    $customers = $customerData['data'] ?? $customerData['customers'] ?? [];
                        
                    echo "  Customers found: " . count($customers) . "\n";
                    
                    if (!empty($customers)) {
                        echo "  First customer data:\n";
                        print_r($customers[0]);
                    }
                    
                    // Success! Exit
                    break;
                }
            }
        }
            
    } catch (Exception $e) {
        echo "❌ Exception: " . $e->getMessage() . "\n";
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
