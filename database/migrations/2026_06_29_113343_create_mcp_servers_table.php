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
        Schema::create('mcp_servers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('endpoint');
            $table->string('transport')->default('streamable_http');
            $table->string('auth_mode')->default('none');
            $table->string('secret_ref')->nullable();
            $table->json('headers')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('last_health_status')->nullable();
            $table->timestamp('last_health_checked_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mcp_servers');
    }
};
