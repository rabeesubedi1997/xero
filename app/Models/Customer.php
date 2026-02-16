<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'xero_customer_id',
        'name',
        'first_name',
        'last_name',
        'email_address',
        'company_number',
        'tax_number',
        'phone_number',
        'address',
        'city',
        'postal_code',
        'country',
        'customer_pending',
        'synced_at',
    ];

    protected $casts = [
        'synced_at' => 'datetime',
        'customer_pending' => 'integer',
    ];

    /**
     * Check if customer is pending sync to Xero
     */
    public function isPending(): bool
    {
        return $this->customer_pending === 1;
    }

    /**
     * Check if customer has been synced to Xero
     */
    public function isSynced(): bool
    {
        return $this->customer_pending === 0 && $this->xero_customer_id !== null;
    }

    /**
     * Mark customer as synced with Xero
     */
    public function markAsSynced(string $xeroCustomerId): void
    {
        $this->update([
            'xero_customer_id' => $xeroCustomerId,
            'customer_pending' => 0,
            'synced_at' => now(),
        ]);
    }

    /**
     * Mark customer as pending sync
     */
    public function markAsPending(): void
    {
        $this->update([
            'customer_pending' => 1,
            'synced_at' => null,
        ]);
    }

    /**
     * Prepare customer data for Xero API
     */
    public function toXeroArray(): array
    {
        return [
            'Name' => $this->name,
            'FirstName' => $this->first_name,
            'LastName' => $this->last_name,
            'EmailAddress' => $this->email_address,
            'CompanyNumber' => $this->company_number,
            'TaxNumber' => $this->tax_number,
            'Addresses' => $this->getFormattedAddresses(),
            'Phones' => $this->getFormattedPhones(),
            'DefaultCurrency' => 'USD', // Can be made configurable
        ];
    }

    /**
     * Format address data for Xero API
     */
    private function getFormattedAddresses(): ?array
    {
        if (!$this->address) {
            return null;
        }

        return [
            [
                'AddressType' => 'STREET',
                'AddressLine1' => $this->address,
                'City' => $this->city,
                'PostalCode' => $this->postal_code,
                'Country' => $this->country,
            ]
        ];
    }

    /**
     * Format phone data for Xero API
     */
    private function getFormattedPhones(): ?array
    {
        if (!$this->phone_number) {
            return null;
        }

        return [
            [
                'PhoneType' => 'DEFAULT',
                'PhoneNumber' => $this->phone_number,
            ]
        ];
    }

    /**
     * Scope to get pending customers
     */
    public function scopePending($query)
    {
        return $query->where('customer_pending', 1);
    }

    /**
     * Scope to get synced customers
     */
    public function scopeSynced($query)
    {
        return $query->where('customer_pending', 0)->whereNotNull('xero_customer_id');
    }
}
