<?php

use App\Actions\Billing\SyncQuotaFromSubscription;
use App\Models\LlmModel;
use App\Models\LlmProvider;
use App\Models\RequestLog;
use App\Models\UsageLedger;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Revoltify\Subscriptionify\Enums\FeatureType;
use Revoltify\Subscriptionify\Models\Feature;
use Revoltify\Subscriptionify\Models\Plan;

it('grants model access to a plan via a subscriptionify toggle feature', function () {
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

    $this->seedSubscriptionify();

    $feature = Feature::query()->updateOrCreate(
        ['slug' => 'model:'.$model->external_model_id],
        ['name' => $model->name.' access', 'type' => FeatureType::Toggle],
    );
    Plan::query()->where('slug', 'free')->firstOrFail()
        ->features()->syncWithoutDetaching([$feature->getKey() => ['value' => '1']]);

    $user = User::factory()->create();
    app(SyncQuotaFromSubscription::class)->handle(user: $user, planCode: 'free');

    expect($user->hasFeature('model:'.$model->external_model_id))->toBeTrue();
});

it('grants provider access to a plan via a subscriptionify toggle feature', function () {
    $provider = LlmProvider::create([
        'name' => 'Provider B',
        'slug' => 'provider-b',
        'adapter_type' => 'openai_compatible',
        'base_url' => 'https://api.example.com',
        'auth_mode' => 'bearer',
        'is_active' => true,
    ]);

    $this->seedSubscriptionify();

    $feature = Feature::query()->updateOrCreate(
        ['slug' => 'provider:'.$provider->slug],
        ['name' => $provider->name.' access', 'type' => FeatureType::Toggle],
    );
    Plan::query()->where('slug', 'free')->firstOrFail()
        ->features()->syncWithoutDetaching([$feature->getKey() => ['value' => '1']]);

    $user = User::factory()->create();
    app(SyncQuotaFromSubscription::class)->handle(user: $user, planCode: 'free');

    expect($user->hasFeature('provider:'.$provider->slug))->toBeTrue();
});

it('relates usage ledger to user api key provider and model with casts', function () {
    $owner = User::factory()->create();

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
    $apiKey = $owner->apiKeys()->create([
        'name' => 'k',
        'key_hash' => 'h',
        'last_four' => 'aaaa',
        'created_by' => $owner->id,
    ]);

    $ledger = UsageLedger::create([
        'user_id' => $owner->id,
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

    expect($fresh->user->is($owner))->toBeTrue()
        ->and($fresh->apiKey->is($apiKey))->toBeTrue()
        ->and($fresh->provider->is($provider))->toBeTrue()
        ->and($fresh->llmModel->is($model))->toBeTrue()
        ->and($fresh->bucket_date)->toBeInstanceOf(CarbonInterface::class)
        ->and($fresh->token_total)->toBe(150)
        ->and($fresh->request_count)->toBe(5)
        ->and($fresh->error_count)->toBe(1);
});

it('relates request log to user api key provider and model with casts', function () {
    $owner = User::factory()->create();

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
    $apiKey = $owner->apiKeys()->create([
        'name' => 'k',
        'key_hash' => 'h',
        'last_four' => 'aaaa',
        'created_by' => $owner->id,
    ]);

    $log = RequestLog::create([
        'trace_id' => 'trace-1',
        'user_id' => $owner->id,
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

    expect($fresh->user->is($owner))->toBeTrue()
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
        ->and($model->requestLogs)->toBeInstanceOf(Collection::class)
        ->and($model->usageLedgers)->toBeInstanceOf(Collection::class);
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
        ->and($fresh->requestLogs)->toBeInstanceOf(Collection::class)
        ->and($fresh->usageLedgers)->toBeInstanceOf(Collection::class);
});

it('returns user api keys', function () {
    $owner = User::factory()->create();

    $apiKey = $owner->apiKeys()->create([
        'name' => 'k',
        'key_hash' => 'h',
        'last_four' => 'aaaa',
        'created_by' => $owner->id,
    ]);

    expect($owner->apiKeys)->toHaveCount(1)
        ->and($owner->apiKeys->first()->id)->toBe($apiKey->id);
});
