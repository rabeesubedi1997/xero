<?php

namespace App\Http\Controllers;

use App\Services\XeroService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class XeroAuthController extends Controller
{
    private $xeroService;

    public function __construct(XeroService $xeroService)
    {
        $this->xeroService = $xeroService;
    }

    public function connect()
    {
        try {
            $authUrl = $this->xeroService->getAuthorizationUrl();
            
            return redirect()->away($authUrl);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate authorization URL: ' . $e->getMessage()
            ], 500);
        }
    }

    public function callback(Request $request): JsonResponse
    {
        try {
            $code = $request->get('code');
            
            if (!$code) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authorization code not provided'
                ], 400);
            }

            $tokens = $this->xeroService->getAccessToken($code);
            $tenants = $this->xeroService->getTenants();

            return response()->json([
                'success' => true,
                'message' => 'Successfully authenticated with Xero',
                'tenants' => $tenants
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function tenants(): JsonResponse
    {
        try {
            if (!$this->xeroService->isAuthenticated()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Not authenticated with Xero'
                ], 401);
            }

            $tenants = $this->xeroService->getTenants();

            return response()->json([
                'success' => true,
                'tenants' => $tenants
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get tenants: ' . $e->getMessage()
            ], 500);
        }
    }

    public function logout(): JsonResponse
    {
        try {
            // Clear all tokens from database
            \App\Models\XeroToken::truncate();

            return response()->json([
                'success' => true,
                'message' => 'Successfully logged out from Xero'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Logout failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
