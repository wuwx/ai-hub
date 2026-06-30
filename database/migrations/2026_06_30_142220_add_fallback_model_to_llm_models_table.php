<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('llm_models', function (Blueprint $table) {
            // When this model is unavailable (circuit open, 5xx), the gateway
            // will retry with the fallback model if configured.
            $table->foreignId('fallback_model_id')->nullable()->after('is_active')
                ->references('id')->on('llm_models')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('llm_models', function (Blueprint $table) {
            $table->dropForeign(['fallback_model_id']);
            $table->dropColumn('fallback_model_id');
        });
    }
};
