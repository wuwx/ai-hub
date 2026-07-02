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

    TeamQuotaPolicy::create([
        'team_id' => $this->team->id,
        'plan_code' => 'free',
        'daily_token_limit' => 1000000,
        'monthly_token_limit' => 10000000,
        'effective_from' => now()->subMinute(),
        'is_active' => true,
    ]);

    $provider = LlmProvider::create([
        'name' => 'OpenAI Mock',
        'slug' => 'concurrent-test-'.uniqid(),
        'adapter_type' => 'openai_compatible',
        'base_url' => 'https://openai.mock',
        'auth_mode' => 'bearer',
        'secret_ref' => 'test-secret',
        'options' => ['endpoints' => ['chat' => '/v1/chat/completions']],
        'is_active' => true,
    ]);

    $model = LlmModel::create([
        'llm_provider_id' => $provider->id,
        'name' => 'GPT-4.1',
        'external_model_id' => 'gpt-4.1',
        'sell_input_per_1m_usd' => 1.0,
        'sell_output_per_1m_usd' => 2.0,
        'is_active' => true,
    ]);

    PlanProviderEntitlement::create(['plan_code' => 'free', 'llm_provider_id' => $provider->id, 'is_enabled' => true]);
    PlanModelEntitlement::create(['plan_code' => 'free', 'llm_model_id' => $model->id, 'is_enabled' => true]);

    app(RechargeTeamWallet::class)->handle($this->team, 100_00, 'Test balance');

    $this->apiKey = app(GenerateApiKey::class)->handle(
        team: $this->team,
        name: 'Concurrent Test Key',
        createdBy: $this->user->id,
    );

    $this->apiKeyModel = $this->apiKey->apiKey;
});

it('allows requests when under the concurrent limit', function () {
    Http::fake([
        'https://openai.mock/v1/chat/completions' => Http::response([
            'id' => 'test',
            'object' => 'chat.completion',
            'choices' => [['index' => 0, 'finish_reason' => 'stop', 'message' => ['role' => 'assistant', 'content' => 'ok']]],
            'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 3, 'total_tokens' => 8],
        ], 200),
    ]);

    $response = $this->withToken($this->apiKey->plainTextKey)->postJson('/api/v1/chat/completions', [
        'model' => 'gpt-4.1',
        'messages' => [['role' => 'user', 'content' => 'hello']],
    ]);

    $response->assertOk();
});

it('blocks requests when concurrent limit is reached', function () {
    // Simulate max concurrent requests already in flight
    Cache::put(sprintf('gateway:concurrent:%d', $this->team->id), 50, now()->addMinutes(10));

    Http::fake();

    $response = $this->withToken($this->apiKey->plainTextKey)->postJson('/api/v1/chat/completions', [
        'model' => 'gpt-4.1',
        'messages' => [['role' => 'user', 'content' => 'hello']],
    ]);

    $response->assertStatus(429);
    $response->assertJsonPath('error.code', 'too_many_concurrent_requests');
    Http::assertNothingSent();
});

it('decrements concurrent counter after request completes', function () {
    Http::fake([
        'https://openai.mock/v1/chat/completions' => Http::response([
            'id' => 'test2',
            'object' => 'chat.completion',
            'choices' => [['index' => 0, 'finish_reason' => 'stop', 'message' => ['role' => 'assistant', 'content' => 'ok']]],
            'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 3, 'total_tokens' => 8],
        ], 200),
    ]);

    $this->withToken($this->apiKey->plainTextKey)->postJson('/api/v1/chat/completions', [
        'model' => 'gpt-4.1',
        'messages' => [['role' => 'user', 'content' => 'hello']],
    ]);

    // After request completes, concurrent counter should be 0 (or not exist)
    $count = Cache::get(sprintf('gateway:concurrent:%d', $this->team->id), 0);
    expect($count)->toBe(0);
});
