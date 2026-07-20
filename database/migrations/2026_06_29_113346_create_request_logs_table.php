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
        Schema::create('request_logs', function (Blueprint $table) {
            $table->id();
            $table->string('trace_id', 64)->nullable();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('token_id')->nullable()->constrained('personal_access_tokens')->nullOnDelete();
            $table->foreignId('llm_provider_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('llm_model_id')->nullable()->constrained()->nullOnDelete();
            $table->string('protocol', 32);
            $table->string('endpoint');
            $table->string('http_method', 16);
            $table->boolean('is_streaming')->default(false);
            $table->unsignedSmallInteger('tool_calls_count')->default(0);
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->unsignedBigInteger('token_input')->default(0);
            $table->unsignedBigInteger('token_output')->default(0);
            $table->unsignedBigInteger('token_total')->default(0);
            $table->unsignedInteger('latency_ms')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamps();

            $table->index(['user_id', 'created_at'], 'request_logs_user_created_idx');
            $table->index(['llm_provider_id', 'created_at'], 'request_logs_provider_created_idx');
            $table->index(['llm_model_id', 'created_at'], 'request_logs_model_created_idx');
            $table->index('trace_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('request_logs');
    }
};
