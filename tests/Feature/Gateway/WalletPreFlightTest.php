<?php

use App\Actions\ApiKeys\GenerateApiKey;
use App\Actions\Billing\RechargeTeamWallet;
use App\Actions\Billing\ResolveModelPricing;
use App\Models\LlmModel;
use App\Models\LlmProvider;
use App\Models\PlanModelEntitlement;
use App\Models\PlanProviderEntitlement;
use App\Models\TeamQuotaPolicy;
use App\Models\User;
use Illuminate\Support\Facades\Http;

it('rejects requests with HTTP 402 when wallet is empty', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    TeamQuotaPolicy::create([
        'team_id' => $team->id,
        'plan_code' => 'free',
        'daily_token_limit' => 100000,
        'monthly_token_limit' => 1000000,
        'effective_from' => now()->subMinute(),
        'is_active' => true,
    ]);

    $provider = LlmProvider::create([
        'name' => 'P',
        'slug' => 'p-'.uniqid(),
        'adapter_type' => 'openai_compatible',
        'base_url' => 'https://openai.mock',
        'auth_mode' => 'bearer',
        'secret_ref' => 'test',
        'options' => ['endpoints' => ['chat' => '/v1/chat/completions']],
        'is_active' => true,
    ]);

    $model = LlmModel::create([
        'llm_provider_id' => $provider->id,
        'name' => 'M',
        'external_model_id' => 'm-no-balance',
        'sell_input_per_1m_usd' => 1.0,
        'sell_output_per_1m_usd' => 2.0,
        'is_active' => true,
    ]);

    PlanProviderEntitlement::create(['plan_code' => 'free', 'llm_provider_id' => $provider->id, 'is_enabled' => true]);
    PlanModelEntitlement::create(['plan_code' => 'free', 'llm_model_id' => $model->id, 'is_enabled' => true]);

    // No wallet recharge — balance is 0
    $apiKey = app(GenerateApiKey::class)->handle($team, 'K', createdBy: $user->id);

    Http::fake();

    $response = $this->withToken($apiKey->plainTextKey)->postJson('/api/v1/chat/completions', [
        'model' => 'm-no-balance',
        'messages' => [['role' => 'user', 'content' => 'hi']],
    ]);

    $response->assertStatus(402);
    $response->assertJsonPath('error.code', 'insufficient_balance');
    Http::assertNothingSent();
});

it('forwards the request when wallet has enough balance', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    TeamQuotaPolicy::create([
        'team_id' => $team->id,
        'plan_code' => 'free',
        'daily_token_limit' => 100000,
        'monthly_token_limit' => 1000000,
        'effective_from' => now()->subMinute(),
        'is_active' => true,
    ]);

    $provider = LlmProvider::create([
        'name' => 'P',
        'slug' => 'p-'.uniqid(),
        'adapter_type' => 'openai_compatible',
        'base_url' => 'https://openai.mock',
        'auth_mode' => 'bearer',
        'secret_ref' => 'test',
        'options' => ['endpoints' => ['chat' => '/v1/chat/completions']],
        'is_active' => true,
    ]);

    $model = LlmModel::create([
        'llm_provider_id' => $provider->id,
        'name' => 'M',
        'external_model_id' => 'm-with-balance',
        'sell_input_per_1m_usd' => 1.0,
        'sell_output_per_1m_usd' => 2.0,
        'is_active' => true,
    ]);

    PlanProviderEntitlement::create(['plan_code' => 'free', 'llm_provider_id' => $provider->id, 'is_enabled' => true]);
    PlanModelEntitlement::create(['plan_code' => 'free', 'llm_model_id' => $model->id, 'is_enabled' => true]);

    app(RechargeTeamWallet::class)->handle($team, 100_00, 'Seed');

    $apiKey = app(GenerateApiKey::class)->handle($team, 'K', createdBy: $user->id);

    Http::fake([
        'https://openai.mock/v1/chat/completions' => Http::response([
            'id' => 'ok',
            'object' => 'chat.completion',
            'choices' => [['index' => 0, 'finish_reason' => 'stop', 'message' => ['role' => 'assistant', 'content' => 'ok']]],
            'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 3, 'total_tokens' => 8],
        ], 200),
    ]);

    $response = $this->withToken($apiKey->plainTextKey)->postJson('/api/v1/chat/completions', [
        'model' => 'm-with-balance',
        'messages' => [['role' => 'user', 'content' => 'hi']],
    ]);

    $response->assertOk();
});

it('deducts the wallet in real-time after a successful request', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    TeamQuotaPolicy::create([
        'team_id' => $team->id,
        'plan_code' => 'free',
        'daily_token_limit' => 100000,
        'monthly_token_limit' => 1000000,
        'effective_from' => now()->subMinute(),
        'is_active' => true,
    ]);

    $provider = LlmProvider::create([
        'name' => 'P',
        'slug' => 'p-'.uniqid(),
        'adapter_type' => 'openai_compatible',
        'base_url' => 'https://openai.mock',
        'auth_mode' => 'bearer',
        'secret_ref' => 'test',
        'options' => ['endpoints' => ['chat' => '/v1/chat/completions']],
        'is_active' => true,
    ]);

    $model = LlmModel::create([
        'llm_provider_id' => $provider->id,
        'name' => 'M',
        'external_model_id' => 'm-debit',
        'sell_input_per_1m_usd' => 1.0,
        'sell_output_per_1m_usd' => 2.0,
        'is_active' => true,
    ]);

    PlanProviderEntitlement::create(['plan_code' => 'free', 'llm_provider_id' => $provider->id, 'is_enabled' => true]);
    PlanModelEntitlement::create(['plan_code' => 'free', 'llm_model_id' => $model->id, 'is_enabled' => true]);

    app(RechargeTeamWallet::class)->handle($team, 100_00, 'Seed');

    $apiKey = app(GenerateApiKey::class)->handle($team, 'K', createdBy: $user->id);

    // Single request with enough tokens to produce a non-zero charge.
    // 500K input @ $1/1M = $0.5 + 250K output @ $2/1M = $0.5 = $1.00 = 100 cents
    Http::fake([
        'https://openai.mock/v1/chat/completions' => Http::response([
            'id' => 'ok',
            'object' => 'chat.completion',
            'choices' => [['index' => 0, 'finish_reason' => 'stop', 'message' => ['role' => 'assistant', 'content' => 'ok']]],
            'usage' => ['prompt_tokens' => 500_000, 'completion_tokens' => 250_000, 'total_tokens' => 750_000],
        ], 200),
    ]);

    $this->withToken($apiKey->plainTextKey)->postJson('/api/v1/chat/completions', [
        'model' => 'm-debit',
        'messages' => [['role' => 'user', 'content' => 'hi']],
    ])->assertOk();

    $wallet = $team->wallet()->firstOrFail()->refresh();
    $expected = app(ResolveModelPricing::class)->chargeCents($model->refresh(), 500_000, 250_000);
    $debitCount = $team->walletTransactions()->where('type', 'debit')->count();

    expect($debitCount)->toBe(1)
        ->and($wallet->balance_cents)->toBe(10000 - $expected);
});
