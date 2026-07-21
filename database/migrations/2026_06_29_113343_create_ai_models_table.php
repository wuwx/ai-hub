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
        Schema::create('ai_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_provider_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('external_model_id');
            $table->json('capabilities')->nullable();
            $table->json('pricing')->nullable();
            $table->unsignedInteger('context_window')->nullable();
            $table->unsignedInteger('max_output_tokens')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['ai_provider_id', 'external_model_id']);
            $table->index(['is_active', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_models');
    }
};
