<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\User;
use Database\Seeders\SubscriptionifySeeder;
use Tests\TestCase;

it('lists all active models via GET /v1/models in OpenAI-compatible format', function () {
    $user = User::factory()->create();

    (new SubscriptionifySeeder)->run();
    TestCase::subscribeUserToFreePlan($user);

    $provider = AiProvider::create([
        'name' => 'OpenAI',
        'slug' => 'openai-'.uniqid(),
        'adapter_type' => 'openai_compatible',
        'base_url' => 'https://api.openai.com',
        'auth_mode' => 'bearer',
        'secret_ref' => 'test-secret',
        'options' => [],
        'is_active' => true,
    ]);

    $modelA = AiModel::create([
        'ai_provider_id' => $provider->id,
        'name' => 'Model A',
        'external_model_id' => 'model-a',
        'is_active' => true,
    ]);

    $modelB = AiModel::create([
        'ai_provider_id' => $provider->id,
        'name' => 'Model B',
        'external_model_id' => 'model-b',
        'is_active' => true,
    ]);

    TestCase::entitleProvider($provider);

    $response = $this->withToken(
        $user->createToken('Gateway Access Key', ['*'], null)->plainTextToken
    )->getJson('/api/v1/models');

    $response->assertOk();
    $response->assertJsonPath('object', 'list');
    $response->assertJsonCount(2, 'data');
    $response->assertJsonFragment(['id' => $modelA->external_model_id]);
    $response->assertJsonFragment(['id' => $modelB->external_model_id]);
});

it('rejects GET /v1/models without a valid API key', function () {
    $response = $this->getJson('/api/v1/models');

    $response->assertStatus(401);
    $response->assertJsonPath('error.type', 'authentication_error');
});
