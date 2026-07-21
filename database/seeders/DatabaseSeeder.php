<?php

namespace Database\Seeders;

use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Revoltify\Subscriptionify\Enums\FeatureType;
use Revoltify\Subscriptionify\Models\Feature;
use Revoltify\Subscriptionify\Models\Plan;

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
            SubscriptionifySeeder::class,
        ]);

        $this->seedAiProviders();
        $this->seedModelProviderFeatures();
    }

    private function seedAiProviders(): void
    {
        $openai = AiProvider::create([
            'name' => 'OpenAI',
            'slug' => 'openai',
            'adapter_type' => 'openai_compatible',
            'base_url' => 'https://api.openai.com/v1',
            'auth_mode' => 'bearer',
            'is_active' => true,
        ]);

        $anthropic = AiProvider::create([
            'name' => 'Anthropic',
            'slug' => 'anthropic',
            'adapter_type' => 'anthropic_compatible',
            'base_url' => 'https://api.anthropic.com',
            'auth_mode' => 'header',
            'is_active' => true,
        ]);

        $google = AiProvider::create([
            'name' => 'Google',
            'slug' => 'google',
            'adapter_type' => 'openai_compatible',
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai',
            'auth_mode' => 'bearer',
            'is_active' => true,
        ]);

        $ollama = AiProvider::create([
            'name' => 'Ollama',
            'slug' => 'ollama',
            'adapter_type' => 'openai_compatible',
            'base_url' => 'http://localhost:11434/v1',
            'auth_mode' => 'none',
            'is_active' => true,
        ]);

        $this->seedAiModels($openai, $anthropic, $google, $ollama);
    }

    private function seedAiModels(AiProvider $openai, AiProvider $anthropic, AiProvider $google, AiProvider $ollama): void
    {
        // OpenAI Models
        $gpt4o = AiModel::create([
            'ai_provider_id' => $openai->id,
            'name' => 'GPT-4o',
            'external_model_id' => 'gpt-4o',
            'capabilities' => ['chat', 'vision', 'function_calling'],
            'context_window' => 128000,
            'max_output_tokens' => 16384,
            'pricing' => [
                'input_per_1m_tokens' => 2.50,
                'output_per_1m_tokens' => 10.00,
            ],
            'is_active' => true,
        ]);

        AiModel::create([
            'ai_provider_id' => $openai->id,
            'name' => 'GPT-4o Mini',
            'external_model_id' => 'gpt-4o-mini',
            'capabilities' => ['chat', 'vision', 'function_calling'],
            'context_window' => 128000,
            'max_output_tokens' => 16384,
            'pricing' => [
                'input_per_1m_tokens' => 0.15,
                'output_per_1m_tokens' => 0.60,
            ],
            'is_active' => true,
        ]);

        AiModel::create([
            'ai_provider_id' => $openai->id,
            'name' => 'o3-mini',
            'external_model_id' => 'o3-mini',
            'capabilities' => ['chat', 'reasoning'],
            'context_window' => 200000,
            'max_output_tokens' => 100000,
            'pricing' => [
                'input_per_1m_tokens' => 1.10,
                'output_per_1m_tokens' => 4.40,
            ],
            'is_active' => true,
        ]);

        // Anthropic Models
        $claudeSonnet = AiModel::create([
            'ai_provider_id' => $anthropic->id,
            'name' => 'Claude Sonnet 4',
            'external_model_id' => 'claude-sonnet-4-20250514',
            'capabilities' => ['chat', 'vision', 'function_calling'],
            'context_window' => 200000,
            'max_output_tokens' => 8192,
            'pricing' => [
                'input_per_1m_tokens' => 3.00,
                'output_per_1m_tokens' => 15.00,
            ],
            'is_active' => true,
        ]);

        AiModel::create([
            'ai_provider_id' => $anthropic->id,
            'name' => 'Claude 3.5 Haiku',
            'external_model_id' => 'claude-3-5-haiku-20241022',
            'capabilities' => ['chat', 'vision', 'function_calling'],
            'context_window' => 200000,
            'max_output_tokens' => 8192,
            'pricing' => [
                'input_per_1m_tokens' => 0.80,
                'output_per_1m_tokens' => 4.00,
            ],
            'is_active' => true,
        ]);

        // Google Models
        AiModel::create([
            'ai_provider_id' => $google->id,
            'name' => 'Gemini 2.0 Flash',
            'external_model_id' => 'gemini-2.0-flash',
            'capabilities' => ['chat', 'vision', 'function_calling'],
            'context_window' => 1048576,
            'max_output_tokens' => 8192,
            'pricing' => [
                'input_per_1m_tokens' => 0.10,
                'output_per_1m_tokens' => 0.40,
            ],
            'is_active' => true,
        ]);

        AiModel::create([
            'ai_provider_id' => $google->id,
            'name' => 'Gemini 2.5 Pro',
            'external_model_id' => 'gemini-2.5-pro',
            'capabilities' => ['chat', 'vision', 'reasoning', 'function_calling'],
            'context_window' => 1048576,
            'max_output_tokens' => 65536,
            'pricing' => [
                'input_per_1m_tokens' => 1.25,
                'output_per_1m_tokens' => 10.00,
            ],
            'is_active' => true,
        ]);

        // Ollama Models (self-hosted, free)
        AiModel::create([
            'ai_provider_id' => $ollama->id,
            'name' => 'Llama 3.1 8B',
            'external_model_id' => 'llama3.1',
            'capabilities' => ['chat', 'function_calling'],
            'context_window' => 128000,
            'max_output_tokens' => 4096,
            'pricing' => [
                'input_per_1m_tokens' => 0.0,
                'output_per_1m_tokens' => 0.0,
            ],
            'is_active' => true,
        ]);

        AiModel::create([
            'ai_provider_id' => $ollama->id,
            'name' => 'Llama 3.1 70B',
            'external_model_id' => 'llama3.1:70b',
            'capabilities' => ['chat', 'function_calling'],
            'context_window' => 128000,
            'max_output_tokens' => 4096,
            'pricing' => [
                'input_per_1m_tokens' => 0.0,
                'output_per_1m_tokens' => 0.0,
            ],
            'is_active' => true,
        ]);

        AiModel::create([
            'ai_provider_id' => $ollama->id,
            'name' => 'Mistral 7B',
            'external_model_id' => 'mistral',
            'capabilities' => ['chat', 'function_calling'],
            'context_window' => 32768,
            'max_output_tokens' => 4096,
            'pricing' => [
                'input_per_1m_tokens' => 0.0,
                'output_per_1m_tokens' => 0.0,
            ],
            'is_active' => true,
        ]);

        AiModel::create([
            'ai_provider_id' => $ollama->id,
            'name' => 'Code Llama 13B',
            'external_model_id' => 'codellama:13b',
            'capabilities' => ['chat'],
            'context_window' => 16384,
            'max_output_tokens' => 4096,
            'pricing' => [
                'input_per_1m_tokens' => 0.0,
                'output_per_1m_tokens' => 0.0,
            ],
            'is_active' => true,
        ]);

        AiModel::create([
            'ai_provider_id' => $ollama->id,
            'name' => 'Qwen 2.5 7B',
            'external_model_id' => 'qwen2.5',
            'capabilities' => ['chat', 'function_calling'],
            'context_window' => 32768,
            'max_output_tokens' => 4096,
            'pricing' => [
                'input_per_1m_tokens' => 0.0,
                'output_per_1m_tokens' => 0.0,
            ],
            'is_active' => true,
        ]);

        AiModel::create([
            'ai_provider_id' => $ollama->id,
            'name' => 'DeepSeek R1 8B',
            'external_model_id' => 'deepseek-r1:8b',
            'capabilities' => ['chat', 'reasoning'],
            'context_window' => 32768,
            'max_output_tokens' => 4096,
            'pricing' => [
                'input_per_1m_tokens' => 0.0,
                'output_per_1m_tokens' => 0.0,
            ],
            'is_active' => true,
        ]);
    }

    /**
     * Grant every active plan access to every active provider and model via
     * Subscriptionify toggle features (namespaced slugs). Replaces the old
     * plan_entitlements tables.
     */
    private function seedModelProviderFeatures(): void
    {
        $plans = Plan::query()->whereIn('slug', ['free', 'pro', 'enterprise'])->get();

        if ($plans->isEmpty()) {
            return;
        }

        foreach (AiProvider::where('is_active', true)->get() as $provider) {
            $feature = Feature::query()->updateOrCreate(
                ['slug' => 'provider:'.$provider->slug],
                ['name' => $provider->name.' provider access', 'type' => FeatureType::Toggle, 'sort_order' => 100],
            );

            foreach ($plans as $plan) {
                $plan->features()->syncWithoutDetaching([$feature->getKey() => ['value' => '1']]);
            }
        }

        foreach (AiModel::where('is_active', true)->get() as $model) {
            $feature = Feature::query()->updateOrCreate(
                ['slug' => 'model:'.$model->external_model_id],
                ['name' => $model->name.' model access', 'type' => FeatureType::Toggle, 'sort_order' => 100],
            );

            foreach ($plans as $plan) {
                $plan->features()->syncWithoutDetaching([$feature->getKey() => ['value' => '1']]);
            }
        }
    }
}
