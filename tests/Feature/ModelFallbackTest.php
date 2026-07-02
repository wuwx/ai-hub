<?php

use App\Actions\ApiKeys\GenerateApiKey;
use App\Actions\Billing\RechargeTeamWallet;
use App\Models\LlmModel;
use App\Models\LlmProvider;
use App\Models\PlanModelEntitlement;
use App\Models\PlanProviderEntitlement;
use App\Models\TeamQuotaPolicy;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = $this->user->currentTeam;

    app(RechargeTeamWallet::class)->handle($this->team, 100_00, 'Test balance');

    TeamQuotaPolicy::create([
        'team_id' => $this->team->id,
        'plan_code' => 'free',
        'daily_token_limit' => 100000,
        'monthly_token_limit' => 1000000,
        'effective_from' => now()->subMinute(),
        'is_active' => true,
    ]);

    // Primary provider (will be circuit-opened)
    $this->primaryProvider = LlmProvider::create([
        'name' => 'OpenAI Mock',
        'slug' => 'fallback-primary-'.uniqid(),
        'adapter_type' => 'openai_compatible',
        'base_url' => 'https://primary.mock',
        'auth_mode' => 'bearer',
        'secret_ref' => 'test-secret',
        'options' => ['endpoints' => ['chat' => '/v1/chat/completions']],
        'is_active' => true,
    ]);

    // Fallback provider
    $this->fallbackProvider = LlmProvider::create([
        'name' => 'Backup Mock',
        'slug' => 'fallback-backup-'.uniqid(),
        'adapter_type' => 'openai_compatible',
        'base_url' => 'https://backup.mock',
        'auth_mode' => 'bearer',
        'secret_ref' => 'test-secret',
        'options' => ['endpoints' => ['chat' => '/v1/chat/completions']],
        'is_active' => true,
    ]);

    $this->fallbackModel = LlmModel::create([
        'llm_provider_id' => $this->fallbackProvider->id,
        'name' => 'Backup Model',
        'external_model_id' => 'backup-model',
        'sell_input_per_1m_usd' => 1.0,
        'sell_output_per_1m_usd' => 2.0,
        'is_active' => true,
    ]);

    $this->primaryModel = LlmModel::create([
        'llm_provider_id' => $this->primaryProvider->id,
        'name' => 'Primary Model',
        'external_model_id' => 'primary-model',
        'sell_input_per_1m_usd' => 1.0,
        'sell_output_per_1m_usd' => 2.0,
        'is_active' => true,
        'fallback_model_id' => $this->fallbackModel->id,
    ]);

    // Entitle both providers and models to the plan
    PlanProviderEntitlement::create(['plan_code' => 'free', 'llm_provider_id' => $this->primaryProvider->id, 'is_enabled' => true]);
    PlanProviderEntitlement::create(['plan_code' => 'free', 'llm_provider_id' => $this->fallbackProvider->id, 'is_enabled' => true]);
    PlanModelEntitlement::create(['plan_code' => 'free', 'llm_model_id' => $this->primaryModel->id, 'is_enabled' => true]);
    PlanModelEntitlement::create(['plan_code' => 'free', 'llm_model_id' => $this->fallbackModel->id, 'is_enabled' => true]);

    $this->apiKey = app(GenerateApiKey::class)->handle(
        team: $this->team,
        name: 'Fallback Test Key',
        createdBy: $this->user->id,
    );
});

it('falls back to the fallback model when primary provider circuit is open', function () {
    // Open the circuit for the primary provider
    Cache::put(
        sprintf('gateway:circuit:provider:%d:open_until', $this->primaryProvider->id),
        now()->addMinutes(5)->timestamp,
        now()->addMinutes(5)
    );

    Http::fake([
        'https://backup.mock/v1/chat/completions' => Http::response([
            'id' => 'chatcmpl_fallback',
            'object' => 'chat.completion',
            'choices' => [['index' => 0, 'finish_reason' => 'stop', 'message' => ['role' => 'assistant', 'content' => 'fallback response']]],
            'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 3, 'total_tokens' => 8],
        ], 200),
    ]);

    $response = $this->withToken($this->apiKey->plainTextKey)->postJson('/api/v1/chat/completions', [
        'model' => 'primary-model',
        'messages' => [['role' => 'user', 'content' => 'hello']],
    ]);

    $response->assertOk();
    $response->assertJsonPath('choices.0.message.content', 'fallback response');

    // Verify the fallback provider was called, not the primary
    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'backup.mock');
    });
});

it('returns 503 when no fallback model is configured', function () {
    // Remove fallback config
    $this->primaryModel->update(['fallback_model_id' => null]);

    // Open the circuit
    Cache::put(
        sprintf('gateway:circuit:provider:%d:open_until', $this->primaryProvider->id),
        now()->addMinutes(5)->timestamp,
        now()->addMinutes(5)
    );

    Http::fake();

    $response = $this->withToken($this->apiKey->plainTextKey)->postJson('/api/v1/chat/completions', [
        'model' => 'primary-model',
        'messages' => [['role' => 'user', 'content' => 'hello']],
    ]);

    $response->assertStatus(503);
    $response->assertJsonPath('error.code', 'provider_circuit_open');
    Http::assertNothingSent();
});

it('returns 503 when fallback provider is also circuit-open', function () {
    // Open circuits for both providers
    Cache::put(
        sprintf('gateway:circuit:provider:%d:open_until', $this->primaryProvider->id),
        now()->addMinutes(5)->timestamp,
        now()->addMinutes(5)
    );
    Cache::put(
        sprintf('gateway:circuit:provider:%d:open_until', $this->fallbackProvider->id),
        now()->addMinutes(5)->timestamp,
        now()->addMinutes(5)
    );

    Http::fake();

    $response = $this->withToken($this->apiKey->plainTextKey)->postJson('/api/v1/chat/completions', [
        'model' => 'primary-model',
        'messages' => [['role' => 'user', 'content' => 'hello']],
    ]);

    $response->assertStatus(503);
    Http::assertNothingSent();
});

it('does not use fallback when primary is healthy', function () {
    Http::fake([
        'https://primary.mock/v1/chat/completions' => Http::response([
            'id' => 'chatcmpl_primary',
            'object' => 'chat.completion',
            'choices' => [['index' => 0, 'finish_reason' => 'stop', 'message' => ['role' => 'assistant', 'content' => 'primary response']]],
            'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 3, 'total_tokens' => 8],
        ], 200),
    ]);

    $response = $this->withToken($this->apiKey->plainTextKey)->postJson('/api/v1/chat/completions', [
        'model' => 'primary-model',
        'messages' => [['role' => 'user', 'content' => 'hello']],
    ]);

    $response->assertOk();
    $response->assertJsonPath('choices.0.message.content', 'primary response');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'primary.mock');
    });
});
