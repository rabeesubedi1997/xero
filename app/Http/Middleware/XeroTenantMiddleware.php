<?php

namespace App\Http\Middleware;

use App\Services\XeroService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class XeroTenantMiddleware
{
    private $xeroService;

    public function __construct()
    {
        $request = request();
        dd($request->headers->all());
        // $this->xeroService = $xeroService;
    }

    public function handle(Request $request, Closure $next)
    {
        dd($request->headers->all());
        if (!$this->xeroService->isAuthenticated()) {
            return response()->json([
                'success' => false,
                'message' => 'Not authenticated with Xero'
            ], 401);
        }

        $tenantId = $request->header('Xero-Tenant-ID');
        
        // Debug: Log all headers for troubleshooting
        \Log::info('XeroTenantMiddleware - All Headers:', [
            'all_headers' => $request->headers->all(),
            'xero_tenant_id' => $tenantId,
            'authorization' => $request->header('authorization')
        ]);
        
        if (!$tenantId || $tenantId === 'null') {
            return response()->json([
                'success' => false,
                'message' => 'Xero-Tenant-ID header is required and cannot be null',
                'debug' => [
                    'received_headers' => $request->headers->all(),
                    'tenant_id_value' => $tenantId
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
