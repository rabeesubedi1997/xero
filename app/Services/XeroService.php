<?php

namespace App\Services;

use XeroAPI\XeroPHP\Api\AccountingApi;
use XeroAPI\XeroPHP\Configuration;
use XeroAPI\XeroPHP\Api\IdentityApi;
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
        $this->config->setTenantId($tenantId);
        
        return new AccountingApi(new Client(), $this->config);
    }

    public function getAccounts($tenantId)
    {
        $accountingApi = $this->getAccountingApi($tenantId);
        
        try {
            return $accountingApi->getAccounts($tenantId);
        } catch (Exception $e) {
            if ($e->getCode() === 401) {
                // Try refresh token and retry
                $this->refreshAccessToken($tenantId);
                $accountingApi = $this->getAccountingApi($tenantId);
                return $accountingApi->getAccounts($tenantId);
            }
            throw $e;
        }
    }

    public function getAccount($tenantId, $accountId)
    {
        $accountingApi = $this->getAccountingApi($tenantId);
        
        try {
            return $accountingApi->getAccount($tenantId, $accountId);
        } catch (Exception $e) {
            if ($e->getCode() === 401) {
                $this->refreshAccessToken($tenantId);
                $accountingApi = $this->getAccountingApi($tenantId);
                return $accountingApi->getAccount($tenantId, $accountId);
            }
            throw $e;
        }
    }

    public function createAccount($tenantId, $accountData)
    {
        $accountingApi = $this->getAccountingApi($tenantId);
        
        try {
            return $accountingApi->createAccount($tenantId, $accountData);
        } catch (Exception $e) {
            if ($e->getCode() === 401) {
                $this->refreshAccessToken($tenantId);
                $accountingApi = $this->getAccountingApi($tenantId);
                return $accountingApi->createAccount($tenantId, $accountData);
            }
            throw $e;
        }
    }

    public function updateAccount($tenantId, $accountId, $accountData)
    {
        $accountingApi = $this->getAccountingApi($tenantId);
        
        try {
            return $accountingApi->updateAccount($tenantId, $accountId, $accountData);
        } catch (Exception $e) {
            if ($e->getCode() === 401) {
                $this->refreshAccessToken($tenantId);
                $accountingApi = $this->getAccountingApi($tenantId);
                return $accountingApi->updateAccount($tenantId, $accountId, $accountData);
            }
            throw $e;
        }
    }

    public function deleteAccount($tenantId, $accountId)
    {
        $accountingApi = $this->getAccountingApi($tenantId);
        
        try {
            return $accountingApi->deleteAccount($tenantId, $accountId);
        } catch (Exception $e) {
            if ($e->getCode() === 401) {
                $this->refreshAccessToken($tenantId);
                $accountingApi = $this->getAccountingApi($tenantId);
                return $accountingApi->deleteAccount($tenantId, $accountId);
            }
            throw $e;
        }
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
}
