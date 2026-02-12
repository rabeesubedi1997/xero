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
        Schema::create('xero_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->unique(); // Xero organization ID
            $table->text('access_token'); // Will be encrypted
            $table->text('refresh_token'); // Will be encrypted
            $table->timestamp('expires_at')->nullable(); // Token expiry time
            $table->string('tenant_name')->nullable(); // Xero organization name
            $table->timestamps();
            
            // Index for faster lookups
            $table->index('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('xero_tokens');
    }
};
