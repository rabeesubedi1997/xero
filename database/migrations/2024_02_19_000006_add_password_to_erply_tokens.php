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
        Schema::table('erply_tokens', function (Blueprint $table) {
            $table->string('password')->after('username'); // Add password column after username
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('erply_tokens', function (Blueprint $table) {
            $table->dropColumn('password'); // Remove password column
        });
    }
};
