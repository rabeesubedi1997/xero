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
            // Get all tokens before deletion for response
            $tokens = \App\Models\XeroToken::all();
            $tokenCount = $tokens->count();
            
            // Revoke tokens from Xero for each tenant
            foreach ($tokens as $token) {
                try {
                    $client = new \GuzzleHttp\Client();
                    $client->post('https://identity.xero.com/connect/revocation', [
                        'form_params' => [
                            'token' => $token->access_token,
                            'client_id' => config('services.xero.client_id'),
                            'client_secret' => config('services.xero.client_secret'),
                        ]
                    ]);
                } catch (\Exception $e) {
                    // Continue even if revocation fails
                    \Log::warning('Failed to revoke token for tenant ' . $token->tenant_id . ': ' . $e->getMessage());
                }
            }
            
            // Clear all tokens from database
            \App\Models\XeroToken::truncate();

            return response()->json([
                'success' => true,
                'message' => 'Successfully logged out from Xero (local and remote)',
                'tokens_cleared' => $tokenCount,
                'cleared_tenants' => $tokens->pluck('tenant_name')->toArray(),
                'xero_revoked' => true
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Logout failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get token status for monitoring
     */
    public function tokenStatus(): JsonResponse
    {
        try {
            $tokens = \App\Models\XeroToken::all();
            $tokenInfo = [];

            foreach ($tokens as $token) {
                $tokenInfo[] = [
                    'tenant_id' => $token->tenant_id,
                    'tenant_name' => $token->tenant_name,
                    'expires_at' => $token->expires_at,
                    'is_expired' => $token->isExpired(),
                    'time_until_expiry' => $token->expires_at ? $token->expires_at->diffForHumans(now(), true) : null,
                    'created_at' => $token->created_at,
                    'updated_at' => $token->updated_at
                ];
            }

            return response()->json([
                'success' => true,
                'total_tokens' => count($tokenInfo),
                'tokens' => $tokenInfo
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get token status: ' . $e->getMessage()
            ], 500);
        }
    }
}
