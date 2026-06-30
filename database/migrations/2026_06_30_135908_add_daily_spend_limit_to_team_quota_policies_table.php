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
        Schema::table('team_quota_policies', function (Blueprint $table) {
            // Maximum daily spend in cents. Null means no spend cap (token limits still apply).
            $table->unsignedBigInteger('daily_spend_limit_cents')->nullable()->after('monthly_token_limit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('team_quota_policies', function (Blueprint $table) {
            $table->dropColumn('daily_spend_limit_cents');
        });
    }
};
