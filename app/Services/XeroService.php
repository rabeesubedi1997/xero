<?php

namespace App\Services;

use XeroAPI\XeroPHP\Api\AccountingApi;
use XeroAPI\XeroPHP\Api\IdentityApi;
use XeroAPI\XeroPHP\Configuration;
use GuzzleHttp\Client;
use Exception;
use App\Models\XeroToken;

class XeroService
{
    private $accountingApi;
    private $identityApi;
    private $config;

    public function __construct()
    {
        $this->config = Configuration::getDefaultConfiguration();
    }

    public function getAuthorizationUrl()
    {
        $clientId = config('services.xero.client_id');
        $redirectUri = config('services.xero.redirect_uri');
        $scope = config('services.xero.scope');

        return "https://login.xero.com/identity/connect/authorize?" . http_build_query([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'scope' => $scope
        ]);
    }

    public function getAccessToken($code)
    {
        $client = new Client();

        $response = $client->post('https://identity.xero.com/connect/token', [
            'form_params' => [
                'grant_type' => 'authorization_code',
                'client_id' => config('services.xero.client_id'),
                'client_secret' => config('services.xero.client_secret'),
                'code' => $code,
                'redirect_uri' => config('services.xero.redirect_uri'),
            ]
        ]);

        $tokens = json_decode($response->getBody(), true);

        // Get tenant information
        $this->config->setAccessToken($tokens['access_token']);
        $identityApi = new IdentityApi(new Client(), $this->config);
        $tenants = $identityApi->getConnections();

        // Store tokens for each tenant
        foreach ($tenants as $tenant) {
            $this->storeTokenForTenant($tenant->getTenantId(), $tokens, $tenant->getTenantName());
        }

        return $tokens;
    }

    public function refreshAccessToken($tenantId)
    {
        $token = XeroToken::findByTenantId($tenantId);

        if (!$token || !$token->refresh_token) {
            throw new Exception('No refresh token available for tenant: ' . $tenantId);
        }

        $client = new Client();

        $response = $client->post('https://identity.xero.com/connect/token', [
            'form_params' => [
                'grant_type' => 'refresh_token',
                'client_id' => config('services.xero.client_id'),
                'client_secret' => config('services.xero.client_secret'),
                'refresh_token' => $token->refresh_token,
            ]
        ]);

        $newTokens = json_decode($response->getBody(), true);

        // Update token in database
        $this->updateTokenForTenant($tenantId, $newTokens);

        return $newTokens;
    }

    public function getTenants()
    {
        $tokens = XeroToken::all();

        if ($tokens->isEmpty()) {
            throw new Exception('No tokens found. Please authenticate first.');
        }

        $tenants = [];
        foreach ($tokens as $token) {
            $tenants[] = [
                'tenantId' => $token->tenant_id,
                'tenantName' => $token->tenant_name,
                'tenantType' => 'ORGANISATION'
            ];
        }

        return $tenants;
    }

    public function getAccountingApi($tenantId)
    {
        $token = $this->getValidToken($tenantId);

        $this->config->setAccessToken($token->access_token);

        return new AccountingApi(new Client(), $this->config);
    }

    /**
     * Global wrapper for Xero API calls with automatic token refresh
     */
    private function executeWithRefresh($tenantId, callable $apiCall)
    {
        try {
            return $apiCall();
        } catch (Exception $e) {
            if ($e->getCode() === 401) {
                // Refresh token and retry
                $this->refreshAccessToken($tenantId);
                // Don't reassign accountingApi, just retry the call
                return $apiCall();
            }
            throw $e;
        }
    }

    public function getAccounts($tenantId)
    {
        return $this->executeWithRefresh($tenantId, function () use ($tenantId) {
            $accountingApi = $this->getAccountingApi($tenantId);

            // Debug: Log the API call
            \Log::info('XeroService::getAccounts - Making API Call', [
                'tenant_id' => $tenantId,
                'api_class' => get_class($accountingApi)
            ]);

            $result = $accountingApi->getAccounts($tenantId);

            // Debug: Log the result
            \Log::info('XeroService::getAccounts - API Response', [
                'tenant_id' => $tenantId,
                'result_type' => gettype($result),
                'has_accounts' => method_exists($result, 'getAccounts'),
                'accounts_count' => method_exists($result, 'getAccounts') ? count($result->getAccounts()) : 'N/A'
            ]);

            return $result;
        });
    }

    public function getAccount($tenantId, $accountId)
    {
        return $this->executeWithRefresh($tenantId, function () use ($tenantId, $accountId) {
            $accountingApi = $this->getAccountingApi($tenantId);
            return $accountingApi->getAccount($tenantId, $accountId);
        });
    }

    public function createAccount($tenantId, $accountData)
    {
        return $this->executeWithRefresh($tenantId, function () use ($tenantId, $accountData) {
            $accountingApi = $this->getAccountingApi($tenantId);
            return $accountingApi->createAccount($tenantId, $accountData);
        });
    }

    public function updateAccount($tenantId, $accountId, $accountData)
    {
        return $this->executeWithRefresh($tenantId, function () use ($tenantId, $accountId, $accountData) {
            $accountingApi = $this->getAccountingApi($tenantId);
            return $accountingApi->updateAccount($tenantId, $accountId, $accountData);
        });
    }

    public function deleteAccount($tenantId, $accountId)
    {
        return $this->executeWithRefresh($tenantId, function () use ($tenantId, $accountId) {
            $accountingApi = $this->getAccountingApi($tenantId);
            return $accountingApi->deleteAccount($tenantId, $accountId);
        });
    }

    public function getUsers($tenantId)
    {
        return $this->executeWithRefresh($tenantId, function () use ($tenantId) {
            $accountingApi = $this->getAccountingApi($tenantId);

            // Debug: Log API call
            \Log::info('XeroService::getUsers - Making API Call', [
                'tenant_id' => $tenantId,
                'api_class' => get_class($accountingApi),
                'available_methods' => get_class_methods($accountingApi)
            ]);

            // Xero Accounting API doesn't have getUsers method
            // Need to use Identity API or check if method exists
            if (method_exists($accountingApi, 'getUsers')) {
                $result = $accountingApi->getUsers($tenantId);
            } else {
                // Fallback: try to get users from accounts or return empty
                \Log::warning('XeroService::getUsers - getUsers method not found', [
                    'tenant_id' => $tenantId,
                    'api_class' => get_class($accountingApi)
                ]);

                // Try to get all accounts and extract user info
                try {
                    $accounts = $accountingApi->getAccounts($tenantId);
                    $users = [];

                    if ($accounts && method_exists($accounts, 'getAccounts')) {
                        foreach ($accounts->getAccounts() as $account) {
                            // Extract user info from account if possible
                            $users[] = [
                                'user_id' => $account->getAccountID() ?? 'unknown',
                                'name' => $account->getName() ?? 'Unknown User',
                                'email' => 'Not available from Account API',
                                'account_type' => $account->getType() ?? 'UNKNOWN'
                            ];
                        }
                    }

                    $result = $users;
                } catch (\Exception $e) {
                    \Log::error('XeroService::getUsers - Fallback failed', [
                        'tenant_id' => $tenantId,
                        'error' => $e->getMessage()
                    ]);
                    $result = [];
                }
            }

            // Debug: Log result
            \Log::info('XeroService::getUsers - API Response', [
                'tenant_id' => $tenantId,
                'result_type' => gettype($result),
                'result_count' => is_array($result) ? count($result) : 0
            ]);

            return $result;
        });
    }

    public function getUser($tenantId, $userId)
    {
        return $this->executeWithRefresh($tenantId, function () use ($tenantId, $userId) {
            $accountingApi = $this->getAccountingApi($tenantId);
            return $accountingApi->getUser($tenantId, $userId);
        });
    }

    public function isAuthenticated()
    {
        return XeroToken::count() > 0;
    }

    /**
     * Store token for a specific tenant
     */
    private function storeTokenForTenant($tenantId, $tokens, $tenantName = null)
    {
        $expiresAt = now()->addSeconds($tokens['expires_in'] ?? 3600);

        XeroToken::updateOrCreate(
            ['tenant_id' => $tenantId],
            [
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'],
                'expires_at' => $expiresAt,
                'tenant_name' => $tenantName
            ]
        );
    }

    /**
     * Update existing token for tenant
     */
    private function updateTokenForTenant($tenantId, $tokens)
    {
        $token = XeroToken::findByTenantId($tenantId);
        if ($token) {
            $expiresAt = now()->addSeconds($tokens['expires_in'] ?? 3600);

            $token->update([
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'],
                'expires_at' => $expiresAt
            ]);
        }
    }

    /**
     * Get valid token (refresh if needed)
     */
    private function getValidToken($tenantId)
    {
        $token = XeroToken::findByTenantId($tenantId);

        if (!$token) {
            throw new Exception('No token found for tenant: ' . $tenantId);
        }

        // Auto-refresh if expired
        if ($token->isExpired()) {
            $this->refreshAccessToken($tenantId);
            $token = XeroToken::findByTenantId($tenantId);
        }

        return $token;
    }

    /**
     * Get all contacts from Xero
     */
    public function getContacts($tenantId)
    {
        return $this->executeWithRefresh($tenantId, function () use ($tenantId) {
            $accountingApi = $this->getAccountingApi($tenantId);

            \Log::info('XeroService::getContacts - Fetching contacts', [
                'tenant_id' => $tenantId
            ]);

            $result = $accountingApi->getContacts($tenantId);

            \Log::info('XeroService::getContacts - Success', [
                'tenant_id' => $tenantId,
                'contacts_count' => method_exists($result, 'getContacts') ? count($result->getContacts()) : 0
            ]);

            return $result;
        });
    }

    /**
     * Get specific contact from Xero
     */
    public function getContact($tenantId, $contactId)
    {
        return $this->executeWithRefresh($tenantId, function () use ($tenantId, $contactId) {
            $accountingApi = $this->getAccountingApi($tenantId);

            \Log::info('XeroService::getContact - Fetching contact', [
                'tenant_id' => $tenantId,
                'contact_id' => $contactId
            ]);

            $result = $accountingApi->getContact($tenantId, $contactId);

            return $result;
        });
    }

    /**
     * Create a new contact in Xero
     */
    public function createContact($tenantId, $contactData)
    {
        return $this->executeWithRefresh($tenantId, function () use ($tenantId, $contactData) {
            $accountingApi = $this->getAccountingApi($tenantId);

            \Log::info('XeroService::createContact - Creating new contact', [
                'tenant_id' => $tenantId,
                'contact_name' => $contactData->getName() ?? 'Unknown'
            ]);

            // Wrap Contact in Contacts collection
            $contacts = new Contacts();
            $contacts->setContacts([$contactData]);

            $result = $accountingApi->createContact($tenantId, $contacts);

            \Log::info('XeroService::createContact - Contact created successfully', [
                'tenant_id' => $tenantId,
                'contact_id' => method_exists($result, 'getContacts') && count($result->getContacts()) > 0
                    ? $result->getContacts()[0]->getContactID()
                    : 'Unknown'
            ]);

            return $result;
        });
    }

    /**
     * Update an existing contact in Xero
     */
    public function updateContact($tenantId, $contactId, $contactData)
    {
        return $this->executeWithRefresh($tenantId, function () use ($tenantId, $contactId, $contactData) {
            $accountingApi = $this->getAccountingApi($tenantId);

            \Log::info('XeroService::updateContact - Updating contact', [
                'tenant_id' => $tenantId,
                'contact_id' => $contactId
            ]);

            // Wrap Contact in Contacts collection
            $contacts = new Contacts();
            $contacts->setContacts([$contactData]);

            $result = $accountingApi->updateContact($tenantId, $contactId, $contacts);

            \Log::info('XeroService::updateContact - Contact updated successfully', [
                'tenant_id' => $tenantId,
                'contact_id' => $contactId
            ]);

            return $result;
        });
    }
}
