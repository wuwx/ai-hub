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
        Schema::table('api_keys', function (Blueprint $table) {
            // JSON allow-list of model external_ids this key may use.
            // Empty/null means "all models the team is entitled to".
            $table->json('allowed_models')->nullable()->after('last_four');
            // Per-key daily token cap. Null means "no per-key cap" (team cap still applies).
            $table->unsignedBigInteger('daily_token_limit')->nullable()->after('allowed_models');
            // Per-key rate limit: requests per minute. Null = team default.
            $table->unsignedInteger('rate_limit_per_minute')->nullable()->after('daily_token_limit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropColumn(['allowed_models', 'daily_token_limit', 'rate_limit_per_minute']);
        });
    }
};
