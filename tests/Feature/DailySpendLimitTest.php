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

    // Set a high token limit but a low spend limit
    $this->quotaPolicy = TeamQuotaPolicy::create([
        'team_id' => $this->team->id,
        'plan_code' => 'free',
        'daily_token_limit' => 1000000,
        'monthly_token_limit' => 10000000,
        'daily_spend_limit_cents' => 100, // $1.00 max per day
        'effective_from' => now()->subMinute(),
        'is_active' => true,
    ]);

    $this->provider = LlmProvider::create([
        'name' => 'OpenAI Mock',
        'slug' => 'spend-test-'.uniqid(),
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
        'sell_input_per_1m_usd' => 10.0, // $10/1M input tokens = expensive
        'sell_output_per_1m_usd' => 20.0, // $20/1M output tokens
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
        name: 'Spend Limit Test Key',
        createdBy: $this->user->id,
    );
});

it('blocks requests when daily spend limit is reached', function () {
    // Simulate prior spend by directly creating a debit transaction
    $this->team->walletTransactions()->create([
        'team_wallet_id' => $this->team->wallet->id,
        'type' => 'debit',
        'amount_cents' => 100, // exactly the limit
        'balance_after_cents' => 9900,
        'currency' => 'USD',
        'description' => 'Prior request',
    ]);

    Http::fake();

    $response = $this->withToken($this->apiKey->plainTextKey)->postJson('/api/v1/chat/completions', [
        'model' => 'gpt-4.1',
        'messages' => [['role' => 'user', 'content' => 'hello']],
    ]);

    $response->assertStatus(429);
    $response->assertJsonPath('error.code', 'quota_exceeded');
    Http::assertNothingSent();
});

it('allows requests when daily spend is below limit', function () {
    // Prior spend below the limit
    $this->team->walletTransactions()->create([
        'team_wallet_id' => $this->team->wallet->id,
        'type' => 'debit',
        'amount_cents' => 50, // $0.50, under the $1.00 limit
        'balance_after_cents' => 9950,
        'currency' => 'USD',
        'description' => 'Prior request',
    ]);

    Http::fake([
        'https://openai.mock/v1/chat/completions' => Http::response([
            'id' => 'chatcmpl_test',
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

it('does not enforce spend limit when not configured', function () {
    $this->quotaPolicy->update(['daily_spend_limit_cents' => null]);

    // Prior spend (doesn't matter, no limit set)
    $this->team->walletTransactions()->create([
        'team_wallet_id' => $this->team->wallet->id,
        'type' => 'debit',
        'amount_cents' => 99999,
        'balance_after_cents' => 100,
        'currency' => 'USD',
        'description' => 'Prior request',
    ]);

    Http::fake([
        'https://openai.mock/v1/chat/completions' => Http::response([
            'id' => 'chatcmpl_test2',
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
