<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Chat;

use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;

it('forwards chat completion requests to the upstream regardless of provider adapter type', function () {
    [$plainTextKey, $modelExternalId] = provisionGatewayTarget('anthropic_compatible', 'claude-3-7-sonnet');

    Http::fake([
        'https://anthropic.mock/v1/chat/completions' => Http::response([
            'id' => 'cmpl_1',
            'choices' => [
                ['message' => ['role' => 'assistant', 'content' => 'hello from upstream']],
            ],
        ], 200),
    ]);

    $response = $this->withToken($plainTextKey)->postJson('/api/v1/chat/completions', [
        'model' => $modelExternalId,
        'messages' => [
            ['role' => 'user', 'content' => 'hello'],
        ],
    ]);

    $response->assertOk();
    $response->assertJsonPath('choices.0.message.content', 'hello from upstream');
});

it('forwards openai requests and responses as-is when the provider is openai compatible', function () {
    [$plainTextKey, $modelExternalId] = provisionGatewayTarget('openai_compatible', 'gpt-4.1');

    Http::fake([
        'https://openai.mock/v1/chat/completions' => function (HttpRequest $request) {
            $body = json_decode($request->body(), true);
            expect($body['model'])->toBe('gpt-4.1');
            expect($body['messages'][0]['role'])->toBe('user');
            expect($body['temperature'] ?? null)->toBe(0.7);

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
