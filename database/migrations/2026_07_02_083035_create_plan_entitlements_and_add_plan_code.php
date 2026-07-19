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
        // Add plan_code to team_quota_policies so the gateway can resolve
        // a team's current plan and look up plan-level entitlements.
        Schema::table('team_quota_policies', function (Blueprint $table) {
            $table->string('plan_code')->default('free')->after('team_id');
        });

        // Plan-level model entitlements: defines which models each plan can access.
        Schema::create('plan_model_entitlements', function (Blueprint $table) {
            $table->id();
            $table->string('plan_code');
            $table->foreignId('llm_model_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['plan_code', 'llm_model_id'], 'plan_model_entitlement_unique');
            $table->index(['plan_code', 'is_enabled']);
        });

        // Plan-level provider entitlements: defines which providers each plan can access.
        Schema::create('plan_provider_entitlements', function (Blueprint $table) {
            $table->id();
            $table->string('plan_code');
            $table->foreignId('llm_provider_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['plan_code', 'llm_provider_id'], 'plan_provider_entitlement_unique');
            $table->index(['plan_code', 'is_enabled']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('team_quota_policies', function (Blueprint $table) {
            $table->dropColumn('plan_code');
        });

        Schema::dropIfExists('plan_provider_entitlements');
        Schema::dropIfExists('plan_model_entitlements');
    }
};
