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
        Schema::create('team_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->unique()->constrained()->cascadeOnDelete();
            // Stored in cents (integer). Negative means the team owes us
            // (post-paid overage). Pre-paid mode should never go below 0.
            $table->bigInteger('balance_cents')->default(0);
            $table->bigInteger('credit_grant_cents')->default(0);
            $table->string('currency', 3)->default('USD');
            $table->boolean('is_postpaid')->default(false);
            $table->timestamp('last_recharged_at')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'is_postpaid']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_wallets');
    }
};
