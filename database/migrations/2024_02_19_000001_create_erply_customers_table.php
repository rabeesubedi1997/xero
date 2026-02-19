<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('erply_customers', function (Blueprint $table) {
            $table->id();
            $table->string('erply_customer_id')->unique();
            $table->string('xero_customer_id')->nullable()->unique();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->enum('sync_status', ['pending', 'synced_to_xero', 'failed', 'skipped'])->default('pending');
            $table->timestamp('last_synced_at')->nullable();
            $table->text('xero_sync_error')->nullable();
            $table->json('erply_data')->nullable();
            $table->json('xero_data')->nullable();
            $table->timestamps();
            
            $table->index('erply_customer_id');
            $table->index('xero_customer_id');
            $table->index('sync_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erply_customers');
    }
};
