<?php

use App\Actions\ApiKeys\GenerateApiKey;
use App\Actions\Billing\DebitTeamWallet;
use App\Exceptions\InsufficientWalletBalanceException;
use App\Models\LlmModel;
use App\Models\LlmProvider;
use App\Models\TeamModelEntitlement;
use App\Models\TeamProviderEntitlement;
use App\Models\TeamWallet;
use App\Models\User;
use Illuminate\Support\Facades\Http;

it('blocks post-paid debit when credit limit is exceeded', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $wallet = TeamWallet::create([
        'team_id' => $team->id,
        'balance_cents' => 0,
        'credit_grant_cents' => 0,
        'currency' => 'USD',
        'is_postpaid' => true,
        'credit_limit_cents' => 1000, // $10.00 max debt
    ]);

    // Debit $10 (exactly the limit, should pass)
    app(DebitTeamWallet::class)->handle($team, 1000, 'Test debit 1');

    expect($wallet->fresh()->balance_cents)->toBe(-1000);

    // Debit $1 more (should be blocked)
    expect(fn () => app(DebitTeamWallet::class)->handle($team, 100, 'Test debit 2'))
        ->toThrow(InsufficientWalletBalanceException::class);
});

it('allows post-paid debit within credit limit', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    TeamWallet::create([
        'team_id' => $team->id,
        'balance_cents' => 0,
        'credit_grant_cents' => 0,
        'currency' => 'USD',
        'is_postpaid' => true,
        'credit_limit_cents' => 5000, // $50 max debt
    ]);

    // Debit $30 — within limit
    $tx = app(DebitTeamWallet::class)->handle($team, 3000, 'Within limit');

    expect($tx)->not->toBeNull();
    expect($tx->balance_after_cents)->toBe(-3000);
});

it('hasEnoughBalance returns false when post-paid credit limit is exceeded', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    TeamWallet::create([
        'team_id' => $team->id,
        'balance_cents' => -900,
        'credit_grant_cents' => 0,
        'currency' => 'USD',
        'is_postpaid' => true,
        'credit_limit_cents' => 1000, // $10 limit, already $9 in debt
    ]);

    $debit = app(DebitTeamWallet::class);

    // $2 more — within limit (total debt $11 > $10 limit? No: -900 - 200 = -1100, |-1100| > 1000)
    expect($debit->hasEnoughBalance($team, 200))->toBeFalse();

    // $1 more — within limit (total debt $10 = limit)
    expect($debit->hasEnoughBalance($team, 100))->toBeTrue();
});

it('allows unlimited post-paid debit when credit_limit_cents is null', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    TeamWallet::create([
        'team_id' => $team->id,
        'balance_cents' => -100000,
        'credit_grant_cents' => 0,
        'currency' => 'USD',
        'is_postpaid' => true,
        'credit_limit_cents' => null,
    ]);

    $debit = app(DebitTeamWallet::class);

    expect($debit->hasEnoughBalance($team, 999999))->toBeTrue();
});

it('gateway rejects post-paid requests when credit limit is exceeded', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    // Set up provider/model/entitlements
    $provider = LlmProvider::create([
        'name' => 'OpenAI Mock',
        'slug' => 'credit-test-'.uniqid(),
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

    TeamProviderEntitlement::create([
        'team_id' => $team->id,
        'llm_provider_id' => $provider->id,
        'is_enabled' => true,
    ]);

    TeamModelEntitlement::create([
        'team_id' => $team->id,
        'llm_model_id' => $model->id,
        'is_enabled' => true,
    ]);

    $apiKey = app(GenerateApiKey::class)->handle(
        team: $team,
        name: 'Test Key',
        createdBy: $user->id,
    );

    // Set up post-paid wallet already beyond credit limit
    TeamWallet::create([
        'team_id' => $team->id,
        'balance_cents' => -1500,
        'credit_grant_cents' => 0,
        'currency' => 'USD',
        'is_postpaid' => true,
        'credit_limit_cents' => 1000,
    ]);

    Http::fake();

    $response = $this->withToken($apiKey->plainTextKey)->postJson('/api/v1/chat/completions', [
        'model' => 'gpt-4.1',
        'messages' => [['role' => 'user', 'content' => 'hello']],
    ]);

    $response->assertStatus(402);
    $response->assertJsonPath('error.code', 'insufficient_balance');
    Http::assertNothingSent();
});
