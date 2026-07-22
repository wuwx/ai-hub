<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;

it('forwards messages requests to the upstream provider', function () {
    [$plainTextKey, $modelExternalId] = provisionGatewayTarget('anthropic_compatible', 'claude-3-7-sonnet');

    Http::fake([
        'https://anthropic.mock/v1/messages' => function (HttpRequest $request) {
            $body = json_decode($request->body(), true);
            expect($body['model'])->toBe('claude-3-7-sonnet');

            return Http::response([
                'id' => 'msg_1',
                'type' => 'message',
                'content' => [['type' => 'text', 'text' => 'hello from anthropic']],
            ], 200);
        },
    ]);

    $response = $this->withToken($plainTextKey)->postJson('/api/v1/messages', [
        'model' => $modelExternalId,
        'messages' => [['role' => 'user', 'content' => 'hello']],
        'max_tokens' => 128,
    ]);

    $response->assertOk();
});

it('rejects anthropic requests when the upstream provider is openai compatible', function () {
    [$plainTextKey, $modelExternalId] = provisionGatewayTarget('openai_compatible', 'gpt-4.1');

    Http::fake();

    $response = $this->withToken($plainTextKey)->postJson('/api/v1/messages', [
        'model' => $modelExternalId,
        'messages' => [['role' => 'user', 'content' => 'hello']],
        'max_tokens' => 128,
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('type', 'error');
    $response->assertJsonPath('error.code', 'protocol_mismatch');
    Http::assertNothingSent();
});
