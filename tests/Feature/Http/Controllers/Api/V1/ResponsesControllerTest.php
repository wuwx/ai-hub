<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;

it('forwards responses requests to the upstream provider', function () {
    [$plainTextKey, $modelExternalId] = provisionGatewayTarget('openai_compatible', 'gpt-4.1');

    Http::fake([
        'https://openai.mock/v1/responses' => function (HttpRequest $request) {
            $body = json_decode($request->body(), true);
            expect($body['model'])->toBe('gpt-4.1');

            return Http::response([
                'id' => 'resp_1',
                'object' => 'response',
                'output' => [
                    ['content' => [['type' => 'output_text', 'text' => 'hello from responses']]],
                ],
            ], 200);
        },
    ]);

    $response = $this->withToken($plainTextKey)->postJson('/api/v1/responses', [
        'model' => $modelExternalId,
        'input' => 'hello',
    ]);

    $response->assertOk();
});

it('rejects responses requests when the upstream provider is not openai compatible', function () {
    [$plainTextKey, $modelExternalId] = provisionGatewayTarget('anthropic_compatible', 'claude-3-7-sonnet');

    Http::fake();

    $response = $this->withToken($plainTextKey)->postJson('/api/v1/responses', [
        'model' => $modelExternalId,
        'input' => 'hello',
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('error.code', 'protocol_mismatch');
    Http::assertNothingSent();
});
