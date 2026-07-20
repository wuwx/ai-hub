<?php

use App\Actions\Billing\SyncQuotaFromSubscription;
use App\Models\LlmModel;
use App\Models\LlmProvider;
use App\Models\User;
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

it('returns user api keys', function () {
    $owner = User::factory()->create();

    $owner->createToken('k');

    expect($owner->tokens)->toHaveCount(1)
        ->and($owner->tokens->first()->name)->toBe('k');
});
