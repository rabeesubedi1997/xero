<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\XeroToken;
use XeroAPI\XeroPHP\Models\Accounting\Contact;
use XeroAPI\XeroPHP\Models\Accounting\Contacts;
use XeroAPI\XeroPHP\Models\Accounting\Phone;
use XeroAPI\XeroPHP\Models\Accounting\Address;
use Exception;

class CustomerService
{
    private $xeroService;

    public function __construct(XeroService $xeroService)
    {
        $this->xeroService = $xeroService;
    }

    /**
     * Create a new local customer
     */
    public function createLocal(array $data): Customer
    {
        $customer = Customer::create($data);

        \Log::info('CustomerService::createLocal - Customer created', [
            'customer_id' => $customer->id,
            'customer_name' => $customer->name
        ]);

        return $customer;
    }

    /**
     * Get valid tenant ID from XeroToken
     */
    private function getValidTenantId(): string
    {
        $token = XeroToken::first();

        if (!$token) {
            throw new Exception('No Xero connection found. Please authenticate with Xero first.');
        }

        // Check if token is expired and refresh if needed
        if ($token->isExpired()) {
            \Log::info('CustomerService::getValidTenantId - Token expired, refreshing', [
                'tenant_id' => $token->tenant_id
            ]);

            $this->xeroService->refreshAccessToken($token->tenant_id);

            // Reload token after refresh
            $token = XeroToken::findByTenantId($token->tenant_id);

            \Log::info('CustomerService::getValidTenantId - Token refreshed successfully', [
                'tenant_id' => $token->tenant_id
            ]);
        }

        \Log::info('CustomerService::getValidTenantId - Valid tenant ID retrieved', [
            'tenant_id' => $token->tenant_id,
            'tenant_name' => $token->tenant_name
        ]);

        return $token->tenant_id;
    }

    /**
     * Sync a local customer to Xero
     */
    public function syncToXero(Customer $customer, string $tenantId = null): Customer
    {
        try {
            // Get tenant ID (use provided or fetch from XeroToken)
            if (!$tenantId) {
                $tenantId = $this->getValidTenantId();
            }

            \Log::info('CustomerService::syncToXero - Starting sync', [
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'tenant_id' => $tenantId
            ]);

            // Prepare contact data for Xero
            $contactData = $this->prepareContactData($customer);

            // Create or update in Xero
            if ($customer->xero_customer_id) {
                // Update existing contact
                $result = $this->xeroService->updateContact(
                    $tenantId,
                    $customer->xero_customer_id,
                    $contactData
                );

                \Log::info('CustomerService::syncToXero - Contact updated in Xero', [
                    'customer_id' => $customer->id,
                    'xero_customer_id' => $customer->xero_customer_id
                ]);
            } else {
                // Create new contact
                $result = $this->xeroService->createContact($tenantId, $contactData);

                // Extract contact ID from response
                $xeroCustomerId = $this->extractContactIdFromResponse($result);

                if ($xeroCustomerId) {
                    $customer->markAsSynced($xeroCustomerId);

                    \Log::info('CustomerService::syncToXero - Contact created in Xero', [
                        'customer_id' => $customer->id,
                        'xero_customer_id' => $xeroCustomerId
                    ]);
                } else {
                    throw new Exception('Failed to extract Xero Contact ID from response');
                }
            }

            // Mark as synced
            if ($customer->customer_pending === 1) {
                $customer->markAsSynced($customer->xero_customer_id);
            }

            return $customer;
        } catch (Exception $e) {
            \Log::error('CustomerService::syncToXero - Sync failed', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Sync all pending customers to Xero
     */
    public function syncAllPending(): array
    {
        try {
            // Get valid tenant ID once for all syncs
            $tenantId = $this->getValidTenantId();

            $pendingCustomers = Customer::pending()->get();

            \Log::info('CustomerService::syncAllPending - Found pending customers', [
                'pending_count' => $pendingCustomers->count(),
                'tenant_id' => $tenantId
            ]);

            $results = [
                'successful' => 0,
                'failed' => 0,
                'errors' => [],
                'tenant_id' => $tenantId
            ];

            if ($pendingCustomers->isEmpty()) {
                \Log::info('CustomerService::syncAllPending - No pending customers to sync');
                return $results;
            }

            foreach ($pendingCustomers as $customer) {
                try {
                    $this->syncToXero($customer, $tenantId);
                    $results['successful']++;

                    \Log::info('CustomerService::syncAllPending - Customer synced', [
                        'customer_id' => $customer->id,
                        'customer_name' => $customer->name
                    ]);
                } catch (Exception $e) {
                    $results['failed']++;
                    $results['errors'][] = [
                        'customer_id' => $customer->id,
                        'customer_name' => $customer->name,
                        'error' => $e->getMessage()
                    ];

                    \Log::error('CustomerService::syncAllPending - Customer sync failed', [
                        'customer_id' => $customer->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            \Log::info('CustomerService::syncAllPending - Batch sync completed', $results);

            return $results;
        } catch (Exception $e) {
            \Log::error('CustomerService::syncAllPending - Batch sync failed', [
                'error' => $e->getMessage()
            ]);

            return [
                'successful' => 0,
                'failed' => 0,
                'errors' => [
                    [
                        'error' => $e->getMessage()
                    ]
                ]
            ];
        }
    }

    /**
     * Prepare contact data for Xero API
     */
    private function prepareContactData(Customer $customer): Contact
    {
        $contact = new Contact();

        // Required field
        $contact->setName($customer->name);

        // Optional but recommended fields
        if ($customer->first_name) {
            $contact->setFirstName($customer->first_name);
        }

        if ($customer->last_name) {
            $contact->setLastName($customer->last_name);
        }

        if ($customer->email_address) {
            $contact->setEmailAddress($customer->email_address);
        }

        if ($customer->company_number) {
            $contact->setCompanyNumber($customer->company_number);
        }

        if ($customer->tax_number) {
            $contact->setTaxNumber($customer->tax_number);
        }

        // Add phone if available
        if ($customer->phone_number) {
            try {
                $phone = new Phone();
                $phone->setPhoneType('DEFAULT');
                $phone->setPhoneNumber($customer->phone_number);
                $contact->setPhones([$phone]);
            } catch (Exception $e) {
                \Log::warning('Failed to set phone', ['error' => $e->getMessage()]);
            }
        }

        // Add address if available
        if ($customer->address) {
            try {
                $address = new Address();
                $address->setAddressType('STREET');
                $address->setAddressLine1($customer->address);
                if ($customer->city) {
                    $address->setCity($customer->city);
                }
                if ($customer->postal_code) {
                    $address->setPostalCode($customer->postal_code);
                }
                if ($customer->country) {
                    $address->setCountry($customer->country);
                }
                $contact->setAddresses([$address]);
            } catch (Exception $e) {
                \Log::warning('Failed to set address', ['error' => $e->getMessage()]);
            }
        }

        // Set currency
        $contact->setDefaultCurrency('USD');

        return $contact;
    }

    /**
     * Extract contact ID from Xero API response
     */
    private function extractContactIdFromResponse($response): ?string
    {
        try {
            if (method_exists($response, 'getContacts')) {
                $contacts = $response->getContacts();
                if (!empty($contacts) && count($contacts) > 0) {
                    $contact = $contacts[0];
                    if (method_exists($contact, 'getContactID')) {
                        return $contact->getContactID();
                    }
                }
            }
        } catch (Exception $e) {
            \Log::warning('CustomerService::extractContactIdFromResponse - Failed to extract ID', [
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    /**
     * Update customer from Xero data
     */
    public function updateFromXeroData(Customer $customer, $xeroContactData): Customer
    {
        // Extract relevant fields from Xero contact
        if (method_exists($xeroContactData, 'getName')) {
            $customer->name = $xeroContactData->getName();
        }

        if (method_exists($xeroContactData, 'getFirstName')) {
            $customer->first_name = $xeroContactData->getFirstName();
        }

        if (method_exists($xeroContactData, 'getLastName')) {
            $customer->last_name = $xeroContactData->getLastName();
        }

        if (method_exists($xeroContactData, 'getEmailAddress')) {
            $customer->email_address = $xeroContactData->getEmailAddress();
        }

        if (method_exists($xeroContactData, 'getContactID')) {
            $customer->xero_customer_id = $xeroContactData->getContactID();
        }

        $customer->save();

        return $customer;
    }
}
