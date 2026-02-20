<?php

namespace App\Services;

use App\Models\ErplyToken;
use App\Models\ErplyCustomer;
use App\Models\ErplyProduct;
use App\Models\SyncStatus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ErplyService
{
    private $baseUrl;
    private $username;
    private $password;
    private $clientCode;
    private $timeout;

    public function __construct()
    {
        $this->baseUrl = env('ERPLY_API_URL', 'https://606950.erply.com/api/');
        $this->username = env('ERPLY_USERNAME', 'support@retailcare.com.au');
        $this->password = env('ERPLY_PASSWORD', 'NF7c8XUFv0!C');
        $this->clientCode = env('ERPLY_CLIENT_CODE', '606950');
        $this->timeout = env('ERPLY_SESSION_TIMEOUT', 3600);
        
        // Ensure user is authorized and session key is stored in database
        $this->ensureUserAuthorized();
    }

    /**
     * Ensure user is authorized and session key is stored in database
     */
    private function ensureUserAuthorized(): void
    {
        try {
            // First, ensure database connection
            Log::info('ERPLY: Checking database connection');
            
            // Test database connection
            $pdo = DB::connection()->getPdo();
            if (!$pdo) {
                Log::error('ERPLY: Database connection failed');
                throw new \Exception('Database connection failed');
            }
            
            Log::info('ERPLY: Database connection successful');
            
            // Generate session key first and store in database
            Log::info('ERPLY: Generating session key first');
            $sessionKey = $this->authenticate();
            // dd($sessionKey);
            
            if ($sessionKey) {
                Log::info('ERPLY: Session key generated and stored successfully', [
                    'session_key' => substr($sessionKey, 0, 10) . '...'
                ]);
            } else {
                Log::error('ERPLY: Failed to generate session key');
                throw new \Exception('Failed to generate ERPLY session key');
            }
            
        } catch (\Exception $e) {
            Log::error('ERPLY: Authorization check failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Authenticate with ERPLY API and store token
     */
    public function authenticate(): ?string
{
    try {
        $response = Http::asForm()->timeout($this->timeout)->post(
            $this->baseUrl,
            [
                'clientCode' => $this->clientCode,
                'username'   => $this->username,
                'password'   => $this->password,
                'request'    => 'verifyUser'
            ]
        );

        Log::info('ERPLY Login Response', [
            'status' => $response->status(),
            'body'   => $response->body()
        ]);

        if (!$response->successful()) {
            return null;
        }

        $data = $response->json();

        if (
            isset($data['status']['responseStatus']) &&
            $data['status']['responseStatus'] === 'ok' &&
            isset($data['records'][0]['sessionKey'])
        ) {
            $sessionKey = $data['records'][0]['sessionKey'];

            $this->storeToken($sessionKey, $data);

            return $sessionKey;
        }

        Log::error('ERPLY Login failed', ['response' => $data]);

        return null;

    } catch (\Exception $e) {
        Log::error('ERPLY Authentication Exception', [
            'error' => $e->getMessage()
        ]);

        return null;
    }
}
    /**
     * Store ERPLY token in database
     */
    private function storeToken(string $sessionKey, array $responseData): void
    {
        try {
            Log::info('ERPLY: Storing token in database', [
                'client_code' => $this->clientCode,
                'username' => $this->username,
                'session_key' => substr($sessionKey, 0, 10) . '...',
                'response_data' => $responseData
            ]);
            
            // Clean up old tokens first
            $deleted = ErplyToken::where('username', $this->username)
                              ->where('client_code', $this->clientCode)
                              ->delete();
            
            Log::info('ERPLY: Old tokens deleted', [
                'deleted_count' => $deleted
            ]);
            
            // Create new token with proper session and expiration time
            $token = ErplyToken::create([
                'client_code' => $this->clientCode,
                'username' => $this->username,
                'password' => $this->password,
                'session_key' => $sessionKey,
                'jwt_token' => $responseData['jwt'] ?? null,
                'expires_at' => Carbon::now()->addHours(1), // 1 hour expiry
                'last_used_at' => Carbon::now()
            ]);
            
            Log::info('ERPLY: Token stored successfully', [
                'token_id' => $token->id,
                'session_key' => substr($sessionKey, 0, 10) . '...',
                'expires_at' => $token->expires_at
            ]);
            
        } catch (\Exception $e) {
            Log::error('ERPLY: Failed to store token', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Get or create valid ERPLY token
     */
    public function getValidToken(): ?string
    {
        try {
            Log::info('ERPLY: Getting valid token from database', [
                'username' => $this->username,
                'client_code' => $this->clientCode
            ]);
            
            // Always get the most recent token from database
            $token = ErplyToken::where('username', $this->username)
                            ->where('client_code', $this->clientCode)
                            ->orderBy('created_at', 'desc')
                            ->first();
            
            if ($token && $token->session_key) {
                Log::info('ERPLY: Found stored session key in database', [
                    'token_id' => $token->id,
                    'session_key' => substr($token->session_key, 0, 10) . '...',
                    'expires_at' => $token->expires_at,
                    'is_expired' => $token->expires_at->isPast()
                ]);
                
                // Mark as used
                $token->last_used_at = Carbon::now();
                $token->save();
                
                return $token->session_key;
            }
            
            Log::error('ERPLY: No session key found in database');
            return null;
            
        } catch (\Exception $e) {
            Log::error('ERPLY: Failed to get valid token', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Make authenticated API request using your approach
     */
    private function makeAuthenticatedRequest(string $request, array $parameters = []): array
{
    try {
        $sessionKey = $this->getValidToken();

        if (!$sessionKey) {
            throw new \Exception('No valid ERPLY session available');
        }

        $requestParams = array_merge($parameters, [
            'clientCode'  => $this->clientCode,
            'sessionKey'  => $sessionKey,
            'request'     => $request,
            'responseType'=> 'json'
        ]);

        Log::info('ERPLY: Correct API Request', [
            'params' => $requestParams
        ]);

        $response = Http::asForm()
            ->timeout($this->timeout)
            ->post($this->baseUrl, $requestParams);

        Log::info('ERPLY: API Response', [
            'status' => $response->status(),
            'body'   => $response->body()
        ]);

        if (!$response->successful()) {
            return [
                'success' => false,
                'error'   => 'HTTP Error',
                'status'  => $response->status()
            ];
        }

        $data = $response->json();

        if (($data['status']['responseStatus'] ?? '') === 'ok') {
            return [
                'success' => true,
                'data'    => $data['records'] ?? [],
                'status'  => $data['status'],
                'response'=> $data
            ];
        }

        return [
            'success' => false,
            'error'   => $data['status']['errorCode'] ?? 'Unknown error',
            'status'  => $data['status'],
            'response'=> $data
        ];

    } catch (\Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}
    public function getCustomers(int $page = 1, int $limit = 100, bool $debug = false, ?string $changedSince = null, bool $autoSync = false): array
{
    try {
        Log::info('ERPLY: Getting customers', [
            'page' => $page,
            'limit' => $limit,
            'changedSince' => $changedSince,
            'autoSync' => $autoSync
        ]);

        // If autoSync is enabled and no changedSince provided, get it from sync_status
        if ($autoSync && !$changedSince) {
            $syncStatus = SyncStatus::getOrCreate('customer');
            $changedSince = $syncStatus->getChangedSinceDate();
            
            Log::info('ERPLY: Using changedSince from sync_status', [
                'changedSince' => $changedSince,
                'last_sync_date' => $syncStatus->last_sync_date
            ]);
        }

        // Ensure valid session
        $sessionKey = $this->getValidToken();
        if (!$sessionKey) {
            Log::warning('ERPLY: No valid session, authenticating...');
            $sessionKey = $this->authenticate();
            if (!$sessionKey) {
                Log::error('ERPLY: Authentication failed, cannot fetch customers');
                return [];
            }
        }

        // Build request parameters
        $params = [
            'pageNo' => $page,           // Correct parameter
            'recordsOnPage' => $limit    // Correct parameter
        ];

        // Add changedSince parameter if provided
        if ($changedSince) {
            $params['changedSince'] = $changedSince;
        }

        $result = $this->makeAuthenticatedRequest('getCustomers', $params);

        if ($result['success']) {
            $customers = $result['data'] ?? [];

            Log::info('ERPLY Customers Retrieved', [
                'page' => $page,
                'count' => count($customers),
                'records_total' => $result['response']['status']['recordsTotal'] ?? 0,
                'records_in_response' => $result['response']['status']['recordsInResponse'] ?? 0,
                'changedSince' => $changedSince
            ]);

            // If autoSync is enabled, process customers immediately
            if ($autoSync) {
                $this->processCustomersSync($customers, $changedSince);
            }

            if ($debug) {
                // Only show in debug mode
                Log::info('Debug mode: customers fetched', [
                    'customers' => $customers
                ]);
            }

            return $customers;
        }

        Log::error('ERPLY Customers API Error', [
            'error' => $result['error'] ?? 'Unknown error',
            'full_response' => $result
        ]);

        return [];

    } catch (\Exception $e) {
        Log::error('ERPLY Customers Exception', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return [];
    }
}

    /**
     * Get products from Erply API with changedSince support
     */
    public function getProducts(int $page = 1, int $limit = 100, bool $debug = false, ?string $changedSince = null, bool $autoSync = false): array
{
    try {
        Log::info('ERPLY: Getting products', [
            'page' => $page,
            'limit' => $limit,
            'changedSince' => $changedSince,
            'autoSync' => $autoSync
        ]);

        // If autoSync is enabled and no changedSince provided, get it from sync_status
        if ($autoSync && !$changedSince) {
            $syncStatus = SyncStatus::getOrCreate('product');
            $changedSince = $syncStatus->getChangedSinceDate();
            
            Log::info('ERPLY: Using changedSince from sync_status for products', [
                'changedSince' => $changedSince,
                'last_sync_date' => $syncStatus->last_sync_date
            ]);
        }

        // Ensure valid session
        $sessionKey = $this->getValidToken();
        if (!$sessionKey) {
            Log::warning('ERPLY: No valid session, authenticating...');
            $sessionKey = $this->authenticate();
            if (!$sessionKey) {
                Log::error('ERPLY: Authentication failed, cannot fetch products');
                return [];
            }
        }

        // Build request parameters
        $params = [
            'pageNo' => $page,           // Correct parameter
            'recordsOnPage' => $limit    // Correct parameter
        ];

        // Add changedSince parameter if provided
        if ($changedSince) {
            $params['changedSince'] = $changedSince;
        }

        $result = $this->makeAuthenticatedRequest('getProducts', $params);

        if ($result['success']) {
            $products = $result['data'] ?? [];

            Log::info('ERPLY Products Retrieved', [
                'page' => $page,
                'count' => count($products),
                'records_total' => $result['response']['status']['recordsTotal'] ?? 0,
                'records_in_response' => $result['response']['status']['recordsInResponse'] ?? 0,
                'changedSince' => $changedSince
            ]);

            // If autoSync is enabled, process products immediately
            if ($autoSync) {
                $this->processProductsSync($products, $changedSince);
            }

            if ($debug) {
                // Only show in debug mode
                Log::info('Debug mode: products fetched', [
                    'products' => $products
                ]);
            }

            return $products;
        }

        Log::error('ERPLY Products API Error', [
            'error' => $result['error'] ?? 'Unknown error',
            'full_response' => $result
        ]);

        return [];

    } catch (\Exception $e) {
        Log::error('ERPLY Products Exception', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return [];
    }
}

    /**
     * Process products sync - insert/update products and update sync status
     */
    private function processProductsSync(array $products, ?string $changedSince): array
{
    try {
        Log::info('ERPLY: Processing products sync', [
            'product_count' => count($products),
            'changedSince' => $changedSince
        ]);

        // Get or create sync status
        $syncStatus = SyncStatus::getOrCreate('product');
        $syncStatus->markInProgress();

        $syncedCount = 0;
        $updatedCount = 0;
        $insertedCount = 0;
        $errorCount = 0;
        $lastRecordTimestamp = null;

        foreach ($products as $productData) {
            try {
                $productID = $productData['productID'] ?? null;
                if (!$productID) {
                    Log::warning('Product missing productID', ['product_data' => $productData]);
                    continue;
                }

                // Prepare product data
                $productDataForDb = [
                    'erply_product_id' => $productID,
                    'name' => $productData['name'] ?? 'Unknown Product',
                    'description' => $productData['description'] ?? null,
                    'code' => $productData['code'] ?? null,
                    'type' => $productData['type'] ?? 'simple',
                    'price' => $productData['price'] ?? 0,
                    'cost' => $productData['cost'] ?? 0,
                    'vat_rate' => $productData['vatrate'] ?? 0,
                    'active' => ($productData['active'] ?? 1) == 1,
                    'sync_status' => 'pending',
                    'raw_erply_data' => json_encode($productData)
                ];

                // Check if product exists
                $existingProduct = ErplyProduct::where('erply_product_id', $productID)->first();

                if ($existingProduct) {
                    // Update existing product
                    $existingProduct->update($productDataForDb);
                    $updatedCount++;
                    
                    Log::info('ERPLY: Updated existing product', [
                        'product_id' => $productID,
                        'name' => $productData['name'] ?? 'Unknown Product'
                    ]);
                } else {
                    // Insert new product
                    ErplyProduct::create($productDataForDb);
                    $insertedCount++;
                    
                    Log::info('ERPLY: Inserted new product', [
                        'product_id' => $productID,
                        'name' => $productData['name'] ?? 'Unknown Product'
                    ]);
                }

                $syncedCount++;

                // Track latest timestamp from this batch
                if (isset($productData['timeModified'])) {
                    $recordTimestamp = Carbon::parse($productData['timeModified']);
                    if (!$lastRecordTimestamp || $recordTimestamp->gt($lastRecordTimestamp)) {
                        $lastRecordTimestamp = $recordTimestamp;
                    }
                } elseif (isset($productData['timeAdded'])) {
                    $recordTimestamp = Carbon::parse($productData['timeAdded']);
                    if (!$lastRecordTimestamp || $recordTimestamp->gt($lastRecordTimestamp)) {
                        $lastRecordTimestamp = $recordTimestamp;
                    }
                }

            } catch (\Exception $e) {
                $errorCount++;
                Log::error('Failed to process product', [
                    'product_id' => $productData['productID'] ?? 'unknown',
                    'error' => $e->getMessage(),
                    'product_data' => $productData
                ]);
            }
        }

        // Update sync status with timestamp of last processed record
        $finalSyncDate = $lastRecordTimestamp ? $lastRecordTimestamp : Carbon::now();
        $syncStatus->markSuccess($syncedCount);

        // Update last_sync_date to timestamp of last processed record
        $syncStatus->update(['last_sync_date' => $finalSyncDate]);

        Log::info('ERPLY: Product sync processing completed', [
            'total_products' => count($products),
            'synced_count' => $syncedCount,
            'updated_count' => $updatedCount,
            'inserted_count' => $insertedCount,
            'error_count' => $errorCount,
            'final_sync_date' => $finalSyncDate->format('Y-m-d H:i:s'),
            'changedSince' => $changedSince
        ]);

        return [
            'total' => count($products),
            'synced' => $syncedCount,
            'updated' => $updatedCount,
            'inserted' => $insertedCount,
            'errors' => $errorCount,
            'last_sync_date' => $finalSyncDate->format('Y-m-d H:i:s')
        ];

    } catch (\Exception $e) {
        // Mark sync as failed
        if (isset($syncStatus)) {
            $syncStatus->markFailed($e->getMessage());
        }

        Log::error('ERPLY: Product sync processing failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return [
            'total' => count($products),
            'synced' => 0,
            'updated' => 0,
            'inserted' => 0,
            'errors' => count($products),
            'error_message' => $e->getMessage()
        ];
    }
}

    /**
     * Incremental product sync using changedSince parameter
     */
    public function syncProductsIncremental(int $page = 1, int $limit = 100, bool $debug = false): array
{
    try {
        Log::info('Starting ERPLY incremental product sync', [
            'page' => $page,
            'limit' => $limit,
            'debug' => $debug
        ]);

        // Get or create sync status
        $syncStatus = SyncStatus::getOrCreate('product');
        $syncStatus->markInProgress();

        // Get changedSince date
        $changedSince = $syncStatus->getChangedSinceDate();

        Log::info('ERPLY: Using changedSince date for products', [
            'changedSince' => $changedSince,
            'last_sync_date' => $syncStatus->last_sync_date
        ]);

        // Fetch products with changedSince and autoSync
        $products = $this->getProducts($page, $limit, $debug, $changedSince, true);

        // Debug mode: just return fetched products
        if ($debug) {
            return [
                'total' => count($products),
                'debug' => true,
                'changedSince' => $changedSince,
                'products' => $products
            ];
        }

        if (empty($products)) {
            $syncStatus->markSuccess(0);
            return [
                'total' => 0,
                'synced' => 0,
                'errors' => 0,
                'changedSince' => $changedSince
            ];
        }

        // Products are already processed by getProducts with autoSync=true
        return [
            'total' => count($products),
            'synced' => count($products),
            'errors' => 0,
            'changedSince' => $changedSince
        ];

    } catch (\Exception $e) {
        // Mark sync as failed
        if (isset($syncStatus)) {
            $syncStatus->markFailed($e->getMessage());
        }

        Log::error('ERPLY Incremental Product Sync Failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        throw $e;
    }
}

public function syncCustomersToDatabase(int $page = 1, int $limit = 100, bool $debug = false): array
{
    try {
        Log::info('Starting ERPLY customer sync', [
            'page' => $page,
            'limit' => $limit,
            'debug' => $debug
        ]);

        // Fetch customers
        $customers = $this->getCustomers($page, $limit, $debug);

        // Debug mode: just return fetched customers
        if ($debug) {
            return [
                'total' => count($customers),
                'debug' => true,
                'customers' => $customers
            ];
        }

        if (empty($customers)) {
            return [
                'total' => 0,
                'synced' => 0,
                'errors' => 0
            ];
        }

        $syncedCount = 0;
        $errorCount = 0;

        foreach ($customers as $customerData) {
            try {
                $firstName = $customerData['firstName'] ?? '';
                $lastName = $customerData['lastName'] ?? '';
                $fullName = trim($firstName . ' ' . $lastName);
                if (empty($fullName)) {
                    $fullName = $customerData['companyName'] ?? 'Unknown Customer';
                }

                // Insert or update customer
                $customer = ErplyCustomer::updateOrCreate(
                    ['erply_customer_id' => $customerData['customerID']],
                    [
                        'name' => $fullName,
                        'first_name' => $firstName ?: null,
                        'last_name' => $lastName ?: null,
                        'company_name' => $customerData['companyName'] ?? null,
                        'email' => $customerData['email'] ?? null,
                        'phone' => $customerData['phone'] ?? null,
                        'mobile' => $customerData['mobile'] ?? null,
                        'address' => $customerData['address'] ?? null,
                        'city' => $customerData['city'] ?? null,
                        'state' => $customerData['state'] ?? null,
                        'post_code' => $customerData['postCode'] ?? null,
                        'country' => $customerData['country'] ?? null,
                        'sync_status' => 'pending',
                        'raw_erply_data' => json_encode($customerData)
                    ]
                );

                $syncedCount++;

            } catch (\Exception $e) {
                $errorCount++;
                Log::error('Failed to store customer', [
                    'customer_id' => $customerData['customerID'] ?? 'unknown',
                    'error' => $e->getMessage(),
                    'customer_data' => $customerData
                ]);
            }
        }

        Log::info('ERPLY Customer Sync Completed', [
            'total_customers' => count($customers),
            'synced_count' => $syncedCount,
            'error_count' => $errorCount
        ]);

        return [
            'total' => count($customers),
            'synced' => $syncedCount,
            'errors' => $errorCount
        ];

    } catch (\Exception $e) {
        Log::error('ERPLY Customer Sync Failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        throw $e;
    }
}

    private function storeCustomer(array $customerData): void
    {
        try {
            $customer = ErplyCustomer::updateOrCreate(
                ['erply_customer_id' => $customerData['customerID']],
                [
                    'first_name' => $customerData['firstName'] ?? null,
                    'last_name' => $customerData['lastName'] ?? null,
                    'company_name' => $customerData['companyName'] ?? null,
                    'email' => $customerData['email'] ?? null,
                    'phone' => $customerData['phone'] ?? null,
                    'mobile' => $customerData['mobile'] ?? null,
                    'address' => $customerData['address'] ?? null,
                    'city' => $customerData['city'] ?? null,
                    'country' => $customerData['country'] ?? null,
                    'sync_status' => 'pending',
                    'raw_erply_data' => json_encode($customerData)
                ]
            );
            
            Log::info('Customer stored successfully', [
                'erply_customer_id' => $customerData['customerID'],
                'customer_id' => $customer->id
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to store customer', [
                'customer_data' => $customerData,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Send customers to ERPLY (your approach)
     */
    public function sendCustomersToErply($limit = 50): array
    {
        try {
            Log::info('Starting send customers to ERPLY');
            
            // Get customers from database (like your approach)
            $customers = ErplyCustomer::where('erplyPending', 1)
                                     ->limit($limit)
                                     ->get();
            
            if ($customers->isEmpty()) {
                Log::info('All customers already synced to ERPLY');
                ErplyCustomer::where('erplyPending', 2)->update(['erplyPending' => 1]);
                return [
                    'success' => true,
                    'message' => 'All customers already synced to ERPLY',
                    'total' => 0,
                    'synced' => 0,
                    'errors' => 0
                ];
            }

            $bundleArray = [];
            foreach ($customers as $customer) {
                // Mark as processing
                $customer->erplyPending = 2;
                $customer->save();
                
                // Build request array (like your approach)
                $reqArray = [
                    'requestName' => 'saveCustomer',
                    'sessionKey' => $this->getValidToken(),
                    'clientCode' => $this->clientCode,
                    'firstName' => $customer->first_name,
                    'lastName' => $customer->last_name,
                    'groupID' => $customer->company_name ? 15 : 16, // Wholesale vs Retail
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                    'mobile' => $customer->mobile,
                    'countryID' => 25, // Australia
                    'trimInputData' => 1,
                    'attributeName1' => 'ShopifyID',
                    'attributeType1' => 'text',
                    'attributeValue1' => $customer->id,
                    'attributeName2' => 'Street',
                    'attributeType2' => 'text',
                    'attributeValue2' => $customer->address,
                    'attributeName3' => 'City',
                    'attributeType3' => 'text',
                    'attributeValue3' => $customer->city,
                    'attributeName4' => 'PostCode',
                    'attributeType4' => 'text',
                    'attributeValue4' => $customer->post_code ?? '',
                    'attributeName5' => 'State',
                    'attributeType5' => 'text',
                    'attributeValue5' => $customer->state ?? '',
                    'attributeName6' => 'Country',
                    'attributeType6' => 'text',
                    'attributeValue6' => $customer->country ?? '',
                ];
                
                // Check if customer exists in ERPLY
                $customerID = $this->checkCustomerExists($customer->email);
                if ($customerID != '') {
                    $reqArray['customerID'] = $customerID;
                }
                
                $bundleArray[] = $reqArray;
            }

            if (count($bundleArray) < 1) {
                return [
                    'success' => true,
                    'message' => 'All customers synced to ERPLY',
                    'total' => 0,
                    'synced' => 0,
                    'errors' => 0
                ];
            }

            // Send bulk request to ERPLY
            $result = $this->sendBulkRequest($bundleArray);
            
            $syncedCount = 0;
            $errorCount = 0;
            
            if ($result['success'] && isset($result['response']['requests'])) {
                foreach ($customers as $key => $customer) {
                    if (isset($result['response']['requests'][$key]) && 
                        $result['response']['requests'][$key]['status']['errorCode'] == 0) {
                        
                        $customer->erply_customer_id = $result['response']['requests'][$key]['records'][0]['customerID'];
                        $customer->erplyPending = 0;
                        $customer->save();
                        $syncedCount++;
                        
                    } else {
                        $customer->erply_error = json_encode($result['response']['requests'][$key] ?? []);
                        $customer->save();
                        $errorCount++;
                    }
                }
            }
            
            Log::info('Customers sent to ERPLY', [
                'total' => count($customers),
                'synced' => $syncedCount,
                'errors' => $errorCount
            ]);
            
            return [
                'success' => true,
                'message' => 'Customer sync to ERPLY completed',
                'total' => count($customers),
                'synced' => $syncedCount,
                'errors' => $errorCount
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to send customers to ERPLY', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'success' => false,
                'message' => 'Failed to send customers to ERPLY',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check if customer exists in ERPLY
     */
    private function checkCustomerExists($email): string
    {
        try {
            $result = $this->makeAuthenticatedRequest('getCustomers', [
                'searchEmail' => $email
            ]);
            
            if ($result['success'] && !empty($result['data'])) {
                Log::info('Customer exists in ERPLY', [
                    'email' => $email,
                    'customer_id' => $result['data'][0]['customerID'] ?? ''
                ]);
                return $result['data'][0]['customerID'] ?? '';
            }
            
            return '';
        } catch (\Exception $e) {
            Log::error('Failed to check customer exists', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            return '';
        }
    }

    /**
     * Send bulk request to ERPLY (your approach)
     */
    private function sendBulkRequest(array $requests): array
    {
        try {
            $parameters = [
                'lang' => 'eng',
                'responseType' => 'json',
                'sessionKey' => $this->getValidToken(),
                'requests' => json_encode($requests)
            ];
            
            Log::info('Sending bulk request to ERPLY', [
                'request_count' => count($requests),
                'session_key' => substr($parameters['sessionKey'], 0, 10) . '...'
            ]);
            
            // Use correct ERPLY API URL
            $response = Http::asForm()->timeout($this->timeout)
                ->withHeaders([
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Accept' => 'application/json'
                ])
                ->post('https://606950.erply.com/api/', $parameters);
            
            Log::info('ERPLY bulk response', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'data' => $data,
                    'response' => $data
                ];
            }
            
            return [
                'success' => false,
                'error' => 'Bulk request failed',
                'status' => $response->status(),
                'body' => $response->body()
            ];
            
        } catch (\Exception $e) {
            Log::error('Bulk request exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Incremental customer sync using changedSince parameter
     */
    public function syncCustomersIncremental(int $page = 1, int $limit = 100, bool $debug = false): array
    {
        try {
            Log::info('Starting ERPLY incremental customer sync', [
                'page' => $page,
                'limit' => $limit,
                'debug' => $debug
            ]);

            // Get or create sync status
            $syncStatus = SyncStatus::getOrCreate('customer');
            $syncStatus->markInProgress();

            // Get changedSince date
            $changedSince = $syncStatus->getChangedSinceDate();

            Log::info('ERPLY: Using changedSince date', [
                'changedSince' => $changedSince,
                'last_sync_date' => $syncStatus->last_sync_date
            ]);

            // Fetch customers with changedSince
            $customers = $this->getCustomers($page, $limit, $debug, $changedSince);

            // Debug mode: just return fetched customers
            if ($debug) {
                return [
                    'total' => count($customers),
                    'debug' => true,
                    'changedSince' => $changedSince,
                    'customers' => $customers
                ];
            }

            if (empty($customers)) {
                $syncStatus->markSuccess(0);
                return [
                    'total' => 0,
                    'synced' => 0,
                    'errors' => 0,
                    'changedSince' => $changedSince
                ];
            }

            $syncedCount = 0;
            $errorCount = 0;

            foreach ($customers as $customerData) {
                try {
                    $firstName = $customerData['firstName'] ?? '';
                    $lastName = $customerData['lastName'] ?? '';
                    $fullName = trim($firstName . ' ' . $lastName);
                    if (empty($fullName)) {
                        $fullName = $customerData['companyName'] ?? 'Unknown Customer';
                    }

                    // Check if customer exists and compare timestamps
                    $existingCustomer = ErplyCustomer::where('erply_customer_id', $customerData['customerID'])->first();
                    
                    $shouldUpdate = true;
                    if ($existingCustomer && isset($customerData['timeModified'])) {
                        $erplyModifiedTime = Carbon::parse($customerData['timeModified']);
                        $localModifiedTime = $existingCustomer->updated_at;
                        
                        // Only update if Erply record is newer
                        $shouldUpdate = $erplyModifiedTime->gt($localModifiedTime);
                    }

                    if ($shouldUpdate) {
                        // Insert or update customer
                        $customer = ErplyCustomer::updateOrCreate(
                            ['erply_customer_id' => $customerData['customerID']],
                            [
                                'name' => $fullName,
                                'first_name' => $firstName ?: null,
                                'last_name' => $lastName ?: null,
                                'company_name' => $customerData['companyName'] ?? null,
                                'email' => $customerData['email'] ?? null,
                                'phone' => $customerData['phone'] ?? null,
                                'mobile' => $customerData['mobile'] ?? null,
                                'address' => $customerData['address'] ?? null,
                                'city' => $customerData['city'] ?? null,
                                'state' => $customerData['state'] ?? null,
                                'post_code' => $customerData['postCode'] ?? null,
                                'country' => $customerData['country'] ?? null,
                                'sync_status' => 'pending',
                                'raw_erply_data' => json_encode($customerData)
                            ]
                        );

                        $syncedCount++;
                    }

                } catch (\Exception $e) {
                    $errorCount++;
                    Log::error('Failed to store customer', [
                        'customer_id' => $customerData['customerID'] ?? 'unknown',
                        'error' => $e->getMessage(),
                        'customer_data' => $customerData
                    ]);
                }
            }

            // Mark sync as successful
            $syncStatus->markSuccess($syncedCount);

            Log::info('ERPLY Incremental Customer Sync Completed', [
                'total_customers' => count($customers),
                'synced_count' => $syncedCount,
                'error_count' => $errorCount,
                'changedSince' => $changedSince
            ]);

            return [
                'total' => count($customers),
                'synced' => $syncedCount,
                'errors' => $errorCount,
                'changedSince' => $changedSince
            ];

        } catch (\Exception $e) {
            // Mark sync as failed
            if (isset($syncStatus)) {
                $syncStatus->markFailed($e->getMessage());
            }

            Log::error('ERPLY Incremental Customer Sync Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Get sync status for all entities
     */
    public function getSyncStatus(): array
    {
        try {
            $customerStatus = SyncStatus::getOrCreate('customer');
            $productStatus = SyncStatus::getOrCreate('product');

            return [
                'customers' => [
                    'last_sync_date' => $customerStatus->last_sync_date,
                    'last_sync_status' => $customerStatus->last_sync_status,
                    'total_records_synced' => $customerStatus->total_records_synced,
                    'needs_sync' => $customerStatus->needsSync(),
                    'error_message' => $customerStatus->error_message
                ],
                'products' => [
                    'last_sync_date' => $productStatus->last_sync_date,
                    'last_sync_status' => $productStatus->last_sync_status,
                    'total_records_synced' => $productStatus->total_records_synced,
                    'needs_sync' => $productStatus->needsSync(),
                    'error_message' => $productStatus->error_message
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get sync status', [
                'error' => $e->getMessage()
            ]);
            return [
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Process customers sync - insert/update customers and update sync status
     */
    private function processCustomersSync(array $customers, ?string $changedSince): array
    {
        try {
            Log::info('ERPLY: Processing customers sync', [
                'customer_count' => count($customers),
                'changedSince' => $changedSince
            ]);

            // Get or create sync status
            $syncStatus = SyncStatus::getOrCreate('customer');
            $syncStatus->markInProgress();

            $syncedCount = 0;
            $updatedCount = 0;
            $insertedCount = 0;
            $errorCount = 0;
            $lastRecordTimestamp = null;

            foreach ($customers as $customerData) {
                try {
                    $customerID = $customerData['customerID'] ?? null;
                    if (!$customerID) {
                        Log::warning('Customer missing customerID', ['customer_data' => $customerData]);
                        continue;
                    }

                    // Prepare customer data
                    $firstName = $customerData['firstName'] ?? '';
                    $lastName = $customerData['lastName'] ?? '';
                    $fullName = trim($firstName . ' ' . $lastName);
                    if (empty($fullName)) {
                        $fullName = $customerData['companyName'] ?? 'Unknown Customer';
                    }

                    $customerDataForDb = [
                        'name' => $fullName,
                        'first_name' => $firstName ?: null,
                        'last_name' => $lastName ?: null,
                        'company_name' => $customerData['companyName'] ?? null,
                        'email' => $customerData['email'] ?? null,
                        'phone' => $customerData['phone'] ?? null,
                        'mobile' => $customerData['mobile'] ?? null,
                        'address' => $customerData['address'] ?? null,
                        'city' => $customerData['city'] ?? null,
                        'state' => $customerData['state'] ?? null,
                        'post_code' => $customerData['postCode'] ?? null,
                        'country' => $customerData['country'] ?? null,
                        'sync_status' => 'pending',
                        'raw_erply_data' => json_encode($customerData)
                    ];

                    // Check if customer exists
                    $existingCustomer = ErplyCustomer::where('erply_customer_id', $customerID)->first();

                    if ($existingCustomer) {
                        // Update existing customer
                        $existingCustomer->update($customerDataForDb);
                        $updatedCount++;
                        
                        Log::info('ERPLY: Updated existing customer', [
                            'customer_id' => $customerID,
                            'name' => $fullName
                        ]);
                    } else {
                        // Insert new customer
                        $customerDataForDb['erply_customer_id'] = $customerID;
                        ErplyCustomer::create($customerDataForDb);
                        $insertedCount++;
                        
                        Log::info('ERPLY: Inserted new customer', [
                            'customer_id' => $customerID,
                            'name' => $fullName
                        ]);
                    }

                    $syncedCount++;

                    // Track latest timestamp from this batch
                    if (isset($customerData['timeModified'])) {
                        $recordTimestamp = Carbon::parse($customerData['timeModified']);
                        if (!$lastRecordTimestamp || $recordTimestamp->gt($lastRecordTimestamp)) {
                            $lastRecordTimestamp = $recordTimestamp;
                        }
                    } elseif (isset($customerData['timeAdded'])) {
                        $recordTimestamp = Carbon::parse($customerData['timeAdded']);
                        if (!$lastRecordTimestamp || $recordTimestamp->gt($lastRecordTimestamp)) {
                            $lastRecordTimestamp = $recordTimestamp;
                        }
                    }

                } catch (\Exception $e) {
                    $errorCount++;
                    Log::error('Failed to process customer', [
                        'customer_id' => $customerData['customerID'] ?? 'unknown',
                        'error' => $e->getMessage(),
                        'customer_data' => $customerData
                    ]);
                }
            }

            // Update sync status with the timestamp of the last processed record
            $finalSyncDate = $lastRecordTimestamp ? $lastRecordTimestamp : Carbon::now();
            $syncStatus->markSuccess($syncedCount);

            // Update last_sync_date to timestamp of last processed record
            $syncStatus->update(['last_sync_date' => $finalSyncDate]);

            Log::info('ERPLY: Customer sync processing completed', [
                'total_customers' => count($customers),
                'synced_count' => $syncedCount,
                'updated_count' => $updatedCount,
                'inserted_count' => $insertedCount,
                'error_count' => $errorCount,
                'final_sync_date' => $finalSyncDate->format('Y-m-d H:i:s'),
                'changedSince' => $changedSince
            ]);

            return [
                'total' => count($customers),
                'synced' => $syncedCount,
                'updated' => $updatedCount,
                'inserted' => $insertedCount,
                'errors' => $errorCount,
                'last_sync_date' => $finalSyncDate->format('Y-m-d H:i:s')
            ];

        } catch (\Exception $e) {
            // Mark sync as failed
            if (isset($syncStatus)) {
                $syncStatus->markFailed($e->getMessage());
            }

            Log::error('ERPLY: Customer sync processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'total' => count($customers),
                'synced' => 0,
                'updated' => 0,
                'inserted' => 0,
                'errors' => count($customers),
                'error_message' => $e->getMessage()
            ];
        }
    }
}
