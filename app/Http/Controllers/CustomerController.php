<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\CustomerService;
use App\Services\XeroService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CustomerController extends Controller
{
    private $customerService;
    private $xeroService;

    public function __construct(CustomerService $customerService, XeroService $xeroService)
    {
        $this->customerService = $customerService;
        $this->xeroService = $xeroService;
    }

    /**
     * Get all customers
     */
    public function index(): JsonResponse
    {
        try {
            $customers = Customer::all();

            return response()->json([
                'success' => true,
                'message' => 'Customers retrieved successfully',
                'data' => $customers,
                'total' => $customers->count(),
                'pending_count' => Customer::pending()->count(),
                'synced_count' => Customer::synced()->count(),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve customers',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get a single customer
     */
    public function show(Customer $customer): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $customer,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve customer',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new customer
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'first_name' => 'nullable|string|max:255',
                'last_name' => 'nullable|string|max:255',
                'email_address' => 'nullable|email|max:255',
                'company_number' => 'nullable|string|max:50',
                'tax_number' => 'nullable|string|max:50',
                'phone_number' => 'nullable|string',
                'address' => 'nullable|string',
                'city' => 'nullable|string',
                'postal_code' => 'nullable|string',
                'country' => 'nullable|string',
            ]);

            $customer = $this->customerService->createLocal($validated);

            return response()->json([
                'success' => true,
                'message' => 'Customer created successfully',
                'data' => $customer,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create customer',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a customer
     */
    public function update(Request $request, Customer $customer): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'nullable|string|max:255',
                'first_name' => 'nullable|string|max:255',
                'last_name' => 'nullable|string|max:255',
                'email_address' => 'nullable|email|max:255',
                'company_number' => 'nullable|string|max:50',
                'tax_number' => 'nullable|string|max:50',
                'phone_number' => 'nullable|string',
                'address' => 'nullable|string',
                'city' => 'nullable|string',
                'postal_code' => 'nullable|string',
                'country' => 'nullable|string',
            ]);

            $customer->update($validated);

            // Mark as pending if customer is already synced
            if ($customer->isSynced()) {
                $customer->markAsPending();
            }

            return response()->json([
                'success' => true,
                'message' => 'Customer updated successfully',
                'data' => $customer,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update customer',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a customer
     */
    public function destroy(Customer $customer): JsonResponse
    {
        try {
            $customerId = $customer->id;
            $customerName = $customer->name;

            $customer->delete();

            return response()->json([
                'success' => true,
                'message' => "Customer '{$customerName}' deleted successfully",
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete customer',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sync a single customer to Xero
     * Auto-fetches tenant_id from XeroToken database
     */
    public function syncToXero(Customer $customer): JsonResponse
    {
        try {
            // Tenant ID is fetched automatically from XeroToken in CustomerService
            $customer = $this->customerService->syncToXero($customer);

            return response()->json([
                'success' => true,
                'message' => 'Customer synced to Xero successfully',
                'data' => $customer,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync customer to Xero',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sync all pending customers to Xero
     * Auto-fetches tenant_id from XeroToken database
     */
    public function syncAllPending(): JsonResponse
    {
        try {
            // Tenant ID is fetched automatically from XeroToken in CustomerService
            $results = $this->customerService->syncAllPending();

            return response()->json([
                'success' => true,
                'message' => 'Batch sync completed',
                'data' => $results,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync customers',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get pending customers (not synced to Xero)
     */
    public function pending(): JsonResponse
    {
        try {
            $customers = Customer::pending()->get();

            return response()->json([
                'success' => true,
                'message' => 'Pending customers retrieved successfully',
                'data' => $customers,
                'total' => $customers->count(),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve pending customers',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get synced customers (synced to Xero)
     */
    public function synced(): JsonResponse
    {
        try {
            $customers = Customer::synced()->get();

            return response()->json([
                'success' => true,
                'message' => 'Synced customers retrieved successfully',
                'data' => $customers,
                'total' => $customers->count(),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve synced customers',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
