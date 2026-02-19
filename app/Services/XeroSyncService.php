<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use App\Models\ErplyCustomer;
use App\Models\ErplyProduct;
use App\Models\ErplyProductVariation;
use XeroAPI\XeroPHP\Api\AccountingApi;
use XeroAPI\XeroPHP\Configuration;
use XeroAPI\XeroPHP\Models\Accounting\Contact;
use XeroAPI\XeroPHP\Models\Accounting\Account;
use GuzzleHttp\Client;

class XeroSyncService
{
    private $accountingApi;
    private $config;
    private $xeroService;

    public function __construct()
    {
        $this->xeroService = new XeroService();
        $this->config = Configuration::getDefaultConfiguration();
    }

    private function getAccountingApi($tenantId)
    {
        $token = $this->xeroService->getValidToken($tenantId);
        $this->config->setAccessToken($token->access_token);
        return new AccountingApi(new Client(), $this->config);
    }

    public function syncCustomersToXero($tenantId = null)
    {
        try {
            if (!$tenantId) {
                $firstToken = \App\Models\XeroToken::first();
                if (!$firstToken) {
                    throw new \Exception('No Xero tokens found');
                }
                $tenantId = $firstToken->tenant_id;
            }

            $pendingCustomers = ErplyCustomer::where('sync_status', 'pending')->get();
            $syncedCount = 0;
            $errorCount = 0;

            foreach ($pendingCustomers as $customer) {
                try {
                    $xeroCustomer = $this->createXeroCustomer($customer, $tenantId);
                    
                    $customer->update([
                        'xero_customer_id' => $xeroCustomer->getContactID(),
                        'sync_status' => 'synced_to_xero',
                        'last_synced_at' => now(),
                        'xero_data' => json_encode($xeroCustomer)
                    ]);
                    
                    $syncedCount++;
                    
                    Log::info('Customer synced to Xero', [
                        'erply_customer_id' => $customer->erply_customer_id,
                        'xero_customer_id' => $xeroCustomer->getContactID()
                    ]);
                } catch (\Exception $e) {
                    $errorCount++;
                    $customer->update([
                        'sync_status' => 'failed',
                        'xero_sync_error' => $e->getMessage()
                    ]);
                    
                    Log::error('Failed to sync customer to Xero', [
                        'erply_customer_id' => $customer->erply_customer_id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            Log::info('Customer Xero Sync Completed', [
                'total_customers' => $pendingCustomers->count(),
                'synced_count' => $syncedCount,
                'error_count' => $errorCount
            ]);

            return [
                'total' => $pendingCustomers->count(),
                'synced' => $syncedCount,
                'errors' => $errorCount
            ];
        } catch (\Exception $e) {
            Log::error('Customer Xero Sync Exception', [
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

    public function createXeroCustomer($customer, $tenantId)
    {
        $accountingApi = $this->getAccountingApi($tenantId);
        
        $xeroCustomer = new Contact();
        $xeroCustomer->setName($customer->name);
        $xeroCustomer->setEmailAddress($customer->email);
        $xeroCustomer->setPhoneNumber($customer->phone);
        
        // Set addresses if available
        if ($customer->address) {
            $address = new \XeroAPI\XeroPHP\Models\Accounting\Address();
            $address->setAddressLine1($customer->address);
            $address->setCity($customer->city);
            $address->setCountry($customer->country);
            $address->setAddressType('POSTAL');
            $xeroCustomer->setAddresses([$address]);
        }
        
        $result = $accountingApi->createContacts($tenantId, [$xeroCustomer]);
        return $result->getContacts()[0];
    }

    public function syncProductsToXero($tenantId = null)
    {
        try {
            if (!$tenantId) {
                $firstToken = \App\Models\XeroToken::first();
                if (!$firstToken) {
                    throw new \Exception('No Xero tokens found');
                }
                $tenantId = $firstToken->tenant_id;
            }

            // Sync matrix products first
            $pendingMatrices = ErplyProduct::where('type', 'matrix')->where('sync_status', 'pending')->get();
            $syncedCount = 0;
            $errorCount = 0;

            foreach ($pendingMatrices as $matrix) {
                try {
                    $xeroProduct = $this->createXeroProduct($matrix, $tenantId);
                    
                    $matrix->update([
                        'xero_product_id' => $xeroProduct->getAccountID(),
                        'sync_status' => 'synced_to_xero',
                        'last_synced_at' => now(),
                        'xero_data' => json_encode($xeroProduct)
                    ]);
                    
                    $syncedCount++;
                    
                    Log::info('Product matrix synced to Xero', [
                        'erply_product_id' => $matrix->erply_product_id,
                        'xero_product_id' => $xeroProduct->getAccountID()
                    ]);
                } catch (\Exception $e) {
                    $errorCount++;
                    $matrix->update([
                        'sync_status' => 'failed',
                        'xero_sync_error' => $e->getMessage()
                    ]);
                    
                    Log::error('Failed to sync product matrix to Xero', [
                        'erply_product_id' => $matrix->erply_product_id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Sync variations
            $pendingVariations = ErplyProductVariation::where('sync_status', 'pending')->get();
            
            foreach ($pendingVariations as $variation) {
                try {
                    $xeroProduct = $this->createXeroProduct($variation, $tenantId);
                    
                    $variation->update([
                        'xero_variation_id' => $xeroProduct->getAccountID(),
                        'sync_status' => 'synced_to_xero',
                        'last_synced_at' => now(),
                        'xero_data' => json_encode($xeroProduct)
                    ]);
                    
                    $syncedCount++;
                    
                    Log::info('Product variation synced to Xero', [
                        'erply_variation_id' => $variation->erply_variation_id,
                        'xero_variation_id' => $xeroProduct->getAccountID()
                    ]);
                } catch (\Exception $e) {
                    $errorCount++;
                    $variation->update([
                        'sync_status' => 'failed',
                        'xero_sync_error' => $e->getMessage()
                    ]);
                    
                    Log::error('Failed to sync product variation to Xero', [
                        'erply_variation_id' => $variation->erply_variation_id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            Log::info('Product Xero Sync Completed', [
                'total_matrices' => $pendingMatrices->count(),
                'total_variations' => $pendingVariations->count(),
                'synced_count' => $syncedCount,
                'error_count' => $errorCount
            ]);

            return [
                'total' => $pendingMatrices->count() + $pendingVariations->count(),
                'synced' => $syncedCount,
                'errors' => $errorCount
            ];
        } catch (\Exception $e) {
            Log::error('Product Xero Sync Exception', [
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

    public function createXeroProduct($product, $tenantId)
    {
        $accountingApi = $this->getAccountingApi($tenantId);
        
        $xeroAccount = new Account();
        $xeroAccount->setName($product->name);
        $xeroAccount->setCode($product->sku ?: 'PROD-' . $product->id);
        $xeroAccount->setType('REVENUE');
        $xeroAccount->setDescription($product->description);
        
        // Set default account codes if not specified
        if (!$product->xero_account_code) {
            $xeroAccount->setCode(config('services.xero.default_account_code', '200'));
        }
        
        $result = $accountingApi->createAccounts($tenantId, [$xeroAccount]);
        return $result->getAccounts()[0];
    }

    public function retryFailedSyncs($tenantId = null)
    {
        try {
            $failedCustomers = ErplyCustomer::where('sync_status', 'failed')->get();
            $failedProducts = ErplyProduct::where('sync_status', 'failed')->get();
            $failedVariations = ErplyProductVariation::where('sync_status', 'failed')->get();
            
            // Reset failed customers to pending
            foreach ($failedCustomers as $customer) {
                $customer->update([
                    'sync_status' => 'pending',
                    'xero_sync_error' => null
                ]);
            }
            
            // Reset failed products to pending
            foreach ($failedProducts as $product) {
                $product->update([
                    'sync_status' => 'pending',
                    'xero_sync_error' => null
                ]);
            }
            
            // Reset failed variations to pending
            foreach ($failedVariations as $variation) {
                $variation->update([
                    'sync_status' => 'pending',
                    'xero_sync_error' => null
                ]);
            }
            
            // Retry sync
            $customerResult = $this->syncCustomersToXero($tenantId);
            $productResult = $this->syncProductsToXero($tenantId);
            
            return [
                'customers' => $customerResult,
                'products' => $productResult,
                'total_retried' => $failedCustomers->count() + $failedProducts->count() + $failedVariations->count()
            ];
        } catch (\Exception $e) {
            Log::error('Retry Failed Syncs Exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'customers' => ['total' => 0, 'synced' => 0, 'errors' => 1],
                'products' => ['total' => 0, 'synced' => 0, 'errors' => 1],
                'total_retried' => 0
            ];
        }
    }
}
