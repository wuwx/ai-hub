<?php

use App\Models\ApiKey;
use App\Models\LlmModel;
use App\Models\LlmProvider;
use App\Models\RequestLog;
use App\Models\UsageLedger;
use App\Models\User;
use Carbon\CarbonInterface;

it('casts fields correctly', function () {
    $user = User::factory()->create();

    $key = ApiKey::create([
        'user_id' => $user->id,
        'name' => 'prod-key',
        'key_hash' => 'hashed',
        'last_four' => 'abcd',
        'allowed_models' => ['gpt-4', 'gpt-3.5'],
        'daily_token_limit' => 1000000,
        'rate_limit_per_minute' => 600,
        'last_used_at' => now(),
        'expires_at' => now()->addDays(30),
        'revoked_at' => null,
        'created_by' => $user->id,
    ]);

    $fresh = $key->fresh();

    expect($fresh->allowed_models)->toBe(['gpt-4', 'gpt-3.5'])
        ->and($fresh->daily_token_limit)->toBe(1000000)
        ->and($fresh->rate_limit_per_minute)->toBe(600)
        ->and($fresh->last_used_at)->toBeInstanceOf(CarbonInterface::class)
        ->and($fresh->expires_at)->toBeInstanceOf(CarbonInterface::class)
        ->and($fresh->revoked_at)->toBeNull();
});

it('determines active status based on revoked and expired flags', function () {
    $user = User::factory()->create();

    $activeKey = ApiKey::create([
        'user_id' => $user->id,
        'name' => 'active',
        'key_hash' => 'h1',
        'last_four' => 'aaaa',
        'created_by' => $user->id,
    ]);

    $revokedKey = ApiKey::create([
        'user_id' => $user->id,
        'name' => 'revoked',
        'key_hash' => 'h2',
        'last_four' => 'bbbb',
        'revoked_at' => now(),
        'created_by' => $user->id,
    ]);

    $expiredKey = ApiKey::create([
        'user_id' => $user->id,
        'name' => 'expired',
        'key_hash' => 'h3',
        'last_four' => 'cccc',
        'expires_at' => now()->subDay(),
        'created_by' => $user->id,
    ]);

    expect($activeKey->isActive())->toBeTrue()
        ->and($activeKey->isRevoked())->toBeFalse()
        ->and($activeKey->isExpired())->toBeFalse()
        ->and($revokedKey->isActive())->toBeFalse()
        ->and($revokedKey->isRevoked())->toBeTrue()
        ->and($expiredKey->isActive())->toBeFalse()
        ->and($expiredKey->isExpired())->toBeTrue();
});

it('allows all models when allowed_models is empty', function () {
    $user = User::factory()->create();

    $key = ApiKey::create([
        'user_id' => $user->id,
        'name' => 'open',
        'key_hash' => 'h',
        'last_four' => 'aaaa',
        'allowed_models' => null,
        'created_by' => $user->id,
    ]);

    expect($key->canAccessModel('gpt-4'))->toBeTrue()
        ->and($key->canAccessModel('claude-3'))->toBeTrue();
});

it('restricts models to the configured whitelist', function () {
    $user = User::factory()->create();

    $key = ApiKey::create([
        'user_id' => $user->id,
        'name' => 'restricted',
        'key_hash' => 'h',
        'last_four' => 'aaaa',
        'allowed_models' => ['gpt-4'],
        'created_by' => $user->id,
    ]);

    expect($key->canAccessModel('gpt-4'))->toBeTrue()
        ->and($key->canAccessModel('claude-3'))->toBeFalse();
});

it('hashes plain text keys via the generate action', function () {
    $plain = 'sk-test-12345';

    $hash = ApiKey::hashPlainTextKey($plain);

    expect($hash)->toBeString()
        ->and($hash)->not->toBe($plain)
        ->and($hash)->toBe(ApiKey::hashPlainTextKey($plain));
});

it('relates to user, request logs and usage ledgers', function () {
    $user = User::factory()->create();

    $key = ApiKey::create([
        'user_id' => $user->id,
        'name' => 'relational',
        'key_hash' => 'h',
        'last_four' => 'aaaa',
        'created_by' => $user->id,
    ]);

    $provider = LlmProvider::create([
        'name' => 'Provider X',
        'slug' => 'provider-x',
        'adapter_type' => 'openai_compatible',
        'base_url' => 'https://api.example.com',
        'auth_mode' => 'bearer',
        'is_active' => true,
    ]);

    $model = LlmModel::create([
        'llm_provider_id' => $provider->id,
        'name' => 'Model X',
        'external_model_id' => 'model-x',
        'is_active' => true,
    ]);

    RequestLog::create([
        'user_id' => $user->id,
        'api_key_id' => $key->id,
        'llm_provider_id' => $provider->id,
        'llm_model_id' => $model->id,
        'protocol' => 'openai',
        'endpoint' => '/v1/chat/completions',
        'http_method' => 'POST',
        'status_code' => 200,
        'token_input' => 10,
        'token_output' => 5,
        'token_total' => 15,
        'latency_ms' => 100,
        'trace_id' => 't1',
        'requested_at' => now(),
    ]);

    UsageLedger::create([
        'user_id' => $user->id,
        'api_key_id' => $key->id,
        'llm_provider_id' => $provider->id,
        'llm_model_id' => $model->id,
        'bucket_date' => now()->toDateString(),
        'bucket_type' => 'day',
        'token_input' => 10,
        'token_output' => 5,
        'token_total' => 15,
        'request_count' => 1,
        'error_count' => 0,
    ]);

    expect($key->user->is($user))->toBeTrue()
        ->and($key->creator->is($user))->toBeTrue()
        ->and($key->requestLogs)->toHaveCount(1)
        ->and($key->usageLedgers)->toHaveCount(1);
});
