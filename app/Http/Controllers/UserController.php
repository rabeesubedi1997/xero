<?php

namespace App\Http\Controllers;

use App\Services\XeroService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    private $xeroService;

    public function __construct(XeroService $xeroService)
    {
        $this->xeroService = $xeroService;
    }

    public function index(Request $request): JsonResponse
    {
        try {
            // Debug: Initial state
            \Log::info('UserController - Request Start', [
                'all_headers' => $request->headers->all(),
                'all_query_params' => $request->query()->all(),
                'header_xero_tenant_id' => $request->header('Xero-Tenant-ID'),
                'header_xero_tenant_id_lowercase' => $request->header('xero-tenant-id'),
                'header_xero_tenant_id_uppercase' => $request->header('XERO-TENANT-ID'),
                'query_xero_tenant_id' => $request->input('Xero-Tenant-ID'),
                'query_xero_tenant_id_lowercase' => $request->input('xero-tenant-id'),
                'method' => $request->method(),
                'url' => $request->fullUrl()
            ]);

            // Try common header variations first, then URL parameter variations (case-insensitive)
            $tenantId = $request->header('Xero-Tenant-ID')
                ?: $request->header('xero-tenant-id')
                ?: $request->header('XERO-TENANT-ID')
                ?: $request->header('tenant_id')
                ?: $request->header('tenantId')
                ?: $request->header('xero_tenant_id');

            if (!$tenantId) {
                $tenantId = $request->input('Xero-Tenant-ID')
                    ?: $request->input('xero-tenant-id')
                    ?: $request->input('tenant_id')
                    ?: $request->input('tenantId')
                    ?: $request->input('xero_tenant_id');
            }

            // Debug: After initial extraction
            \Log::info('UserController - After Extraction', [
                'tenant_id' => $tenantId,
                'is_null' => is_null($tenantId),
                'type' => gettype($tenantId)
            ]);

            // If still no tenant ID, try to get from database (first available non-empty tenant_id)
            if (!$tenantId) {
                $firstToken = \App\Models\XeroToken::whereNotNull('tenant_id')->where('tenant_id', '!=', '')->first();
                if ($firstToken) {
                    $tenantId = $firstToken->tenant_id;
                }

                // Debug: Check database state (include other common query/header keys)
                \Log::info('UserController - Token Search Debug', [
                    'tenant_id_from_header' => $request->header('Xero-Tenant-ID') ?? $request->header('tenant_id'),
                    'tenant_id_from_url' => $request->input('Xero-Tenant-ID') ?? $request->input('tenant_id'),
                    'first_token_found' => $firstToken ? true : false,
                    'first_token_data' => $firstToken ? [
                        'tenant_id' => $firstToken->tenant_id,
                        'tenant_name' => $firstToken->tenant_name,
                        'expires_at' => $firstToken->expires_at,
                        'is_expired' => $firstToken->isExpired()
                    ] : null,
                    'total_tokens_count' => \App\Models\XeroToken::count(),
                    'all_tokens' => \App\Models\XeroToken::all()->pluck('tenant_id')->toArray()
                ]);
            }

            // Debug: Final tenant ID decision
            \Log::info('UserController - Final Tenant ID', [
                'final_tenant_id' => $tenantId,
                'is_null' => is_null($tenantId),
                'tenant_id_type' => gettype($tenantId)
            ]);

            if (!$tenantId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Xero-Tenant-ID header or URL parameter is required',
                    'help' => 'Add ?Xero-Tenant-ID=TENANT_ID to URL or Xero-Tenant-ID header',
                    'debug' => [
                        'header_value' => $request->header('Xero-Tenant-ID'),
                        'url_parameter_value' => $request->input('Xero-Tenant-ID'),
                        'available_tokens' => \App\Models\XeroToken::count(),
                        'first_tenant' => \App\Models\XeroToken::first()?->tenant_id
                    ]
                ], 400);
            }

            $users = $this->xeroService->getUsers($tenantId);

            return response()->json([
                'success' => true,
                'data' => $users,
                'count' => count($users),
                'tenant_used' => $tenantId
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch users: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(Request $request, $userId): JsonResponse
    {
        try {
            // Try common header/query variations, then DB fallback
            $tenantId = $request->header('Xero-Tenant-ID')
                ?: $request->header('xero-tenant-id')
                ?: $request->header('tenant_id')
                ?: $request->header('tenantId')
                ?: $request->input('Xero-Tenant-ID')
                ?: $request->input('xero-tenant-id')
                ?: $request->input('tenant_id')
                ?: $request->input('tenantId');

            // If still no tenant ID, try to get from database (first available non-empty tenant_id)
            if (!$tenantId) {
                $firstToken = \App\Models\XeroToken::whereNotNull('tenant_id')->where('tenant_id', '!=', '')->first();
                if ($firstToken) {
                    $tenantId = $firstToken->tenant_id;
                }
            }

            if (!$tenantId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Xero-Tenant-ID header or URL parameter is required',
                    'help' => 'Add ?Xero-Tenant-ID=TENANT_ID to URL or Xero-Tenant-ID header'
                ], 400);
            }

            $user = $this->xeroService->getUser($tenantId, $userId);

            return response()->json([
                'success' => true,
                'data' => $user,
                'message' => 'User retrieved successfully',
                'tenant_used' => $tenantId
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve user: ' . $e->getMessage()
            ], 500);
        }
    }
}
