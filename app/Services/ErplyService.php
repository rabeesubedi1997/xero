<?php

namespace App\Services;

use App\Models\ErplyToken;
use App\Models\ErplyCustomer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
            // Check if user already has valid token in database
            $existingToken = ErplyToken::where('username', $this->username)
                                    ->where('client_code', $this->clientCode)
                                    ->where('expires_at', '>', Carbon::now())
                                    ->first();
            
            if ($existingToken) {
                Log::info('ERPLY: User already authorized with valid token', [
                    'token_id' => $existingToken->id,
                    'expires_at' => $existingToken->expires_at
                ]);
                return;
            }
            
            // Authenticate and store new token
            Log::info('ERPLY: Authorizing user and storing session key', [
                'username' => $this->username,
                'client_code' => $this->clientCode
            ]);
            
            $sessionKey = $this->authenticate();
            
            if ($sessionKey) {
                Log::info('ERPLY: User authorized and session key stored', [
                    'session_key' => substr($sessionKey, 0, 10) . '...'
                ]);
            } else {
                Log::error('ERPLY: Failed to authorize user', [
                    'username' => $this->username,
                    'client_code' => $this->clientCode
                ]);
            }
            
        } catch (\Exception $e) {
            Log::error('ERPLY: Authorization check failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Get or create valid ERPLY token
     */
    public function getValidToken(): ?string
    {
        // Check for existing valid token
        $token = ErplyToken::getActiveToken($this->username, $this->clientCode);
        
        if ($token && $token->isValid()) {
            Log::info('Using existing valid ERPLY token', [
                'token_id' => $token->id,
                'expires_at' => $token->expires_at,
                'minutes_remaining' => $token->expires_at->diffInMinutes(Carbon::now())
            ]);
            
            // Mark as used
            $token->markAsUsed();
            return $token->session_key;
        }
        
        // Need new token
        Log::info('ERPLY: No valid token found, authenticating', [
            'username' => $this->username,
            'client_code' => $this->clientCode
        ]);
        
        return $this->authenticate();
    }

    /**
     * Authenticate with ERPLY API and store token
     */
    public function authenticate(): ?string
    {
        try {
            Log::info('ERPLY: Starting authentication', [
                'api_url' => 'https://606950.erply.com/api/',
                'username' => $this->username,
                'client_code' => $this->clientCode
            ]);

            $response = Http::asForm()->timeout($this->timeout)->post('https://606950.erply.com/api/login', [
                'username' => $this->username,
                'password' => $this->password,
                'clientCode' => $this->clientCode
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                // Check for different session key locations
                $sessionKey = $data['session'] ?? $data['session_key'] ?? $data['sessionKey'] ?? $data['session_token'] ?? $data['token'] ?? null;
                
                if ($sessionKey) {
                    // Store token in database
                    $this->storeToken($sessionKey, $data);
                    
                    Log::info('ERPLY: Authentication successful', [
                        'session_key' => substr($sessionKey, 0, 10) . '...',
                        'response_status' => $data['status']['responseStatus'] ?? 'unknown',
                        'records_total' => $data['status']['recordsTotal'] ?? 'unknown'
                    ]);
                    
                    return $sessionKey;
                }
            }

            Log::error('ERPLY Authentication Failed', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('ERPLY Authentication Exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
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
            
            // Clean up old tokens
            $deleted = ErplyToken::where('username', $this->username)
                              ->where('client_code', $this->clientCode)
                              ->where('expires_at', '<', Carbon::now())
                              ->delete();
            
            Log::info('ERPLY: Old tokens deleted', [
                'deleted_count' => $deleted
            ]);
            
            // Create new token
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
                'expires_at' => Carbon::now()->addHours(1)
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
     * Make authenticated API request
     */
    private function makeAuthenticatedRequest(string $endpoint, array $data = []): array
    {
        $sessionKey = $this->getValidToken();
        
        if (!$sessionKey) {
            throw new \Exception('No valid ERPLY session available');
        }

        $requestData = array_merge([
            'session' => $sessionKey
        ], $data);

        Log::info('ERPLY: Making authenticated request', [
            'endpoint' => $endpoint,
            'session_key' => substr($sessionKey, 0, 10) . '...',
            'request_data' => $requestData
        ]);

        $response = Http::asForm()->timeout($this->timeout)
            ->withHeaders([
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json'
            ])
            ->post($this->baseUrl . $endpoint, $requestData);

        Log::info('ERPLY: API Response', [
            'endpoint' => $endpoint,
            'status' => $response->status(),
            'body' => $response->body()
        ]);

        if ($response->successful()) {
            $responseData = $response->json();
            
            return [
                'success' => true,
                'data' => $responseData['records'] ?? $responseData['data'] ?? [],
                'status' => $responseData['status'] ?? [],
                'response' => $responseData
            ];
        }

        return [
            'success' => false,
            'error' => 'API request failed',
            'status' => $response->status(),
            'body' => $response->body()
        ];
    }

    public function getCustomers($page = 1, $limit = 100): array
    {
        try {
            // First, let's test if we can get any data at all
            Log::info('ERPLY: Testing basic API access');
            
            $result = $this->makeAuthenticatedRequest('customers', [
                'request' => json_encode([
                    'getCustomers' => [
                        'page' => $page,
                        'limit' => $limit
                    ]
                ])
            ]);

            if ($result['success']) {
                $customers = $result['data'] ?? [];
                
                Log::info('ERPLY Customers Retrieved', [
                    'page' => $page,
                    'limit' => $limit,
                    'count' => count($customers),
                    'records_total' => $result['response']['status']['recordsTotal'] ?? 0,
                    'records_in_response' => $result['response']['status']['recordsInResponse'] ?? 0,
                    'response_status' => $result['response']['status']['responseStatus'] ?? 'unknown'
                ]);
                
                // If no customers, let's try to get user info to verify API access
                if (empty($customers)) {
                    Log::info('ERPLY: No customers found, testing API access with verifyUser');
                    
                    $userResult = $this->makeAuthenticatedRequest('verifyUser', []);
                    
                    if ($userResult['success']) {
                        Log::info('ERPLY: API Access Verified', [
                            'user_data' => $userResult['data'] ?? [],
                            'response_status' => $userResult['response']['status']['responseStatus'] ?? 'unknown'
                        ]);
                    }
                }
                
                return $customers;
            }

            Log::error('ERPLY Customers API Error', [
                'error' => $result['error'] ?? 'Unknown error',
                'status' => $result['status'] ?? 'Unknown status',
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

    public function syncCustomersToDatabase(): array
    {
        try {
            Log::info('Starting ERPLY customer sync to database');
            
            $customers = $this->getCustomers(1, 10);
            
            Log::info('ERPLY Customers Retrieved', [
                'count' => count($customers),
                'customers_sample' => array_slice($customers, 0, 2)
            ]);
            
            $syncedCount = 0;
            $errorCount = 0;

            foreach ($customers as $customerData) {
                try {
                    $this->storeCustomer($customerData);
                    $syncedCount++;
                } catch (\Exception $e) {
                    $errorCount++;
                    Log::error('Failed to store customer', [
                        'customer_id' => $customerData['customerID'] ?? 'unknown',
                        'error' => $e->getMessage()
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
            
            if ($result['success'] && isset($result['requests'])) {
                foreach ($customers as $key => $customer) {
                    if (isset($result['requests'][$key]) && 
                        $result['requests'][$key]['status']['errorCode'] == 0) {
                        
                        $customer->erply_customer_id = $result['requests'][$key]['records'][0]['customerID'];
                        $customer->erplyPending = 0;
                        $customer->save();
                        $syncedCount++;
                        
                    } else {
                        $customer->erply_error = json_encode($result['requests'][$key] ?? []);
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
     * Send bulk request to ERPLY
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
}
