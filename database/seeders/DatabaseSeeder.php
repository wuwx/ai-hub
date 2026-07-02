<?php

namespace Database\Seeders;

use App\Models\LlmModel;
use App\Models\LlmProvider;
use App\Models\PlanModelEntitlement;
use App\Models\PlanProviderEntitlement;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call([
            PlanSeeder::class,
        ]);

        $this->seedLlmProviders();
        $this->seedPlanEntitlements();
    }

    private function seedLlmProviders(): void
    {
        $openai = LlmProvider::create([
            'name' => 'OpenAI',
            'slug' => 'openai',
            'adapter_type' => 'openai_compatible',
            'base_url' => 'https://api.openai.com/v1',
            'auth_mode' => 'bearer',
            'is_active' => true,
        ]);

        $anthropic = LlmProvider::create([
            'name' => 'Anthropic',
            'slug' => 'anthropic',
            'adapter_type' => 'anthropic_compatible',
            'base_url' => 'https://api.anthropic.com',
            'auth_mode' => 'header',
            'is_active' => true,
        ]);

        $google = LlmProvider::create([
            'name' => 'Google',
            'slug' => 'google',
            'adapter_type' => 'openai_compatible',
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai',
            'auth_mode' => 'bearer',
            'is_active' => true,
        ]);

        $ollama = LlmProvider::create([
            'name' => 'Ollama',
            'slug' => 'ollama',
            'adapter_type' => 'openai_compatible',
            'base_url' => 'http://localhost:11434/v1',
            'auth_mode' => 'none',
            'is_active' => true,
        ]);

        $this->seedLlmModels($openai, $anthropic, $google, $ollama);
    }

    private function seedLlmModels(LlmProvider $openai, LlmProvider $anthropic, LlmProvider $google, LlmProvider $ollama): void
    {
        // OpenAI Models
        $gpt4o = LlmModel::create([
            'llm_provider_id' => $openai->id,
            'name' => 'GPT-4o',
            'external_model_id' => 'gpt-4o',
            'capabilities' => ['chat', 'vision', 'function_calling'],
            'context_window' => 128000,
            'max_output_tokens' => 16384,
            'cost_input_per_1m_usd' => 2.50,
            'cost_output_per_1m_usd' => 10.00,
            'sell_input_per_1m_usd' => 3.75,
            'sell_output_per_1m_usd' => 15.00,
            'markup_percent' => 50,
            'is_active' => true,
        ]);

        LlmModel::create([
            'llm_provider_id' => $openai->id,
            'name' => 'GPT-4o Mini',
            'external_model_id' => 'gpt-4o-mini',
            'capabilities' => ['chat', 'vision', 'function_calling'],
            'context_window' => 128000,
            'max_output_tokens' => 16384,
            'cost_input_per_1m_usd' => 0.15,
            'cost_output_per_1m_usd' => 0.60,
            'sell_input_per_1m_usd' => 0.23,
            'sell_output_per_1m_usd' => 0.90,
            'markup_percent' => 50,
            'is_active' => true,
            'fallback_model_id' => $gpt4o->id,
        ]);

        LlmModel::create([
            'llm_provider_id' => $openai->id,
            'name' => 'o3-mini',
            'external_model_id' => 'o3-mini',
            'capabilities' => ['chat', 'reasoning'],
            'context_window' => 200000,
            'max_output_tokens' => 100000,
            'cost_input_per_1m_usd' => 1.10,
            'cost_output_per_1m_usd' => 4.40,
            'sell_input_per_1m_usd' => 1.65,
            'sell_output_per_1m_usd' => 6.60,
            'markup_percent' => 50,
            'is_active' => true,
        ]);

        // Anthropic Models
        $claudeSonnet = LlmModel::create([
            'llm_provider_id' => $anthropic->id,
            'name' => 'Claude Sonnet 4',
            'external_model_id' => 'claude-sonnet-4-20250514',
            'capabilities' => ['chat', 'vision', 'function_calling'],
            'context_window' => 200000,
            'max_output_tokens' => 8192,
            'cost_input_per_1m_usd' => 3.00,
            'cost_output_per_1m_usd' => 15.00,
            'sell_input_per_1m_usd' => 4.50,
            'sell_output_per_1m_usd' => 22.50,
            'markup_percent' => 50,
            'is_active' => true,
        ]);

        LlmModel::create([
            'llm_provider_id' => $anthropic->id,
            'name' => 'Claude 3.5 Haiku',
            'external_model_id' => 'claude-3-5-haiku-20241022',
            'capabilities' => ['chat', 'vision', 'function_calling'],
            'context_window' => 200000,
            'max_output_tokens' => 8192,
            'cost_input_per_1m_usd' => 0.80,
            'cost_output_per_1m_usd' => 4.00,
            'sell_input_per_1m_usd' => 1.20,
            'sell_output_per_1m_usd' => 6.00,
            'markup_percent' => 50,
            'is_active' => true,
            'fallback_model_id' => $claudeSonnet->id,
        ]);

        // Google Models
        LlmModel::create([
            'llm_provider_id' => $google->id,
            'name' => 'Gemini 2.0 Flash',
            'external_model_id' => 'gemini-2.0-flash',
            'capabilities' => ['chat', 'vision', 'function_calling'],
            'context_window' => 1048576,
            'max_output_tokens' => 8192,
            'cost_input_per_1m_usd' => 0.10,
            'cost_output_per_1m_usd' => 0.40,
            'sell_input_per_1m_usd' => 0.15,
            'sell_output_per_1m_usd' => 0.60,
            'markup_percent' => 50,
            'is_active' => true,
        ]);

        LlmModel::create([
            'llm_provider_id' => $google->id,
            'name' => 'Gemini 2.5 Pro',
            'external_model_id' => 'gemini-2.5-pro',
            'capabilities' => ['chat', 'vision', 'reasoning', 'function_calling'],
            'context_window' => 1048576,
            'max_output_tokens' => 65536,
            'cost_input_per_1m_usd' => 1.25,
            'cost_output_per_1m_usd' => 10.00,
            'sell_input_per_1m_usd' => 1.88,
            'sell_output_per_1m_usd' => 15.00,
            'markup_percent' => 50,
            'is_active' => true,
        ]);

        // Ollama Models (self-hosted, free)
        LlmModel::create([
            'llm_provider_id' => $ollama->id,
            'name' => 'Llama 3.1 8B',
            'external_model_id' => 'llama3.1',
            'capabilities' => ['chat', 'function_calling'],
            'context_window' => 128000,
            'max_output_tokens' => 4096,
            'cost_input_per_1m_usd' => 0,
            'cost_output_per_1m_usd' => 0,
            'sell_input_per_1m_usd' => 0,
            'sell_output_per_1m_usd' => 0,
            'markup_percent' => 0,
            'is_active' => true,
        ]);

        LlmModel::create([
            'llm_provider_id' => $ollama->id,
            'name' => 'Llama 3.1 70B',
            'external_model_id' => 'llama3.1:70b',
            'capabilities' => ['chat', 'function_calling'],
            'context_window' => 128000,
            'max_output_tokens' => 4096,
            'cost_input_per_1m_usd' => 0,
            'cost_output_per_1m_usd' => 0,
            'sell_input_per_1m_usd' => 0,
            'sell_output_per_1m_usd' => 0,
            'markup_percent' => 0,
            'is_active' => true,
        ]);

        LlmModel::create([
            'llm_provider_id' => $ollama->id,
            'name' => 'Mistral 7B',
            'external_model_id' => 'mistral',
            'capabilities' => ['chat', 'function_calling'],
            'context_window' => 32768,
            'max_output_tokens' => 4096,
            'cost_input_per_1m_usd' => 0,
            'cost_output_per_1m_usd' => 0,
            'sell_input_per_1m_usd' => 0,
            'sell_output_per_1m_usd' => 0,
            'markup_percent' => 0,
            'is_active' => true,
        ]);

        LlmModel::create([
            'llm_provider_id' => $ollama->id,
            'name' => 'Code Llama 13B',
            'external_model_id' => 'codellama:13b',
            'capabilities' => ['chat'],
            'context_window' => 16384,
            'max_output_tokens' => 4096,
            'cost_input_per_1m_usd' => 0,
            'cost_output_per_1m_usd' => 0,
            'sell_input_per_1m_usd' => 0,
            'sell_output_per_1m_usd' => 0,
            'markup_percent' => 0,
            'is_active' => true,
        ]);

        LlmModel::create([
            'llm_provider_id' => $ollama->id,
            'name' => 'Qwen 2.5 7B',
            'external_model_id' => 'qwen2.5',
            'capabilities' => ['chat', 'function_calling'],
            'context_window' => 32768,
            'max_output_tokens' => 4096,
            'cost_input_per_1m_usd' => 0,
            'cost_output_per_1m_usd' => 0,
            'sell_input_per_1m_usd' => 0,
            'sell_output_per_1m_usd' => 0,
            'markup_percent' => 0,
            'is_active' => true,
        ]);

        LlmModel::create([
            'llm_provider_id' => $ollama->id,
            'name' => 'DeepSeek R1 8B',
            'external_model_id' => 'deepseek-r1:8b',
            'capabilities' => ['chat', 'reasoning'],
            'context_window' => 32768,
            'max_output_tokens' => 4096,
            'cost_input_per_1m_usd' => 0,
            'cost_output_per_1m_usd' => 0,
            'sell_input_per_1m_usd' => 0,
            'sell_output_per_1m_usd' => 0,
            'markup_percent' => 0,
            'is_active' => true,
        ]);
    }

    private function seedPlanEntitlements(): void
    {
        $plans = ['free', 'pro', 'enterprise'];

        // Grant all plans access to all active providers and models.
        // Future: differentiate by plan (e.g., free = open-source only).
        $providers = LlmProvider::where('is_active', true)->get();
        $models = LlmModel::where('is_active', true)->get();

        foreach ($plans as $plan) {
            foreach ($providers as $provider) {
                PlanProviderEntitlement::create([
                    'plan_code' => $plan,
                    'llm_provider_id' => $provider->id,
                    'is_enabled' => true,
                ]);
            }

            foreach ($models as $model) {
                PlanModelEntitlement::create([
                    'plan_code' => $plan,
                    'llm_model_id' => $model->id,
                    'is_enabled' => true,
                ]);
            }
        }
    }
}
