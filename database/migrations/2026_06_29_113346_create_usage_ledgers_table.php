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
        Schema::create('usage_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('api_key_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('llm_provider_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('llm_model_id')->nullable()->constrained()->nullOnDelete();
            $table->date('bucket_date');
            $table->string('bucket_type', 16)->default('day');
            $table->unsignedBigInteger('token_input')->default(0);
            $table->unsignedBigInteger('token_output')->default(0);
            $table->unsignedBigInteger('token_total')->default(0);
            $table->unsignedInteger('request_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'bucket_type', 'bucket_date'], 'usage_ledger_user_bucket_idx');
            $table->index(['api_key_id', 'bucket_date'], 'usage_ledger_api_key_bucket_idx');
            $table->index(['llm_provider_id', 'llm_model_id', 'bucket_date'], 'usage_ledger_provider_model_bucket_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usage_ledgers');
    }
};
