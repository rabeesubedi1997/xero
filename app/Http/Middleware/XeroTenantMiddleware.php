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
        if (!$this->xeroService->isAuthenticated()) {
            return response()->json([
                'success' => false,
                'message' => 'Not authenticated with Xero'
            ], 401);
        }

        // Try header first, then URL parameter as fallback
        $tenantId = $request->header('Xero-Tenant-ID');
        
        // Fallback to URL parameter if header is not provided
        if (!$tenantId) {
            $tenantId = $request->input('Xero-Tenant-ID');
        }
        
        // Debug: Log all sources for troubleshooting
        \Log::info('XeroTenantMiddleware - Tenant ID Sources:', [
            'header_value' => $request->header('Xero-Tenant-ID'),
            'url_parameter' => $request->input('Xero-Tenant-ID'),
            'final_tenant_id' => $tenantId,
            'all_headers' => $request->headers->all(),
            'all_query_params' => $request->query()
        ]);
        
        if (!$tenantId || $tenantId === 'null') {
            return response()->json([
                'success' => false,
                'message' => 'Xero-Tenant-ID header or URL parameter is required and cannot be null',
                'debug' => [
                    'received_headers' => $request->headers->all(),
                    'url_parameters' => $request->query(),
                    'header_value' => $request->header('Xero-Tenant-ID'),
                    'url_parameter_value' => $request->input('Xero-Tenant-ID')
                ]
            ], 400);
        }
        
        try {
            $tenants = $this->xeroService->getTenants();
            $validTenant = collect($tenants)->first(function ($tenant) use ($tenantId) {
                return $tenant['tenantId'] === $tenantId;
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

            return $next($request);
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
    }
}
