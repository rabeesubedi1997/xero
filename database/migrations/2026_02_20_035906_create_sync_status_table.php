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
        Schema::create('sync_status', function (Blueprint $table) {
            $table->id();
            $table->enum('entity_type', ['customer', 'product'])->unique();
            $table->dateTime('last_sync_date')->nullable();
            $table->enum('last_sync_status', ['success', 'failed', 'in_progress'])->default('in_progress');
            $table->integer('total_records_synced')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamps();
            
            $table->index(['entity_type', 'last_sync_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_status');
    }
};
