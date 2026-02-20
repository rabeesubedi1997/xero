<?php

namespace App\Http\Controllers;

use App\Services\ErplyService;
use App\Services\XeroSyncService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ErplyController extends Controller
{
    private $erplyService;
    private $xeroSyncService;

    public function __construct(ErplyService $erplyService, XeroSyncService $xeroSyncService)
    {
        $this->erplyService = $erplyService;
        $this->xeroSyncService = $xeroSyncService;
    }

 public function syncCustomers(Request $request): JsonResponse
{
    try {
        // Cast debug query parameter to boolean
        $debug = filter_var($request->query('debug', false), FILTER_VALIDATE_BOOLEAN);

        // Optional: allow page & limit via query params
        $page = (int) $request->query('page', 1);
        $limit = (int) $request->query('limit', 20);

        $result = $this->erplyService->syncCustomersToDatabase($page, $limit, $debug);

        return response()->json([
            'success' => true,
            'message' => $debug ? 'Customer fetch (debug) completed' : 'Customer sync completed',
            'data' => $result
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to sync customers: ' . $e->getMessage(),
            'error_details' => [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]
        ], 500);
    }
}

    public function sendCustomersToErply(Request $request)
    {
        try {
            $limit = $request->get('limit', 50);
            $result = $this->erplyService->sendCustomersToErply($limit);
            
            return response()->json([
                'success' => true,
                'message' => $result['message'] ?? 'Customer sync to ERPLY completed',
                'data' => [
                    'total' => $result['total'] ?? 0,
                    'synced' => $result['synced'] ?? 0,
                    'errors' => $result['errors'] ?? 0
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to send customers to ERPLY', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to send customers to ERPLY',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function syncProducts(): JsonResponse
    {
        try {
            $result = $this->erplyService->syncProductsToDatabase();
            
            return response()->json([
                'success' => true,
                'message' => 'Product sync completed',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync products: ' . $e->getMessage(),
                'error_details' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
            ], 500);
        }
    }

    public function syncFull(): JsonResponse
    {
        try {
            $customersResult = $this->erplyService->syncCustomersToDatabase();
            $productsResult = $this->erplyService->syncProductsToDatabase();
            
            return response()->json([
                'success' => true,
                'message' => 'Full ERPLY sync completed',
                'data' => [
                    'customers' => $customersResult,
                    'products' => $productsResult,
                    'total_synced' => $customersResult['synced'] + $productsResult['synced'],
                    'total_errors' => $customersResult['errors'] + $productsResult['errors']
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync full ERPLY data: ' . $e->getMessage(),
                'error_details' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
            ], 500);
        }
    }

    public function getCustomers(): JsonResponse
    {
        try {
            $customers = \App\Models\ErplyCustomer::with('variations')->get();
            
            return response()->json([
                'success' => true,
                'data' => $customers,
                'count' => $customers->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch customers: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getProducts(): JsonResponse
    {
        try {
            $products = \App\Models\ErplyProduct::with('variations')->get();
            
            return response()->json([
                'success' => true,
                'data' => $products,
                'count' => $products->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch products: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getVariations($productId): JsonResponse
    {
        try {
            $product = \App\Models\ErplyProduct::findOrFail($productId);
            $variations = $product->variations;
            
            return response()->json([
                'success' => true,
                'data' => $variations,
                'count' => $variations->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch variations: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getMatrices(): JsonResponse
    {
        try {
            $matrices = \App\Models\ErplyProduct::where('type', 'matrix')->with('variations')->get();
            
            return response()->json([
                'success' => true,
                'data' => $matrices,
                'count' => $matrices->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch matrices: ' . $e->getMessage()
            ], 500);
        }
    }

    public function syncToXero(): JsonResponse
    {
        try {
            $customersResult = $this->xeroSyncService->syncCustomersToXero();
            $productsResult = $this->xeroSyncService->syncProductsToXero();
            
            return response()->json([
                'success' => true,
                'message' => 'Xero sync completed',
                'data' => [
                    'customers' => $customersResult,
                    'products' => $productsResult,
                    'total_synced' => $customersResult['synced'] + $productsResult['synced'],
                    'total_errors' => $customersResult['errors'] + $productsResult['errors']
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync to Xero: ' . $e->getMessage(),
                'error_details' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
            ], 500);
        }
    }

    public function syncCustomersToXero(): JsonResponse
    {
        try {
            $result = $this->xeroSyncService->syncCustomersToXero();
            
            return response()->json([
                'success' => true,
                'message' => 'Customer Xero sync completed',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync customers to Xero: ' . $e->getMessage(),
                'error_details' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
            ], 500);
        }
    }

    public function syncProductsToXero(): JsonResponse
    {
        try {
            $result = $this->xeroSyncService->syncProductsToXero();
            
            return response()->json([
                'success' => true,
                'message' => 'Product Xero sync completed',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync products to Xero: ' . $e->getMessage(),
                'error_details' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
            ], 500);
        }
    }

    public function retryFailed(): JsonResponse
    {
        try {
            $result = $this->xeroSyncService->retryFailedSyncs();
            
            return response()->json([
                'success' => true,
                'message' => 'Failed syncs retried',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retry failed syncs: ' . $e->getMessage(),
                'error_details' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
            ], 500);
        }
    }

    public function getStatus(): JsonResponse
    {
        try {
            $customers = \App\Models\ErplyCustomer::all();
            $products = \App\Models\ErplyProduct::all();
            $variations = \App\Models\ErplyProductVariation::all();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'customers' => [
                        'total' => $customers->count(),
                        'pending' => $customers->where('sync_status', 'pending')->count(),
                        'synced' => $customers->where('sync_status', 'synced_to_xero')->count(),
                        'failed' => $customers->where('sync_status', 'failed')->count(),
                        'skipped' => $customers->where('sync_status', 'skipped')->count()
                    ],
                    'products' => [
                        'total' => $products->count(),
                        'pending' => $products->where('sync_status', 'pending')->count(),
                        'synced' => $products->where('sync_status', 'synced_to_xero')->count(),
                        'failed' => $products->where('sync_status', 'failed')->count(),
                        'skipped' => $products->where('sync_status', 'skipped')->count()
                    ],
                    'variations' => [
                        'total' => $variations->count(),
                        'pending' => $variations->where('sync_status', 'pending')->count(),
                        'synced' => $variations->where('sync_status', 'synced_to_xero')->count(),
                        'failed' => $variations->where('sync_status', 'failed')->count(),
                        'skipped' => $variations->where('sync_status', 'skipped')->count()
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get sync status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Incremental customer sync using changedSince parameter
     */
    public function syncCustomersIncremental(Request $request): JsonResponse
    {
        try {
            // Cast debug query parameter to boolean
            $debug = filter_var($request->query('debug', false), FILTER_VALIDATE_BOOLEAN);

            // Optional: allow page & limit via query params
            $page = (int) $request->query('page', 1);
            $limit = (int) $request->query('limit', 100);

            // Use autoSync mode to automatically process customers
            $result = $this->erplyService->getCustomers($page, $limit, $debug, null, true);

            return response()->json([
                'success' => true,
                'message' => $debug ? 'Incremental customer fetch (debug) completed' : 'Incremental customer sync completed',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync customers incrementally: ' . $e->getMessage(),
                'error_details' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
            ], 500);
        }
    }

    /**
     * Get sync status for all entities
     */
    public function getSyncStatus(): JsonResponse
    {
        try {
            $result = $this->erplyService->getSyncStatus();

            return response()->json([
                'success' => true,
                'message' => 'Sync status retrieved successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get sync status: ' . $e->getMessage(),
                'error_details' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
            ], 500);
        }
    }
}
