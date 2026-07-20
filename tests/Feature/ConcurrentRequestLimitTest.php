<?php

use App\Actions\ApiKeys\GenerateApiKey;
use App\Models\LlmModel;
use App\Models\LlmProvider;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->user = User::factory()->create();

    $this->subscribeUserToFreePlan($this->user);

    $provider = LlmProvider::create([
        'name' => 'OpenAI Mock',
        'slug' => 'concurrent-test-'.uniqid(),
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
        'is_active' => true,
    ]);

    $this->entitleProvider($provider);
    $this->entitleModel($model);

    $this->apiKey = app(GenerateApiKey::class)->handle(
        user: $this->user,
        name: 'Concurrent Test Key',
        createdBy: $this->user->id,
    );

    $this->apiKeyModel = $this->apiKey->apiKey;
});

it('allows requests when under the concurrent limit', function () {
    Http::fake([
        'https://openai.mock/v1/chat/completions' => Http::response(
            [
                'id' => 'test',
                'object' => 'chat.completion',
                'choices' => [
                    [
                        'index' => 0,
                        'finish_reason' => 'stop',
                        'message' => ['role' => 'assistant', 'content' => 'ok'],
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 5,
                    'completion_tokens' => 3,
                    'total_tokens' => 8,
                ],
            ],
            200,
        ),
    ]);

    $response = $this->withToken($this->apiKey->plainTextKey)->postJson(
        '/api/v1/chat/completions',
        [
            'model' => 'gpt-4.1',
            'messages' => [['role' => 'user', 'content' => 'hello']],
        ],
    );

    $response->assertOk();
});

it('blocks requests when concurrent limit is reached', function () {
    // Simulate max concurrent requests already in flight
    Cache::put(
        sprintf('gateway:concurrent:%d', $this->user->id),
        50,
        now()->addMinutes(10),
    );

    Http::fake();

    $response = $this->withToken($this->apiKey->plainTextKey)->postJson(
        '/api/v1/chat/completions',
        [
            'model' => 'gpt-4.1',
            'messages' => [['role' => 'user', 'content' => 'hello']],
        ],
    );

    $response->assertStatus(429);
    $response->assertJsonPath('error.code', 'too_many_concurrent_requests');
    Http::assertNothingSent();
});

it('decrements concurrent counter after request completes', function () {
    Http::fake([
        'https://openai.mock/v1/chat/completions' => Http::response(
            [
                'id' => 'test2',
                'object' => 'chat.completion',
                'choices' => [
                    [
                        'index' => 0,
                        'finish_reason' => 'stop',
                        'message' => ['role' => 'assistant', 'content' => 'ok'],
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 5,
                    'completion_tokens' => 3,
                    'total_tokens' => 8,
                ],
            ],
            200,
        ),
    ]);

    $this->withToken($this->apiKey->plainTextKey)->postJson(
        '/api/v1/chat/completions',
        [
            'model' => 'gpt-4.1',
            'messages' => [['role' => 'user', 'content' => 'hello']],
        ],
    );

    // After request completes, concurrent counter should be 0 (or not exist)
    $count = Cache::get(sprintf('gateway:concurrent:%d', $this->user->id), 0);
    expect($count)->toBe(0);
});
