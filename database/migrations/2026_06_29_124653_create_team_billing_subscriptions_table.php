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
        Schema::create('team_billing_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('payment_provider', 32)->default('stripe');
            $table->string('stripe_customer_id')->nullable();
            $table->string('stripe_subscription_id')->nullable();
            $table->string('plan_code', 64)->default('free');
            $table->string('status', 32)->default('inactive');
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique('team_id');
            $table->unique('stripe_subscription_id');
            $table->index(['status', 'plan_code'], 'team_billing_subscription_status_plan_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_billing_subscriptions');
    }
};
