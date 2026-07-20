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

it(
    'forwards embeddings requests to the upstream provider and returns the response',
    function () {
        [$plainTextKey, $modelExternalId] = provisionEmbeddingsTarget();

        Http::fake([
            'https://openai.mock/v1/embeddings' => function (
                HttpRequest $request,
            ) {
                expect($request['model'])->toBe('text-embedding-3-small');
                expect($request['input'])->toBe('hello world');

                return Http::response(
                    [
                        'object' => 'list',
                        'data' => [
                            [
                                'object' => 'embedding',
                                'index' => 0,
                                'embedding' => [0.1, 0.2, 0.3],
                            ],
                        ],
                        'model' => 'text-embedding-3-small',
                        'usage' => [
                            'prompt_tokens' => 2,
                            'total_tokens' => 2,
                        ],
                    ],
                    200,
                );
            },
        ]);

        $response = $this->withToken($plainTextKey)->postJson(
            '/api/v1/embeddings',
            [
                'model' => $modelExternalId,
                'input' => 'hello world',
            ],
        );

        $response->assertOk();
        $response->assertHeader('X-Trace-Id');
        $response->assertJsonPath('object', 'list');
        $response->assertJsonPath('data.0.object', 'embedding');
        $response->assertJsonPath('data.0.embedding', [0.1, 0.2, 0.3]);
        $response->assertJsonPath('usage.prompt_tokens', 2);
    },
);

it('accepts an array of inputs for batch embeddings', function () {
    [$plainTextKey, $modelExternalId] = provisionEmbeddingsTarget();

    Http::fake([
        'https://openai.mock/v1/embeddings' => function (HttpRequest $request) {
            expect($request['input'])->toBe(['first', 'second']);

            return Http::response(
                [
                    'object' => 'list',
                    'data' => [
                        [
                            'object' => 'embedding',
                            'index' => 0,
                            'embedding' => [0.1],
                        ],
                        [
                            'object' => 'embedding',
                            'index' => 1,
                            'embedding' => [0.2],
                        ],
                    ],
                    'model' => 'text-embedding-3-small',
                    'usage' => ['prompt_tokens' => 4, 'total_tokens' => 4],
                ],
                200,
            );
        },
    ]);

    $response = $this->withToken($plainTextKey)->postJson(
        '/api/v1/embeddings',
        [
            'model' => $modelExternalId,
            'input' => ['first', 'second'],
        ],
    );

    $response->assertOk();
    $response->assertJsonPath('data.0.embedding', [0.1]);
    $response->assertJsonPath('data.1.embedding', [0.2]);
});

it('rejects embeddings without a model field', function () {
    [$plainTextKey] = provisionEmbeddingsTarget();

    $response = $this->withToken($plainTextKey)->postJson(
        '/api/v1/embeddings',
        [
            'input' => 'hello',
        ],
    );

    $response->assertStatus(422);
    $response->assertJsonPath('error.code', 'missing_model');
});

it('rejects embeddings without an input field', function () {
    [$plainTextKey, $modelExternalId] = provisionEmbeddingsTarget();

    $response = $this->withToken($plainTextKey)->postJson(
        '/api/v1/embeddings',
        [
            'model' => $modelExternalId,
        ],
    );

    $response->assertStatus(422);
    $response->assertJsonPath('error.code', 'missing_input');
});

it('rejects embeddings without a valid API key', function () {
    $this->postJson('/api/v1/embeddings', [
        'model' => 'text-embedding-3-small',
        'input' => 'hello',
    ])->assertUnauthorized();
});

function provisionEmbeddingsTarget(): array
{
    $user = User::factory()->create();

    (new SubscriptionifySeeder)->run();
    app(SyncQuotaFromSubscription::class)->handle(user: $user, planCode: 'free');

    $provider = LlmProvider::create([
        'name' => 'OpenAI Mock',
        'slug' => 'openai-embeddings-'.uniqid(),
        'adapter_type' => 'openai_compatible',
        'base_url' => 'https://openai.mock',
        'auth_mode' => 'bearer',
        'secret_ref' => 'test-secret',
        'options' => [
            'endpoints' => [
                'embeddings' => '/v1/embeddings',
            ],
        ],
        'is_active' => true,
    ]);

    $model = LlmModel::create([
        'llm_provider_id' => $provider->id,
        'name' => 'TEXT-EMBEDDING-3-SMALL',
        'external_model_id' => 'text-embedding-3-small',
        'is_active' => true,
    ]);

    TestCase::entitleProvider($provider);
    TestCase::entitleModel($model);

    $apiKey = app(GenerateApiKey::class)->handle(
        user: $user,
        name: 'Embeddings Access Key',
    );

    return [$apiKey->plainTextToken, $model->external_model_id, $user];
}
