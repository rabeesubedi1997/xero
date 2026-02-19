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
        $this->baseUrl = env('ERPLY_API_URL', 'https://api.erply.com/api/');
        $this->username = env('ERPLY_USERNAME', 'support@retailcare.com.au');
        $this->password = env('ERPLY_PASSWORD', 'NF7c8XUFv0!C');
        $this->clientCode = env('ERPLY_CLIENT_CODE', '606950');
        $this->timeout = env('ERPLY_SESSION_TIMEOUT', 3600);
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
                'api_url' => $this->baseUrl,
                'username' => $this->username,
                'client_code' => $this->clientCode
            ]);

            $response = Http::asForm()->timeout($this->timeout)->post($this->baseUrl . 'login', [
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
        // Clean up old tokens
        ErplyToken::where('username', $this->username)
                   ->where('client_code', $this->clientCode)
                   ->where('expires_at', '<', Carbon::now())
                   ->delete();
        
        // Create new token
        ErplyToken::create([
            'client_code' => $this->clientCode,
            'username' => $this->username,
            'password' => $this->password,
            'session_key' => $sessionKey,
            'jwt_token' => $responseData['jwt'] ?? null,
            'expires_at' => Carbon::now()->addHours(1), // 1 hour expiry
            'last_used_at' => Carbon::now()
        ]);
        
        Log::info('ERPLY: Token stored in database', [
            'session_key' => substr($sessionKey, 0, 10) . '...',
            'expires_at' => Carbon::now()->addHours(1)
        ]);
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
            
            $customers = $this->getCustomers(1, 100);
            
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
}
