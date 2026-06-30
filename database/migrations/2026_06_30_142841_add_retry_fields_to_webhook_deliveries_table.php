<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_deliveries', function (Blueprint $table) {
            $table->unsignedTinyInteger('attempt_count')->default(1)->after('succeeded');
            $table->timestamp('next_retry_at')->nullable()->after('attempt_count');
        });
    }

    public function down(): void
    {
        Schema::table('webhook_deliveries', function (Blueprint $table) {
            $table->dropColumn(['attempt_count', 'next_retry_at']);
        });
    }
};
