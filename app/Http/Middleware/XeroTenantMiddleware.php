<?php

namespace App\Http\Middleware;

use App\Services\XeroService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class XeroTenantMiddleware
{
    private $xeroService;

    public function __construct(XeroService $xeroService)
    {
        $this->xeroService = $xeroService;
    }

    public function handle(Request $request, Closure $next)
    {
        // Try common header/query variations (case-insensitive)
        $tenantId = $request->header('Xero-Tenant-ID')
            ?: $request->header('xero-tenant-id')
            ?: $request->header('xero_tenant_id')
            ?: $request->header('tenant_id')
            ?: $request->header('tenantId');

        if (!$tenantId) {
            $tenantId = $request->input('Xero-Tenant-ID')
                ?: $request->input('xero-tenant-id')
                ?: $request->input('xero_tenant_id')
                ?: $request->input('tenant_id')
                ?: $request->input('tenantId');
        }

        // Debug: Log all sources for troubleshooting
        \Log::info('XeroTenantMiddleware - Tenant ID Sources:', [
            'header_value' => $request->header('Xero-Tenant-ID'),
            'url_parameter' => $request->input('Xero-Tenant-ID'),
            'final_tenant_id' => $tenantId,
            'all_headers' => $request->headers->all(),
            'all_query_params' => $request->query()
        ]);

        // If tenantId is explicitly the string 'null', treat as absent
        if ($tenantId === 'null') {
            $tenantId = null;
        }

        // If a tenantId was provided, validate it against known tenants when possible
        if ($tenantId) {
            try {
                if (!$this->xeroService->isAuthenticated()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Not authenticated with Xero'
                    ], 401);
                }

                $tenants = $this->xeroService->getTenants();
                $validTenant = collect($tenants)->first(function ($tenant) use ($tenantId) {
                    return ($tenant['tenantId'] ?? null) === $tenantId;
                });

                if (!$validTenant) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid or unauthorized tenant ID',
                        'debug' => [
                            'received_tenant_id' => $tenantId,
                            'available_tenants' => $tenants
                        ]
                    ], 403);
                }

                $request->merge(['xero_tenant_id' => $tenantId]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant validation failed: ' . $e->getMessage(),
                    'debug' => [
                        'tenant_id' => $tenantId,
                        'error' => $e->getMessage()
                    ]
                ], 500);
            }
        } else {
            // No tenant provided — allow request through and let controller handle DB fallback
            $request->merge(['xero_tenant_id' => null]);
        }

        return $next($request);
    }
}
