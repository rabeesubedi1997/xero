<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('xero_customer_id')->unique()->nullable()->comment('Xero ContactID');
            $table->string('name')->comment('Full name of contact/organisation (Required)');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email_address')->nullable();
            $table->string('company_number', 50)->nullable()->comment('Company registration number');
            $table->string('tax_number', 50)->nullable()->comment('Tax/VAT/GST number');
            $table->string('phone_number')->nullable();
            $table->longText('address')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->nullable();
            $table->tinyInteger('customer_pending')->default(1)->comment('1=not synced, 0=synced');
            $table->timestamp('synced_at')->nullable()->comment('When customer was synced to Xero');
            $table->timestamps();

            // Indexes for better query performance
            $table->index('customer_pending');
            $table->index('xero_customer_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
