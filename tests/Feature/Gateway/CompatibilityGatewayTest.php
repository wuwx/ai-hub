<?php

use App\Actions\ApiKeys\GenerateApiKey;
use App\Models\LlmModel;
use App\Models\LlmProvider;
use App\Models\TeamModelEntitlement;
use App\Models\TeamProviderEntitlement;
use App\Models\TeamQuotaPolicy;
use App\Models\User;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

it('converts openai request/response when upstream provider is anthropic compatible', function () {
    [$plainTextKey, $modelExternalId] = provisionGatewayTargetForTeam('anthropic_compatible', 'claude-3-7-sonnet');

    Http::fake([
        'https://anthropic.mock/v1/messages' => function (HttpRequest $request) {
            expect($request['model'])->toBe('claude-3-7-sonnet');
            expect($request['messages'][0]['role'])->toBe('user');

            return Http::response([
                'id' => 'msg_123',
                'type' => 'message',
                'role' => 'assistant',
                'content' => [
                    ['type' => 'text', 'text' => 'Hello from Anthropic'],
                ],
                'usage' => [
                    'input_tokens' => 10,
                    'output_tokens' => 5,
                ],
            ], 200);
        },
    ]);

    $response = $this->withToken($plainTextKey)->postJson('/api/v1/chat/completions', [
        'model' => $modelExternalId,
        'messages' => [
            ['role' => 'user', 'content' => 'hello'],
        ],
    ]);

    $response->assertOk();
    $response->assertHeader('X-Trace-Id');
    $response->assertJsonPath('choices.0.message.role', 'assistant');
    $response->assertJsonPath('choices.0.message.content', 'Hello from Anthropic');
    $response->assertJsonPath('usage.prompt_tokens', 10);
});

it('converts anthropic request/response when upstream provider is openai compatible', function () {
    [$plainTextKey, $modelExternalId] = provisionGatewayTargetForTeam('openai_compatible', 'gpt-4.1');

    Http::fake([
        'https://openai.mock/v1/chat/completions' => function (HttpRequest $request) {
            expect($request['model'])->toBe('gpt-4.1');
            expect($request['messages'][0]['role'])->toBe('user');

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

    $response = $this->withToken($plainTextKey)->postJson('/api/v1/messages', [
        'model' => $modelExternalId,
        'messages' => [
            ['role' => 'user', 'content' => 'hello'],
        ],
        'max_tokens' => 128,
    ]);

    $response->assertOk();
    $response->assertHeader('X-Trace-Id');
    $response->assertJsonPath('type', 'message');
    $response->assertJsonPath('role', 'assistant');
    $response->assertJsonPath('content.0.text', 'Hello from OpenAI');
    $response->assertJsonPath('usage.input_tokens', 8);
});

it('replays cached idempotent responses without hitting upstream again', function () {
    [$plainTextKey, $modelExternalId] = provisionGatewayTargetForTeam('openai_compatible', 'gpt-4.1');

    $upstreamCalls = 0;

    Http::fake([
        'https://openai.mock/v1/chat/completions' => function () use (&$upstreamCalls) {
            $upstreamCalls++;

            return Http::response([
                'id' => 'chatcmpl_idempotent',
                'object' => 'chat.completion',
                'choices' => [
                    [
                        'index' => 0,
                        'finish_reason' => 'stop',
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'cached answer',
                        ],
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 6,
                    'completion_tokens' => 2,
                    'total_tokens' => 8,
                ],
            ], 200);
        },
    ]);

    $payload = [
        'model' => $modelExternalId,
        'messages' => [
            ['role' => 'user', 'content' => 'hello idempotent'],
        ],
    ];

    $first = $this->withToken($plainTextKey)
        ->withHeader('Idempotency-Key', 'idem-123')
        ->postJson('/api/v1/chat/completions', $payload);

    $second = $this->withToken($plainTextKey)
        ->withHeader('Idempotency-Key', 'idem-123')
        ->postJson('/api/v1/chat/completions', $payload);

    $first->assertOk();
    $second->assertOk();
    $second->assertHeader('X-Idempotent-Replay', 'true');
    expect($upstreamCalls)->toBe(1);
});

it('returns conflict when idempotency key is reused with a different payload', function () {
    [$plainTextKey, $modelExternalId] = provisionGatewayTargetForTeam('openai_compatible', 'gpt-4.1');

    Http::fake([
        'https://openai.mock/v1/chat/completions' => Http::response([
            'id' => 'chatcmpl_conflict',
            'object' => 'chat.completion',
            'choices' => [
                [
                    'index' => 0,
                    'finish_reason' => 'stop',
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'first payload response',
                    ],
                ],
            ],
            'usage' => [
                'prompt_tokens' => 5,
                'completion_tokens' => 3,
                'total_tokens' => 8,
            ],
        ], 200),
    ]);

    $firstPayload = [
        'model' => $modelExternalId,
        'messages' => [['role' => 'user', 'content' => 'first payload']],
    ];

    $secondPayload = [
        'model' => $modelExternalId,
        'messages' => [['role' => 'user', 'content' => 'different payload']],
    ];

    $first = $this->withToken($plainTextKey)
        ->withHeader('Idempotency-Key', 'idem-conflict')
        ->postJson('/api/v1/chat/completions', $firstPayload);

    $second = $this->withToken($plainTextKey)
        ->withHeader('Idempotency-Key', 'idem-conflict')
        ->postJson('/api/v1/chat/completions', $secondPayload);

    $first->assertOk();
    $second->assertStatus(409);
    $second->assertJsonPath('error.code', 'idempotency_payload_mismatch');
});

it('opens circuit breaker after repeated provider failures', function () {
    Cache::flush();

    config()->set('services.llm_gateway.retry_attempts', 1);
    config()->set('services.llm_gateway.circuit_failure_threshold', 2);
    config()->set('services.llm_gateway.circuit_cooldown_seconds', 120);

    [$plainTextKey, $modelExternalId] = provisionGatewayTargetForTeam('openai_compatible', 'gpt-4.1');

    $upstreamCalls = 0;

    Http::fake([
        'https://openai.mock/v1/chat/completions' => function () use (&$upstreamCalls) {
            $upstreamCalls++;

            return Http::response([
                'error' => [
                    'message' => 'upstream failure',
                    'code' => 'upstream_failure',
                ],
            ], 500);
        },
    ]);

    $payload = [
        'model' => $modelExternalId,
        'messages' => [
            ['role' => 'user', 'content' => 'trigger failure'],
        ],
    ];

    $first = $this->withToken($plainTextKey)->postJson('/api/v1/chat/completions', $payload);
    $second = $this->withToken($plainTextKey)->postJson('/api/v1/chat/completions', $payload);
    $third = $this->withToken($plainTextKey)->postJson('/api/v1/chat/completions', $payload);

    $first->assertStatus(500);
    $second->assertStatus(500);
    $third->assertStatus(503);
    $third->assertJsonPath('error.code', 'provider_circuit_open');
    expect($upstreamCalls)->toBe(2);
});

function provisionGatewayTargetForTeam(string $adapterType, string $externalModelId): array
{
    $user = User::factory()->create();
    $team = $user->currentTeam;

    TeamQuotaPolicy::create([
        'team_id' => $team->id,
        'daily_token_limit' => 100000,
        'monthly_token_limit' => 1000000,
        'effective_from' => now()->subMinute(),
        'is_active' => true,
    ]);

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
        name: 'Gateway Access Key',
        createdBy: $user->id,
    );

    return [$apiKey->plainTextKey, $model->external_model_id];
}
