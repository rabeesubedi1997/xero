<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_history', function (Blueprint $table) {
            $table->id();
            $table->enum('entity_type', ['customer', 'product', 'variation']);
            $table->unsignedBigInteger('erply_id');
            $table->string('xero_id')->nullable();
            $table->enum('sync_type', ['erply_to_db', 'db_to_xero', 'full_sync']);
            $table->enum('status', ['success', 'failed', 'partial']);
            $table->text('error_message')->nullable();
            $table->json('sync_data')->nullable();
            $table->timestamps();
            
            $table->index(['entity_type', 'erply_id']);
            $table->index('status');
            $table->index('sync_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_history');
    }
};
