<?php

use App\Enums\TeamRole;
use App\Models\LlmModel;
use App\Models\LlmProvider;
use App\Models\Membership;
use App\Models\PlanModelEntitlement;
use App\Models\PlanProviderEntitlement;
use App\Models\RequestLog;
use App\Models\Team;
use App\Models\TeamWallet;
use App\Models\TeamWalletTransaction;
use App\Models\UsageLedger;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

it('relates membership to team and user with role cast', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $member = User::factory()->create();

    $membership = Membership::create([
        'team_id' => $team->id,
        'user_id' => $member->id,
        'role' => TeamRole::Admin->value,
    ]);

    expect($membership->team->is($team))->toBeTrue()
        ->and($membership->user->is($member))->toBeTrue()
        ->and($membership->role)->toBe(TeamRole::Admin);
});

it('relates plan model entitlements to plan code and model with boolean cast', function () {
    $provider = LlmProvider::create([
        'name' => 'Provider A',
        'slug' => 'provider-a',
        'adapter_type' => 'openai_compatible',
        'base_url' => 'https://api.example.com',
        'auth_mode' => 'bearer',
        'is_active' => true,
    ]);
    $model = LlmModel::create([
        'llm_provider_id' => $provider->id,
        'name' => 'Model A',
        'external_model_id' => 'model-a',
        'is_active' => true,
    ]);

    $entitlement = PlanModelEntitlement::create([
        'plan_code' => 'free',
        'llm_model_id' => $model->id,
        'is_enabled' => 1,
    ]);

    expect($entitlement->llmModel->is($model))->toBeTrue()
        ->and($entitlement->is_enabled)->toBeTrue();
});

it('relates plan provider entitlements to plan code and provider with boolean cast', function () {
    $provider = LlmProvider::create([
        'name' => 'Provider B',
        'slug' => 'provider-b',
        'adapter_type' => 'openai_compatible',
        'base_url' => 'https://api.example.com',
        'auth_mode' => 'bearer',
        'is_active' => true,
    ]);

    $entitlement = PlanProviderEntitlement::create([
        'plan_code' => 'free',
        'llm_provider_id' => $provider->id,
        'is_enabled' => true,
    ]);

    expect($entitlement->provider->is($provider))->toBeTrue()
        ->and($entitlement->is_enabled)->toBeTrue();
});

it('relates wallet transactions to team wallet and morph source with casts', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $wallet = TeamWallet::create([
        'team_id' => $team->id,
        'balance_cents' => 10000,
        'currency' => 'USD',
        'type' => 'prepaid',
    ]);

    $transaction = TeamWalletTransaction::create([
        'team_id' => $team->id,
        'team_wallet_id' => $wallet->id,
        'source_type' => Team::class,
        'source_id' => $team->id,
        'type' => 'recharge',
        'amount_cents' => 5000,
        'balance_after_cents' => 15000,
        'currency' => 'USD',
        'description' => 'Stripe recharge',
        'metadata' => ['stripe_pi' => 'pi_123'],
        'reference_id' => 'ref-1',
    ]);

    $fresh = $transaction->fresh();

    expect($fresh->team->is($team))->toBeTrue()
        ->and($fresh->wallet->is($wallet))->toBeTrue()
        ->and($fresh->source->is($team))->toBeTrue()
        ->and($fresh->amount_cents)->toBe(5000)
        ->and($fresh->balance_after_cents)->toBe(15000)
        ->and($fresh->metadata)->toBe(['stripe_pi' => 'pi_123']);
});

it('relates usage ledger to team api key provider and model with casts', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;

    $provider = LlmProvider::create([
        'name' => 'Provider L',
        'slug' => 'provider-l',
        'adapter_type' => 'openai_compatible',
        'base_url' => 'https://api.example.com',
        'auth_mode' => 'bearer',
        'is_active' => true,
    ]);
    $model = LlmModel::create([
        'llm_provider_id' => $provider->id,
        'name' => 'Model L',
        'external_model_id' => 'model-l',
        'is_active' => true,
    ]);
    $apiKey = $team->apiKeys()->create([
        'name' => 'k',
        'key_hash' => 'h',
        'last_four' => 'aaaa',
        'created_by' => $owner->id,
    ]);

    $ledger = UsageLedger::create([
        'team_id' => $team->id,
        'api_key_id' => $apiKey->id,
        'llm_provider_id' => $provider->id,
        'llm_model_id' => $model->id,
        'bucket_date' => now()->toDateString(),
        'bucket_type' => 'day',
        'token_input' => 100,
        'token_output' => 50,
        'token_total' => 150,
        'request_count' => 5,
        'error_count' => 1,
    ]);

    $fresh = $ledger->fresh();

    expect($fresh->team->is($team))->toBeTrue()
        ->and($fresh->apiKey->is($apiKey))->toBeTrue()
        ->and($fresh->provider->is($provider))->toBeTrue()
        ->and($fresh->llmModel->is($model))->toBeTrue()
        ->and($fresh->bucket_date)->toBeInstanceOf(CarbonInterface::class)
        ->and($fresh->token_total)->toBe(150)
        ->and($fresh->request_count)->toBe(5)
        ->and($fresh->error_count)->toBe(1);
});

it('relates request log to team api key provider and model with casts', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;

    $provider = LlmProvider::create([
        'name' => 'Provider P',
        'slug' => 'provider-p',
        'adapter_type' => 'openai_compatible',
        'base_url' => 'https://api.example.com',
        'auth_mode' => 'bearer',
        'is_active' => true,
    ]);
    $model = LlmModel::create([
        'llm_provider_id' => $provider->id,
        'name' => 'Model P',
        'external_model_id' => 'model-p',
        'is_active' => true,
    ]);
    $apiKey = $team->apiKeys()->create([
        'name' => 'k',
        'key_hash' => 'h',
        'last_four' => 'aaaa',
        'created_by' => $owner->id,
    ]);

    $log = RequestLog::create([
        'trace_id' => 'trace-1',
        'team_id' => $team->id,
        'api_key_id' => $apiKey->id,
        'llm_provider_id' => $provider->id,
        'llm_model_id' => $model->id,
        'protocol' => 'openai',
        'endpoint' => '/v1/chat/completions',
        'http_method' => 'POST',
        'is_streaming' => true,
        'tool_calls_count' => 2,
        'status_code' => 200,
        'token_input' => 10,
        'token_output' => 5,
        'token_total' => 15,
        'latency_ms' => 250,
        'error_code' => null,
        'error_message' => null,
        'requested_at' => now(),
    ]);

    $fresh = $log->fresh();

    expect($fresh->team->is($team))->toBeTrue()
        ->and($fresh->apiKey->is($apiKey))->toBeTrue()
        ->and($fresh->provider->is($provider))->toBeTrue()
        ->and($fresh->llmModel->is($model))->toBeTrue()
        ->and($fresh->is_streaming)->toBeTrue()
        ->and($fresh->tool_calls_count)->toBe(2)
        ->and($fresh->status_code)->toBe(200)
        ->and($fresh->latency_ms)->toBe(250)
        ->and($fresh->requested_at)->toBeInstanceOf(CarbonInterface::class);
});

it('relates llm model to provider entitlements request logs usage ledgers and invoice items', function () {
    $provider = LlmProvider::create([
        'name' => 'Provider M',
        'slug' => 'provider-m',
        'adapter_type' => 'openai_compatible',
        'base_url' => 'https://api.example.com',
        'auth_mode' => 'bearer',
        'is_active' => true,
    ]);

    $model = LlmModel::create([
        'llm_provider_id' => $provider->id,
        'name' => 'Model M',
        'external_model_id' => 'model-m',
        'is_active' => true,
    ]);

    expect($model->provider->is($provider))->toBeTrue()
        ->and($model->planEntitlements)->toBeInstanceOf(Collection::class)
        ->and($model->requestLogs)->toBeInstanceOf(Collection::class)
        ->and($model->usageLedgers)->toBeInstanceOf(Collection::class)
        ->and($model->billingInvoiceItems)->toBeInstanceOf(Collection::class);
});

it('relates llm provider to models entitlements request logs and usage ledgers', function () {
    $provider = LlmProvider::create([
        'name' => 'Provider N',
        'slug' => 'provider-n',
        'adapter_type' => 'openai_compatible',
        'base_url' => 'https://api.example.com',
        'auth_mode' => 'bearer',
        'is_active' => true,
        'options' => ['timeout' => 30],
    ]);

    $fresh = $provider->fresh();

    expect($fresh->options)->toBe(['timeout' => 30])
        ->and($fresh->is_active)->toBeTrue()
        ->and($fresh->models)->toBeInstanceOf(Collection::class)
        ->and($fresh->planEntitlements)->toBeInstanceOf(Collection::class)
        ->and($fresh->requestLogs)->toBeInstanceOf(Collection::class)
        ->and($fresh->usageLedgers)->toBeInstanceOf(Collection::class);
});

it('returns owner via team owner relation', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;

    $teamOwner = $team->owner();

    expect($teamOwner)->not->toBeNull()
        ->and($teamOwner->is($owner))->toBeTrue()
        ->and($team->members)->toHaveCount(1);
});
