<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('erply_products', function (Blueprint $table) {
            $table->id();
            $table->string('erply_product_id')->unique();
            $table->unsignedBigInteger('erply_matrix_id')->nullable();
            $table->string('xero_product_id')->nullable()->unique();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('cost', 10, 2);
            $table->enum('type', ['matrix', 'variation'])->default('variation');
            $table->string('xero_account_code')->nullable();
            $table->string('xero_purchase_account_code')->nullable();
            $table->enum('sync_status', ['pending', 'synced_to_xero', 'failed', 'skipped'])->default('pending');
            $table->timestamp('last_synced_at')->nullable();
            $table->text('xero_sync_error')->nullable();
            $table->json('erply_data')->nullable();
            $table->json('xero_data')->nullable();
            $table->timestamps();
            
            $table->index('erply_product_id');
            $table->index('xero_product_id');
            $table->index('sync_status');
            $table->foreign('erply_matrix_id')->references('id')->on('erply_products')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erply_products');
    }
};
