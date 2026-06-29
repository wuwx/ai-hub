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
        Schema::table('llm_providers', function (Blueprint $table) {
            $table->string('last_health_status')->nullable()->after('is_active');
            $table->timestamp('last_health_checked_at')->nullable()->after('last_health_status');
            $table->text('last_health_error')->nullable()->after('last_health_checked_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('llm_providers', function (Blueprint $table) {
            $table->dropColumn(['last_health_status', 'last_health_checked_at', 'last_health_error']);
        });
    }
};
