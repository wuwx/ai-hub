<?php

use App\Actions\ApiKeys\GenerateApiKey;
use App\Actions\Billing\SyncQuotaFromSubscription;
use App\Models\LlmModel;
use App\Models\LlmProvider;
use App\Models\User;
use Database\Seeders\SubscriptionifySeeder;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

it('rejects openai requests when the upstream provider is anthropic compatible', function () {
    [$plainTextKey, $modelExternalId] = provisionGatewayTarget('anthropic_compatible', 'claude-3-7-sonnet');

    Http::fake();

    $response = $this->withToken($plainTextKey)->postJson('/api/v1/chat/completions', [
        'model' => $modelExternalId,
        'messages' => [
            ['role' => 'user', 'content' => 'hello'],
        ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('error.code', 'protocol_mismatch');
    Http::assertNothingSent();
});

it('rejects anthropic requests when the upstream provider is openai compatible', function () {
    [$plainTextKey, $modelExternalId] = provisionGatewayTarget('openai_compatible', 'gpt-4.1');

    Http::fake();

    $response = $this->withToken($plainTextKey)->postJson('/api/v1/messages', [
        'model' => $modelExternalId,
        'messages' => [
            ['role' => 'user', 'content' => 'hello'],
        ],
        'max_tokens' => 128,
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('type', 'error');
    $response->assertJsonPath('error.code', 'protocol_mismatch');
    Http::assertNothingSent();
});

it('forwards openai requests and responses as-is when the provider is openai compatible', function () {
    [$plainTextKey, $modelExternalId] = provisionGatewayTarget('openai_compatible', 'gpt-4.1');

    Http::fake([
        'https://openai.mock/v1/chat/completions' => function (HttpRequest $request) {
            expect($request['model'])->toBe('gpt-4.1');
            expect($request['messages'][0]['role'])->toBe('user');
            // Unknown fields are forwarded untouched (no protocol rewriting).
            expect($request['temperature'] ?? null)->toBe(0.7);

            return Http::response([
                'id' => 'chatcmpl_123',
                'object' => 'chat.completion',
                'choices' => [
                    [
                        'index' => 0,
                        'finish_reason' => 'stop',
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'Hello from OpenAI',
                        ],
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 8,
                    'completion_tokens' => 4,
                    'total_tokens' => 12,
                ],
            ], 200);
        },
    ]);

    $response = $this->withToken($plainTextKey)->postJson('/api/v1/chat/completions', [
        'model' => $modelExternalId,
        'messages' => [
            ['role' => 'user', 'content' => 'hello'],
        ],
        'temperature' => 0.7,
    ]);

    $response->assertOk();
    $response->assertJsonPath('choices.0.message.content', 'Hello from OpenAI');
    $response->assertJsonPath('usage.prompt_tokens', 8);
});

it('lists entitled models via GET /v1/models in OpenAI-compatible format', function () {
    [$plainTextKey, $modelExternalId] = provisionGatewayTarget('openai_compatible', 'gpt-4.1');

    $response = $this->withToken($plainTextKey)->getJson('/api/v1/models');

    $response->assertOk();
    $response->assertJsonPath('object', 'list');
    $response->assertJsonStructure([
        'object',
        'data' => [
            '*' => ['id', 'object', 'created', 'owned_by'],
        ],
    ]);

    $modelIds = collect($response->json('data'))->pluck('id');
    expect($modelIds)->toContain($modelExternalId);
});

it('lists all active models regardless of entitlement', function () {
    [$plainTextKey] = provisionGatewayTarget('openai_compatible', 'gpt-4.1');

    // Create a second provider+model that previously would have been hidden
    // behind an entitlement check. With gating removed it is now listed.
    $otherProvider = LlmProvider::create([
        'name' => 'Anthropic Direct',
        'slug' => 'anthropic-direct-'.uniqid(),
        'adapter_type' => 'anthropic_compatible',
        'base_url' => 'https://anthropic.mock',
        'auth_mode' => 'bearer',
        'secret_ref' => 'test-secret',
        'is_active' => true,
    ]);

    LlmModel::create([
        'llm_provider_id' => $otherProvider->id,
        'name' => 'CLAUDE-3-7-SONNET',
        'external_model_id' => 'claude-3-7-sonnet',
        'is_active' => true,
    ]);

    $response = $this->withToken($plainTextKey)->getJson('/api/v1/models');

    $response->assertOk();
    $modelIds = collect($response->json('data'))->pluck('id');
    expect($modelIds)->toContain('claude-3-7-sonnet');
});

it('rejects GET /v1/models without a valid API key', function () {
    $this->getJson('/api/v1/models')->assertUnauthorized();
});

function provisionGatewayTarget(string $adapterType, string $externalModelId): array
{
    $user = User::factory()->create();

    (new SubscriptionifySeeder)->run();
    app(SyncQuotaFromSubscription::class)->handle(user: $user, planCode: 'free');

    $provider = LlmProvider::create([
        'name' => 'Provider '.$adapterType,
        'slug' => 'provider-'.$adapterType.'-'.uniqid(),
        'adapter_type' => $adapterType,
        'base_url' => $adapterType === 'anthropic_compatible' ? 'https://anthropic.mock' : 'https://openai.mock',
        'auth_mode' => 'bearer',
        'secret_ref' => 'test-secret',
        'options' => [
            'endpoints' => [
                'chat' => '/v1/chat/completions',
                'responses' => '/v1/responses',
                'messages' => '/v1/messages',
            ],
        ],
        'is_active' => true,
    ]);

    $model = LlmModel::create([
        'llm_provider_id' => $provider->id,
        'name' => strtoupper($externalModelId),
        'external_model_id' => $externalModelId,
        'is_active' => true,
    ]);

    TestCase::entitleProvider($provider);
    TestCase::entitleModel($model);

    $apiKey = app(GenerateApiKey::class)->handle(
        user: $user,
        name: 'Gateway Access Key',
    );

    return [$apiKey->plainTextToken, $model->external_model_id];
}
