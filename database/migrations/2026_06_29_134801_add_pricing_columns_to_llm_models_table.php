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
        Schema::table('llm_models', function (Blueprint $table) {
            // Explicit cost (what we pay upstream) and sell (what we charge
            // the customer) prices per 1M tokens, in USD. The legacy `pricing`
            // JSON column stays as a flexible fallback for cost-only rates.
            $table->decimal('cost_input_per_1m_usd', 12, 6)->default(0)->after('max_output_tokens');
            $table->decimal('cost_output_per_1m_usd', 12, 6)->default(0)->after('cost_input_per_1m_usd');
            $table->decimal('sell_input_per_1m_usd', 12, 6)->default(0)->after('cost_output_per_1m_usd');
            $table->decimal('sell_output_per_1m_usd', 12, 6)->default(0)->after('sell_input_per_1m_usd');
            $table->unsignedSmallInteger('markup_percent')->default(0)->after('sell_output_per_1m_usd');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('llm_models', function (Blueprint $table) {
            $table->dropColumn([
                'cost_input_per_1m_usd',
                'cost_output_per_1m_usd',
                'sell_input_per_1m_usd',
                'sell_output_per_1m_usd',
                'markup_percent',
            ]);
        });
    }
};
