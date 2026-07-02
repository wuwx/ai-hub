<?php

use App\Actions\ApiKeys\GenerateApiKey;
use App\Actions\Usage\RecordApiRequestUsage;
use App\Exceptions\QuotaExceededException;
use App\Models\LlmModel;
use App\Models\LlmProvider;
use App\Models\QuotaPolicy;
use App\Models\UsageLedger;
use App\Models\User;

it('records request log and updates day/week/month ledgers', function () {
    $user = User::factory()->create();

    QuotaPolicy::create([
        'user_id' => $user->id,
        'daily_token_limit' => 10_000,
        'monthly_token_limit' => 50_000,
        'effective_from' => now()->subDay(),
        'is_active' => true,
    ]);

    $provider = LlmProvider::create([
        'name' => 'OpenAI Proxy',
        'slug' => 'openai-proxy',
        'adapter_type' => 'openai_compatible',
        'base_url' => 'https://api.example.com',
        'auth_mode' => 'bearer',
        'is_active' => true,
    ]);

    $model = LlmModel::create([
        'llm_provider_id' => $provider->id,
        'name' => 'GPT-4.1',
        'external_model_id' => 'gpt-4.1',
        'is_active' => true,
    ]);

    $apiKey = app(GenerateApiKey::class)->handle(
        user: $user,
        name: 'Gateway Key',
        createdBy: $user->id,
    )->apiKey;

    app(RecordApiRequestUsage::class)->handle(
        user: $user,
        protocol: 'openai',
        endpoint: '/v1/chat/completions',
        httpMethod: 'POST',
        tokenInput: 120,
        tokenOutput: 30,
        statusCode: 200,
        latencyMs: 840,
        apiKey: $apiKey,
        provider: $provider,
        llmModel: $model,
        traceId: 'trace_123',
    );

    $this->assertDatabaseHas('request_logs', [
        'user_id' => $user->id,
        'api_key_id' => $apiKey->id,
        'llm_provider_id' => $provider->id,
        'llm_model_id' => $model->id,
        'token_total' => 150,
        'status_code' => 200,
    ]);

    $todayLedger = UsageLedger::query()
        ->where('user_id', $user->id)
        ->where('bucket_type', 'day')
        ->whereDate('bucket_date', now()->toDateString())
        ->first();

    expect($todayLedger)->not->toBeNull();
    expect($todayLedger->token_total)->toBe(150);
    expect($todayLedger->request_count)->toBe(1);
    expect($todayLedger->error_count)->toBe(0);

    $monthLedger = UsageLedger::query()
        ->where('user_id', $user->id)
        ->where('bucket_type', 'month')
        ->whereDate('bucket_date', now()->startOfMonth()->toDateString())
        ->first();

    $weekLedger = UsageLedger::query()
        ->where('user_id', $user->id)
        ->where('bucket_type', 'week')
        ->whereDate('bucket_date', now()->startOfWeek()->toDateString())
        ->first();

    expect($weekLedger)->not->toBeNull();
    expect($weekLedger->token_total)->toBe(150);
    expect($weekLedger->request_count)->toBe(1);

    expect($monthLedger)->not->toBeNull();
    expect($monthLedger->token_total)->toBe(150);
    expect($monthLedger->request_count)->toBe(1);
});

it('increments error counters when status indicates failure', function () {
    $user = User::factory()->create();

    QuotaPolicy::create([
        'user_id' => $user->id,
        'daily_token_limit' => 10_000,
        'monthly_token_limit' => 50_000,
        'effective_from' => now()->subDay(),
        'is_active' => true,
    ]);

    app(RecordApiRequestUsage::class)->handle(
        user: $user,
        protocol: 'anthropic',
        endpoint: '/v1/messages',
        tokenInput: 80,
        tokenOutput: 20,
        statusCode: 500,
        errorCode: 'upstream_error',
    );

    $todayLedger = UsageLedger::query()
        ->where('user_id', $user->id)
        ->where('bucket_type', 'day')
        ->whereDate('bucket_date', now()->toDateString())
        ->first();

    expect($todayLedger)->not->toBeNull();
    expect($todayLedger->error_count)->toBe(1);
});

it('throws when recording would exceed token quota', function () {
    $user = User::factory()->create();

    QuotaPolicy::create([
        'user_id' => $user->id,
        'daily_token_limit' => 50,
        'monthly_token_limit' => 100,
        'effective_from' => now()->subDay(),
        'is_active' => true,
    ]);

    expect(fn () => app(RecordApiRequestUsage::class)->handle(
        user: $user,
        protocol: 'openai',
        endpoint: '/v1/responses',
        tokenInput: 45,
        tokenOutput: 10,
    ))->toThrow(QuotaExceededException::class);
});
