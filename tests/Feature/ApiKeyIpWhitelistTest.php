<?php

use App\Actions\ApiKeys\GenerateApiKey;
use App\Models\LlmModel;
use App\Models\LlmProvider;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->user = User::factory()->create();

    $this->subscribeUserToFreePlan($this->user);

    $this->provider = LlmProvider::create([
        'name' => 'OpenAI Mock',
        'slug' => 'openai-ip-test-'.uniqid(),
        'adapter_type' => 'openai_compatible',
        'base_url' => 'https://openai.mock',
        'auth_mode' => 'bearer',
        'secret_ref' => 'test-secret',
        'options' => ['endpoints' => ['chat' => '/v1/chat/completions']],
        'is_active' => true,
    ]);

    $this->model = LlmModel::create([
        'llm_provider_id' => $this->provider->id,
        'name' => 'GPT-4.1',
        'external_model_id' => 'gpt-4.1',
        'is_active' => true,
    ]);

    $this->entitleProvider($this->provider);
    $this->entitleModel($this->model);
});

it('allows requests from any IP when allowed_ips is empty', function () {
    $apiKey = app(GenerateApiKey::class)->handle(
        user: $this->user,
        name: 'No IP Restriction',
        createdBy: $this->user->id,
    );

    Http::fake([
        'https://openai.mock/v1/chat/completions' => Http::response(
            [
                'id' => 'chatcmpl_1',
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

    $response = $this->withToken($apiKey->plainTextKey)->postJson(
        '/api/v1/chat/completions',
        [
            'model' => 'gpt-4.1',
            'messages' => [['role' => 'user', 'content' => 'hello']],
        ],
    );

    $response->assertOk();
});

it('blocks requests from non-whitelisted IPs', function () {
    $apiKey = app(GenerateApiKey::class)->handle(
        user: $this->user,
        name: 'IP Restricted',
        createdBy: $this->user->id,
    );

    $apiKey->apiKey->update(['allowed_ips' => ['192.168.1.100']]);

    Http::fake();

    $response = $this->withToken($apiKey->plainTextKey)->postJson(
        '/api/v1/chat/completions',
        [
            'model' => 'gpt-4.1',
            'messages' => [['role' => 'user', 'content' => 'hello']],
        ],
    );

    $response->assertForbidden();
    $response->assertJsonPath('error.type', 'permission_error');
    Http::assertNothingSent();
});

it('allows requests from whitelisted IPs', function () {
    $apiKey = app(GenerateApiKey::class)->handle(
        user: $this->user,
        name: 'IP Restricted',
        createdBy: $this->user->id,
    );

    // The test client IP is 127.0.0.1
    $apiKey->apiKey->update(['allowed_ips' => ['127.0.0.1']]);

    Http::fake([
        'https://openai.mock/v1/chat/completions' => Http::response(
            [
                'id' => 'chatcmpl_2',
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

    $response = $this->withToken($apiKey->plainTextKey)->postJson(
        '/api/v1/chat/completions',
        [
            'model' => 'gpt-4.1',
            'messages' => [['role' => 'user', 'content' => 'hello']],
        ],
    );

    $response->assertOk();
});

it('supports CIDR ranges in IP whitelist', function () {
    $apiKey = app(GenerateApiKey::class)->handle(
        user: $this->user,
        name: 'CIDR Restricted',
        createdBy: $this->user->id,
    );

    // 127.0.0.0/8 covers 127.0.0.1
    $apiKey->apiKey->update(['allowed_ips' => ['127.0.0.0/8']]);

    Http::fake([
        'https://openai.mock/v1/chat/completions' => Http::response(
            [
                'id' => 'chatcmpl_3',
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

    $response = $this->withToken($apiKey->plainTextKey)->postJson(
        '/api/v1/chat/completions',
        [
            'model' => 'gpt-4.1',
            'messages' => [['role' => 'user', 'content' => 'hello']],
        ],
    );

    $response->assertOk();
});

it('blocks requests when IP does not match CIDR range', function () {
    $apiKey = app(GenerateApiKey::class)->handle(
        user: $this->user,
        name: 'CIDR Restricted',
        createdBy: $this->user->id,
    );

    // 192.168.0.0/16 does not cover 127.0.0.1
    $apiKey->apiKey->update(['allowed_ips' => ['192.168.0.0/16']]);

    Http::fake();

    $response = $this->withToken($apiKey->plainTextKey)->postJson(
        '/api/v1/chat/completions',
        [
            'model' => 'gpt-4.1',
            'messages' => [['role' => 'user', 'content' => 'hello']],
        ],
    );

    $response->assertForbidden();
    Http::assertNothingSent();
});

it('supports multiple IPs in the whitelist', function () {
    $apiKey = app(GenerateApiKey::class)->handle(
        user: $this->user,
        name: 'Multi IP',
        createdBy: $this->user->id,
    );

    $apiKey->apiKey->update([
        'allowed_ips' => ['10.0.0.1', '127.0.0.1', '172.16.0.5'],
    ]);

    Http::fake([
        'https://openai.mock/v1/chat/completions' => Http::response(
            [
                'id' => 'chatcmpl_4',
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

    $response = $this->withToken($apiKey->plainTextKey)->postJson(
        '/api/v1/chat/completions',
        [
            'model' => 'gpt-4.1',
            'messages' => [['role' => 'user', 'content' => 'hello']],
        ],
    );

    $response->assertOk();
});
