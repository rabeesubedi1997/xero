<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\ErplyCustomer;
use App\Models\ErplyProduct;
use App\Models\ErplyProductVariation;

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

    public function authenticate()
    {
        try {
            // Use correct ERPLY API format based on documentation
            $response = Http::asForm()->timeout($this->timeout)->post($this->baseUrl . 'login', [
                'username' => $this->username,
                'password' => $this->password,
                'clientCode' => $this->clientCode
            ]);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('ERPLY Authentication Response', [
                    'response' => $data,
                    'status' => $response->status()
                ]);
                
                // Check for different session key locations
                $sessionKey = $data['session'] ?? $data['session_key'] ?? $data['sessionKey'] ?? $data['session_token'] ?? $data['token'] ?? null;
                
                Log::info('ERPLY Session Key Found', [
                    'session_key' => $sessionKey ? substr($sessionKey, 0, 10) . '...' : 'NULL'
                ]);
                
                return $sessionKey;
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

    public function getCustomers($page = 1, $limit = 100)
    {
        try {
            $sessionKey = $this->authenticate();
            if (!$sessionKey) {
                Log::error('ERPLY: No session key available');
                return [];
            }

            Log::info('ERPLY: Attempting to get customers with session key', [
                'session_key' => substr($sessionKey, 0, 10) . '...',
                'page' => $page,
                'limit' => $limit
            ]);

            // Use correct ERPLY API format based on documentation
            $response = Http::asForm()->timeout($this->timeout)
                ->withHeaders([
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Accept' => 'application/json'
                ])
                ->post($this->baseUrl . 'customers', [
                    'session' => $sessionKey,
                    'request' => json_encode([
                        'getCustomers' => [
                            'page' => $page,
                            'limit' => $limit
                        ]
                    ])
                ]);

            Log::info('ERPLY: Customers API Response', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                // Check for records array (ERPLY format)
                $customers = $data['records'] ?? $data['data'] ?? $data['customers'] ?? [];
                
                Log::info('ERPLY Customers Retrieved', [
                    'page' => $page,
                    'limit' => $limit,
                    'count' => count($customers)
                ]);
                
                return $customers;
            }

            Log::error('ERPLY Customers API Error', [
                'status' => $response->status(),
                'body' => $response->body()
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

    public function getProducts($page = 1, $limit = 100)
    {
        try {
            $sessionToken = $this->authenticate();
            if (!$sessionToken) {
                throw new \Exception('Failed to authenticate with ERPLY');
            }

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $sessionToken,
                    'Content-Type' => 'application/json'
                ])
                ->get($this->baseUrl . 'products', [
                    'page' => $page,
                    'limit' => $limit
                ]);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('ERPLY Products Retrieved', [
                    'page' => $page,
                    'limit' => $limit,
                    'count' => count($data['data'] ?? [])
                ]);
                return $data['data'] ?? [];
            }

            Log::error('ERPLY Products API Error', [
                'status' => $response->status(),
                'body' => $response->body()
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

    public function getProductMatrix($page = 1, $limit = 100)
    {
        try {
            $sessionToken = $this->authenticate();
            if (!$sessionToken) {
                throw new \Exception('Failed to authenticate with ERPLY');
            }

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $sessionToken,
                    'Content-Type' => 'application/json'
                ])
                ->get($this->baseUrl . 'product-matrices', [
                    'page' => $page,
                    'limit' => $limit
                ]);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('ERPLY Product Matrices Retrieved', [
                    'page' => $page,
                    'limit' => $limit,
                    'count' => count($data['data'] ?? [])
                ]);
                return $data['data'] ?? [];
            }

            Log::error('ERPLY Product Matrices API Error', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return [];
        } catch (\Exception $e) {
            Log::error('ERPLY Product Matrices Exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    public function getProductVariations($matrixId, $page = 1, $limit = 100)
    {
        try {
            $sessionToken = $this->authenticate();
            if (!$sessionToken) {
                throw new \Exception('Failed to authenticate with ERPLY');
            }

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $sessionToken,
                    'Content-Type' => 'application/json'
                ])
                ->get($this->baseUrl . 'product-matrices/' . $matrixId . '/variations', [
                    'page' => $page,
                    'limit' => $limit
                ]);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('ERPLY Product Variations Retrieved', [
                    'matrix_id' => $matrixId,
                    'page' => $page,
                    'limit' => $limit,
                    'count' => count($data['data'] ?? [])
                ]);
                return $data['data'] ?? [];
            }

            Log::error('ERPLY Product Variations API Error', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return [];
        } catch (\Exception $e) {
            Log::error('ERPLY Product Variations Exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    public function syncCustomersToDatabase()
    {
        try {
            $customers = $this->getCustomers();
            $syncedCount = 0;
            $errorCount = 0;

            foreach ($customers as $customerData) {
                try {
                    ErplyCustomer::updateOrCreate(
                        ['erply_customer_id' => $customerData['id']],
                        [
                            'name' => $customerData['name'] ?? '',
                            'email' => $customerData['email'] ?? '',
                            'phone' => $customerData['phone'] ?? '',
                            'address' => $customerData['address'] ?? '',
                            'city' => $customerData['city'] ?? '',
                            'country' => $customerData['country'] ?? '',
                            'erply_data' => json_encode($customerData),
                            'sync_status' => 'pending'
                        ]
                    );
                    $syncedCount++;
                } catch (\Exception $e) {
                    $errorCount++;
                    Log::error('Failed to sync customer', [
                        'customer_id' => $customerData['id'] ?? 'unknown',
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
            Log::error('ERPLY Customer Sync Exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'total' => 0,
                'synced' => 0,
                'errors' => 1
            ];
        }
    }

    public function syncProductsToDatabase()
    {
        try {
            $matrices = $this->getProductMatrix();
            $syncedCount = 0;
            $errorCount = 0;

            foreach ($matrices as $matrixData) {
                try {
                    ErplyProduct::updateOrCreate(
                        ['erply_product_id' => $matrixData['id']],
                        [
                            'erply_matrix_id' => $matrixData['matrix_id'] ?? null,
                            'name' => $matrixData['name'] ?? '',
                            'sku' => $matrixData['sku'] ?? '',
                            'description' => $matrixData['description'] ?? '',
                            'price' => $matrixData['price'] ?? 0,
                            'cost' => $matrixData['cost'] ?? 0,
                            'type' => 'matrix',
                            'erply_data' => json_encode($matrixData),
                            'sync_status' => 'pending'
                        ]
                    );
                    $syncedCount++;

                    // Sync variations for this matrix
                    $variations = $this->getProductVariations($matrixData['id']);
                    foreach ($variations as $variationData) {
                        try {
                            ErplyProductVariation::updateOrCreate(
                                ['erply_variation_id' => $variationData['id']],
                                [
                                    'erply_matrix_id' => $matrixData['id'],
                                    'erply_product_id' => $variationData['product_id'] ?? null,
                                    'name' => $variationData['name'] ?? '',
                                    'sku' => $variationData['sku'] ?? '',
                                    'price' => $variationData['price'] ?? 0,
                                    'cost' => $variationData['cost'] ?? 0,
                                    'attributes' => json_encode($variationData['attributes'] ?? []),
                                    'erply_data' => json_encode($variationData),
                                    'sync_status' => 'pending'
                                ]
                            );
                            $syncedCount++;
                        } catch (\Exception $e) {
                            $errorCount++;
                            Log::error('Failed to sync variation', [
                                'variation_id' => $variationData['id'] ?? 'unknown',
                                'matrix_id' => $matrixData['id'],
                                'error' => $e->getMessage()
                            ]);
                        }
                    }
                } catch (\Exception $e) {
                    $errorCount++;
                    Log::error('Failed to sync product matrix', [
                        'matrix_id' => $matrixData['id'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ]);
                }
            }

            Log::info('ERPLY Product Sync Completed', [
                'total_matrices' => count($matrices),
                'synced_count' => $syncedCount,
                'error_count' => $errorCount
            ]);

            return [
                'total' => count($matrices),
                'synced' => $syncedCount,
                'errors' => $errorCount
            ];
        } catch (\Exception $e) {
            Log::error('ERPLY Product Sync Exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'total' => 0,
                'synced' => 0,
                'errors' => 1
            ];
        }
    }
}
