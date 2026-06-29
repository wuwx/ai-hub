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
        Schema::create('team_wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_wallet_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('source'); // stripe_charge, billing_invoice, request_log...
            $table->string('type'); // recharge, debit, refund, grant, adjustment
            $table->bigInteger('amount_cents'); // +credit / -debit
            $table->bigInteger('balance_after_cents');
            $table->string('currency', 3)->default('USD');
            $table->string('description')->nullable();
            $table->json('metadata')->nullable();
            $table->string('reference_id')->nullable(); // idempotency key / stripe charge id
            $table->timestamps();

            $table->index(['team_id', 'created_at']);
            $table->unique('reference_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_wallet_transactions');
    }
};
