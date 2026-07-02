<?php

use App\Actions\ApiKeys\GenerateApiKey;
use App\Actions\Billing\RechargeTeamWallet;
use App\Models\LlmModel;
use App\Models\LlmProvider;
use App\Models\PlanModelEntitlement;
use App\Models\PlanProviderEntitlement;
use App\Models\TeamQuotaPolicy;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = $this->user->currentTeam;

    TeamQuotaPolicy::create([
        'team_id' => $this->team->id,
        'plan_code' => 'free',
        'daily_token_limit' => 100000,
        'monthly_token_limit' => 1000000,
        'effective_from' => now()->subMinute(),
        'is_active' => true,
    ]);

    $this->provider = LlmProvider::create([
        'name' => 'OpenAI Mock',
        'slug' => 'ratelimit-test-'.uniqid(),
        'adapter_type' => 'openai_compatible',
        'base_url' => 'https://openai.mock',
        'auth_mode' => 'bearer',
        'secret_ref' => 'test-secret',
        'options' => ['endpoints' => ['chat' => '/v1/chat/completions']],
        'is_active' => true,
    ]);

    $this->model = LlmModel::create([
        'llm_provider_id' => $this->provider->id,
        'name' => 'GPT-4.1',
        'external_model_id' => 'gpt-4.1',
        'is_active' => true,
    ]);

    PlanProviderEntitlement::create([
        'plan_code' => 'free',
        'llm_provider_id' => $this->provider->id,
        'is_enabled' => true,
    ]);

    PlanModelEntitlement::create([
        'plan_code' => 'free',
        'llm_model_id' => $this->model->id,
        'is_enabled' => true,
    ]);

    app(RechargeTeamWallet::class)->handle(
        team: $this->team,
        amountCents: 100_00,
        description: 'Test seed balance',
    );

    $this->apiKey = app(GenerateApiKey::class)->handle(
        team: $this->team,
        name: 'Rate Limit Test Key',
        createdBy: $this->user->id,
    );

    Http::fake([
        'https://openai.mock/v1/chat/completions' => Http::response([
            'id' => 'chatcmpl_test',
            'object' => 'chat.completion',
            'choices' => [['index' => 0, 'finish_reason' => 'stop', 'message' => ['role' => 'assistant', 'content' => 'ok']]],
            'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 3, 'total_tokens' => 8],
        ], 200),
    ]);
});

it('includes rate limit headers on successful responses', function () {
    $response = $this->withToken($this->apiKey->plainTextKey)->postJson('/api/v1/chat/completions', [
        'model' => 'gpt-4.1',
        'messages' => [['role' => 'user', 'content' => 'hello']],
    ]);

    $response->assertOk();
    $response->assertHeader('X-RateLimit-Limit');
    $response->assertHeader('X-RateLimit-Remaining');
    $response->assertHeader('X-RateLimit-Reset');
});

it('X-RateLimit-Remaining decreases after each request', function () {
    $first = $this->withToken($this->apiKey->plainTextKey)->postJson('/api/v1/chat/completions', [
        'model' => 'gpt-4.1',
        'messages' => [['role' => 'user', 'content' => 'hello']],
    ]);

    $second = $this->withToken($this->apiKey->plainTextKey)->postJson('/api/v1/chat/completions', [
        'model' => 'gpt-4.1',
        'messages' => [['role' => 'user', 'content' => 'world']],
    ]);

    $firstRemaining = (int) $first->headers->get('X-RateLimit-Remaining');
    $secondRemaining = (int) $second->headers->get('X-RateLimit-Remaining');

    expect($secondRemaining)->toBeLessThan($firstRemaining);
});

it('includes rate limit headers on 429 responses', function () {
    // Set a very low rate limit for this test
    config(['services.llm_gateway.api_key_rate_limit_per_minute' => 1]);

    // First request succeeds
    $this->withToken($this->apiKey->plainTextKey)->postJson('/api/v1/chat/completions', [
        'model' => 'gpt-4.1',
        'messages' => [['role' => 'user', 'content' => 'hello']],
    ]);

    // Second request should be rate limited
    $response = $this->withToken($this->apiKey->plainTextKey)->postJson('/api/v1/chat/completions', [
        'model' => 'gpt-4.1',
        'messages' => [['role' => 'user', 'content' => 'hello']],
    ]);

    $response->assertStatus(429);
    $response->assertHeader('X-RateLimit-Limit');
    $response->assertHeader('X-RateLimit-Remaining');
    $response->assertHeader('X-RateLimit-Reset');
    $response->assertHeader('Retry-After');
});
