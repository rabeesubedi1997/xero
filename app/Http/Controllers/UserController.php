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
            // Try header first, then URL parameter as fallback
            $tenantId = $request->header('Xero-Tenant-ID');
            if (!$tenantId) {
                $tenantId = $request->input('Xero-Tenant-ID');
            }
            
            // If still no tenant ID, try to get from database (first available)
            if (!$tenantId) {
                $firstToken = \App\Models\XeroToken::first();
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
            $tenantId = $request->header('Xero-Tenant-ID');
            if (!$tenantId) {
                $tenantId = $request->input('Xero-Tenant-ID');
            }
            
            // If still no tenant ID, try to get from database (first available)
            if (!$tenantId) {
                $firstToken = \App\Models\XeroToken::first();
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
