<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use Illuminate\Support\Facades\Http;

it('forwards embeddings requests to the upstream provider and returns the response', function () {
    [$plainTextKey, $modelExternalId] = provisionEmbeddingsTarget('text-embedding-3-small');

    Http::fake([
        'https://mockprovider.local/v1/embeddings' => Http::response([
            'object' => 'list',
            'data' => [
                ['object' => 'embedding', 'index' => 0, 'embedding' => [0.1, 0.2, 0.3]],
            ],
            'model' => 'text-embedding-3-small',
            'usage' => ['prompt_tokens' => 3, 'total_tokens' => 3],
        ], 200),
    ]);

    $response = $this->withToken($plainTextKey)->postJson('/api/v1/embeddings', [
        'model' => $modelExternalId,
        'input' => 'The quick brown fox',
    ]);

    $response->assertOk();
    $response->assertJsonPath('data.0.embedding', [0.1, 0.2, 0.3]);
    $response->assertJsonPath('model', 'text-embedding-3-small');
});

it('accepts an array of inputs for batch embeddings', function () {
    [$plainTextKey, $modelExternalId] = provisionEmbeddingsTarget('text-embedding-3-small');

    Http::fake([
        'https://mockprovider.local/v1/embeddings' => Http::response([
            'object' => 'list',
            'data' => [
                ['object' => 'embedding', 'index' => 0, 'embedding' => [0.1]],
                ['object' => 'embedding', 'index' => 1, 'embedding' => [0.2]],
            ],
            'model' => 'text-embedding-3-small',
        ], 200),
    ]);

    $response = $this->withToken($plainTextKey)->postJson('/api/v1/embeddings', [
        'model' => $modelExternalId,
        'input' => ['first', 'second'],
    ]);

    $response->assertOk();
    $response->assertJsonCount(2, 'data');
});

it('rejects embeddings without a model field', function () {
    [$plainTextKey] = provisionEmbeddingsTarget('text-embedding-3-small');

    Http::fake();

    $response = $this->withToken($plainTextKey)->postJson('/api/v1/embeddings', [
        'input' => 'The quick brown fox',
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('error.code', 'missing_model');
    Http::assertNothingSent();
});

it('rejects embeddings without an input field', function () {
    [$plainTextKey, $modelExternalId] = provisionEmbeddingsTarget('text-embedding-3-small');

    Http::fake();

    $response = $this->withToken($plainTextKey)->postJson('/api/v1/embeddings', [
        'model' => $modelExternalId,
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('error.code', 'missing_input');
    Http::assertNothingSent();
});

it('rejects embeddings without a valid API key', function () {
    [$plainTextKey, $modelExternalId] = provisionEmbeddingsTarget('text-embedding-3-small');
    unset($plainTextKey);

    Http::fake();

    $response = $this->postJson('/api/v1/embeddings', [
        'model' => $modelExternalId,
        'input' => 'The quick brown fox',
    ]);

    $response->assertStatus(401);
    $response->assertJsonPath('error.type', 'authentication_error');
    Http::assertNothingSent();
});
