<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Customer 1: Full company details
        Customer::create([
            'name' => 'Tech Solutions Inc.',
            'first_name' => 'John',
            'last_name' => 'Anderson',
            'email_address' => 'john.anderson@techsolutions.com',
            'company_number' => '12345678',
            'tax_number' => '98-7654321',
            'phone_number' => '+1-555-0101',
            'address' => '123 Business Ave',
            'city' => 'San Francisco',
            'postal_code' => '94105',
            'country' => 'United States',
            'customer_pending' => 1, // Not synced yet
        ]);

        // Customer 2: Individual person with minimal details
        Customer::create([
            'name' => 'Sarah Mitchell',
            'first_name' => 'Sarah',
            'last_name' => 'Mitchell',
            'email_address' => 'sarah.mitchell@email.com',
            'phone_number' => '+1-555-0102',
            'address' => '456 Consulting Lane',
            'city' => 'New York',
            'postal_code' => '10001',
            'country' => 'United States',
            'customer_pending' => 1, // Not synced yet
        ]);

        // Customer 3: Company with complete details
        Customer::create([
            'name' => 'Global Enterprises Ltd.',
            'first_name' => 'Michael',
            'last_name' => 'Chen',
            'email_address' => 'michael.chen@globalenterprises.com',
            'company_number' => '87654321',
            'tax_number' => '45-6789012',
            'phone_number' => '+1-555-0103',
            'address' => '789 Enterprise Boulevard',
            'city' => 'Chicago',
            'postal_code' => '60601',
            'country' => 'United States',
            'customer_pending' => 1, // Not synced yet
        ]);
    }
}
