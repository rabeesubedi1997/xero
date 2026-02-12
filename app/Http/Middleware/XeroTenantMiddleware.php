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

        $tenantId = $request->header('Xero-Tenant-ID');
        if (!$tenantId) {
            return response()->json([
                'success' => false,
                'message' => 'Xero-Tenant-ID header is required'
            ], 400);
        }

        try {
            $tenants = $this->xeroService->getTenants();
            dump($tenants);
            $validTenant = collect($tenants)->first(function ($tenant) use ($tenantId) {
                return $tenant['tenantId'] === $tenantId;
            });
            dd($validTenant);
            if (!$validTenant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or unauthorized tenant ID'
                ], 403);
            }

            $request->merge(['xero_tenant_id' => $tenantId]);

            return $next($request);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant validation failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
