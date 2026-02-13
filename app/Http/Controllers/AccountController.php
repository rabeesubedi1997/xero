<?php

namespace App\Http\Controllers;

use App\Services\XeroService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AccountController extends Controller
{
    private $xeroService;

    public function __construct(XeroService $xeroService)
    {
        $this->xeroService = $xeroService;
    }

    public function index(Request $request): JsonResponse
    {
        try {
            // Try header first, then URL parameter as fallback
            $tenantId = $request->header('Xero-Tenant-ID');
            if (!$tenantId) {
                $tenantId = $request->input('Xero-Tenant-ID');
            }
            
            if (!$tenantId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Xero-Tenant-ID header or URL parameter is required',
                    'debug' => [
                        'header_value' => $request->header('Xero-Tenant-ID'),
                        'url_parameter_value' => $request->input('Xero-Tenant-ID')
                    ]
                ], 400);
            }
            
            $accounts = $this->xeroService->getAccounts($tenantId);
            
            // Debug: Check what we're getting from Xero
            $accountsArray = $accounts->getAccounts();
            
            // Extract actual data from all accounts
            $formattedAccounts = [];
            foreach ($accountsArray as $account) {
                $formattedAccounts[] = [
                    'account_id' => method_exists($account, 'getAccountID') ? $account->getAccountID() : null,
                    'name' => method_exists($account, 'getName') ? $account->getName() : null,
                    'code' => method_exists($account, 'getCode') ? $account->getCode() : null,
                    'type' => method_exists($account, 'getType') ? $account->getType() : null,
                    'status' => method_exists($account, 'getStatus') ? $account->getStatus() : null,
                    'description' => method_exists($account, 'getDescription') ? $account->getDescription() : null,
                    'currency_code' => method_exists($account, 'getCurrencyCode') ? $account->getCurrencyCode() : null,
                    'bank_account_number' => method_exists($account, 'getBankAccountNumber') ? $account->getBankAccountNumber() : null,
                    'tax_type' => method_exists($account, 'getTaxType') ? $account->getTaxType() : null,
                    'enable_payments_to_account' => method_exists($account, 'getEnablePaymentsToAccount') ? $account->getEnablePaymentsToAccount() : null,
                    'show_in_expense_claims' => method_exists($account, 'getShowInExpenseClaims') ? $account->getShowInExpenseClaims() : null,
                    'class' => method_exists($account, 'getClass') ? $account->getClass() : null,
                    'system_account' => method_exists($account, 'getSystemAccount') ? $account->getSystemAccount() : null,
                    'reporting_code' => method_exists($account, 'getReportingCode') ? $account->getReportingCode() : null,
                    'updated_date_utc' => method_exists($account, 'getUpdatedDateUtc') ? $account->getUpdatedDateUtc() : null
                ];
            }
            
            // Inspect first account object
            $firstAccount = null;
            $sampleAccountData = null;
            if (!empty($accountsArray)) {
                $firstAccount = $accountsArray[0];
                
                // Extract actual data using getters
                $sampleAccountData = [
                    'account_class' => get_class($firstAccount),
                    'account_properties' => get_object_vars($firstAccount),
                    'has_getters' => [
                        'getName' => method_exists($firstAccount, 'getName'),
                        'getCode' => method_exists($firstAccount, 'getCode'),
                        'getType' => method_exists($firstAccount, 'getType'),
                        'getAccountID' => method_exists($firstAccount, 'getAccountID'),
                        'getStatus' => method_exists($firstAccount, 'getStatus')
                    ],
                    'extracted_data' => [
                        'account_id' => method_exists($firstAccount, 'getAccountID') ? $firstAccount->getAccountID() : 'N/A',
                        'name' => method_exists($firstAccount, 'getName') ? $firstAccount->getName() : 'N/A',
                        'code' => method_exists($firstAccount, 'getCode') ? $firstAccount->getCode() : 'N/A',
                        'type' => method_exists($firstAccount, 'getType') ? $firstAccount->getType() : 'N/A',
                        'status' => method_exists($firstAccount, 'getStatus') ? $firstAccount->getStatus() : 'N/A',
                        'description' => method_exists($firstAccount, 'getDescription') ? $firstAccount->getDescription() : 'N/A',
                        'currency_code' => method_exists($firstAccount, 'getCurrencyCode') ? $firstAccount->getCurrencyCode() : 'N/A'
                    ]
                ];
            }
            
            return response()->json([
                'success' => true,
                'data' => $formattedAccounts,
                'count' => count($formattedAccounts),
                'debug' => [
                    'tenant_id' => $tenantId,
                    'raw_response_type' => gettype($accounts),
                    'accounts_type' => gettype($accountsArray),
                    'sample_account' => $sampleAccountData,
                    'first_account_raw' => $firstAccount
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch accounts: ' . $e->getMessage(),
                'error_details' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
            ], 500);
        }
    }

    public function show(Request $request, $accountId): JsonResponse
    {
        try {
            $tenantId = $request->header('Xero-Tenant-ID');
            if (!$tenantId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Xero-Tenant-ID header is required'
                ], 400);
            }
            
            // Get token info before API call to show database usage
            $token = \App\Models\XeroToken::findByTenantId($tenantId);
            $tokenInfo = null;
            
            if ($token) {
                $tokenInfo = [
                    'tenant_id' => $token->tenant_id,
                    'tenant_name' => $token->tenant_name,
                    'is_expired' => $token->isExpired(),
                    'expires_at' => $token->expires_at,
                    'time_until_expiry' => $token->expires_at ? $token->expires_at->diffForHumans(now(), true) : null,
                    'will_refresh' => $token->isExpired()
                ];
            }
            
            $account = $this->xeroService->getAccount($tenantId, $accountId);

            return response()->json([
                'success' => true,
                'message' => 'Account retrieved successfully',
                'data' => $account->getAccounts()[0],
                'token_info' => $tokenInfo,
                'database_token_used' => $tokenInfo !== null
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve account: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $tenantId = $request->input('xero_tenant_id');
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'code' => 'required|string|max:10',
                'type' => 'required|string|in:BANK,CURRENT,CURRLIAB,DEPRECIATN,EQUITY,EXPENSE,INVENTORY,LIABILITY,NONCURRENT,OTHERINCOME,OVERHEADS,PAYGLIABILITY,PREPAYMENT,REVENUE,SALES,TAX,TERMLIAB',
                'description' => 'nullable|string|max:4000',
                'tax_type' => 'nullable|string|max:50',
                'enable_payments_to_account' => 'boolean',
                'show_in_expense_claims' => 'boolean',
            ]);

            $account = new \XeroAPI\XeroPHP\Models\Accounting\Account();
            $account->setName($validated['name']);
            $account->setCode($validated['code']);
            $account->setType($validated['type']);
            
            if (isset($validated['description'])) {
                $account->setDescription($validated['description']);
            }
            
            if (isset($validated['tax_type'])) {
                $account->setTaxType($validated['tax_type']);
            }
            
            if (isset($validated['enable_payments_to_account'])) {
                $account->setEnablePaymentsToAccount($validated['enable_payments_to_account']);
            }
            
            if (isset($validated['show_in_expense_claims'])) {
                $account->setShowInExpenseClaims($validated['show_in_expense_claims']);
            }

            $result = $this->xeroService->createAccount($tenantId, $account);

            return response()->json([
                'success' => true,
                'message' => 'Account created successfully',
                'data' => $result->getAccounts()[0]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create account: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $accountId): JsonResponse
    {
        try {
            $tenantId = $request->header('Xero-Tenant-ID');
            if (!$tenantId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Xero-Tenant-ID header is required'
                ], 400);
            }
            
            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'code' => 'sometimes|required|string|max:10',
                'type' => 'sometimes|required|string|in:BANK,CURRENT,CURRLIAB,DEPRECIATN,EQUITY,EXPENSE,INVENTORY,LIABILITY,NONCURRENT,OTHERINCOME,OVERHEADS,PAYGLIABILITY,PREPAYMENT,REVENUE,SALES,TAX,TERMLIAB',
                'description' => 'nullable|string|max:4000',
                'tax_type' => 'nullable|string|max:50',
                'enable_payments_to_account' => 'boolean',
                'show_in_expense_claims' => 'boolean',
                'status' => 'sometimes|required|string|in:ACTIVE,ARCHIVED'
            ]);

            $account = new \XeroAPI\XeroPHP\Models\Accounting\Account();
            $account->setAccountID($accountId);
            
            if (isset($validated['name'])) {
                $account->setName($validated['name']);
            }
            
            if (isset($validated['code'])) {
                $account->setCode($validated['code']);
            }
            
            if (isset($validated['type'])) {
                $account->setType($validated['type']);
            }
            
            if (isset($validated['description'])) {
                $account->setDescription($validated['description']);
            }
            
            if (isset($validated['tax_type'])) {
                $account->setTaxType($validated['tax_type']);
            }
            
            if (isset($validated['enable_payments_to_account'])) {
                $account->setEnablePaymentsToAccount($validated['enable_payments_to_account']);
            }
            
            if (isset($validated['show_in_expense_claims'])) {
                $account->setShowInExpenseClaims($validated['show_in_expense_claims']);
            }
            
            if (isset($validated['status'])) {
                $account->setStatus($validated['status']);
            }

            $result = $this->xeroService->updateAccount($tenantId, $accountId, $account);

            return response()->json([
                'success' => true,
                'message' => 'Account updated successfully',
                'data' => $result->getAccounts()[0]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update account: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Request $request, $accountId): JsonResponse
    {
        try {
            $tenantId = $request->header('Xero-Tenant-ID');
            if (!$tenantId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Xero-Tenant-ID header is required'
                ], 400);
            }
            
            $this->xeroService->deleteAccount($tenantId, $accountId);

            return response()->json([
                'success' => true,
                'message' => 'Account deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete account: ' . $e->getMessage()
            ], 500);
        }
    }
}




