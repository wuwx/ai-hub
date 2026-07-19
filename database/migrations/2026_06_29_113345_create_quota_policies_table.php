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
        Schema::create('quota_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('daily_token_limit')->nullable();
            $table->unsignedBigInteger('weekly_token_limit')->nullable();
            $table->unsignedBigInteger('monthly_token_limit')->nullable();
            $table->unsignedTinyInteger('daily_alert_threshold')->default(80);
            $table->unsignedTinyInteger('monthly_alert_threshold')->default(80);
            $table->timestamp('effective_from');
            $table->timestamp('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
            $table->index(['user_id', 'effective_from', 'effective_to'], 'quota_policy_effective_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quota_policies');
    }
};
