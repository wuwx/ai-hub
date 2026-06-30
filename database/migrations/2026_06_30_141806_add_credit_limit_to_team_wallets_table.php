<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_wallets', function (Blueprint $table) {
            // Maximum negative balance (in cents) a post-paid team can accumulate.
            // Null means unlimited (NOT recommended for production).
            $table->unsignedBigInteger('credit_limit_cents')->nullable()->after('is_postpaid');
        });
    }

    public function down(): void
    {
        Schema::table('team_wallets', function (Blueprint $table) {
            $table->dropColumn('credit_limit_cents');
        });
    }
};
