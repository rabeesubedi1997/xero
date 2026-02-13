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
            
            $users = $this->xeroService->getUsers($tenantId);
            
            return response()->json([
                'success' => true,
                'data' => $users,
                'count' => count($users)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch users: ' . $e->getMessage(),
                'error_details' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
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
            
            if (!$tenantId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Xero-Tenant-ID header or URL parameter is required'
                ], 400);
            }
            
            $user = $this->xeroService->getUser($tenantId, $userId);

            return response()->json([
                'success' => true,
                'data' => $user,
                'message' => 'User retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve user: ' . $e->getMessage()
            ], 500);
        }
    }
}
