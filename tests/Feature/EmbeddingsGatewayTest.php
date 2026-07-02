<?php

use App\Actions\ApiKeys\GenerateApiKey;
use App\Actions\Billing\RechargeTeamWallet;
use App\Models\LlmModel;
use App\Models\LlmProvider;
use App\Models\PlanModelEntitlement;
use App\Models\PlanProviderEntitlement;
use App\Models\TeamQuotaPolicy;
use App\Models\User;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;

it('forwards embeddings requests to the upstream provider and returns the response', function () {
    [$plainTextKey, $modelExternalId] = provisionEmbeddingsTargetForTeam();

    Http::fake([
        'https://openai.mock/v1/embeddings' => function (HttpRequest $request) {
            expect($request['model'])->toBe('text-embedding-3-small');
            expect($request['input'])->toBe('hello world');

            return Http::response([
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
            ], 200);
        },
    ]);

    $response = $this->withToken($plainTextKey)->postJson('/api/v1/embeddings', [
        'model' => $modelExternalId,
        'input' => 'hello world',
    ]);

    $response->assertOk();
    $response->assertHeader('X-Trace-Id');
    $response->assertJsonPath('object', 'list');
    $response->assertJsonPath('data.0.object', 'embedding');
    $response->assertJsonPath('data.0.embedding', [0.1, 0.2, 0.3]);
    $response->assertJsonPath('usage.prompt_tokens', 2);
});

it('accepts an array of inputs for batch embeddings', function () {
    [$plainTextKey, $modelExternalId] = provisionEmbeddingsTargetForTeam();

    Http::fake([
        'https://openai.mock/v1/embeddings' => function (HttpRequest $request) {
            expect($request['input'])->toBe(['first', 'second']);

            return Http::response([
                'object' => 'list',
                'data' => [
                    ['object' => 'embedding', 'index' => 0, 'embedding' => [0.1]],
                    ['object' => 'embedding', 'index' => 1, 'embedding' => [0.2]],
                ],
                'model' => 'text-embedding-3-small',
                'usage' => ['prompt_tokens' => 4, 'total_tokens' => 4],
            ], 200);
        },
    ]);

    $response = $this->withToken($plainTextKey)->postJson('/api/v1/embeddings', [
        'model' => $modelExternalId,
        'input' => ['first', 'second'],
    ]);

    $response->assertOk();
    $response->assertJsonPath('data.0.embedding', [0.1]);
    $response->assertJsonPath('data.1.embedding', [0.2]);
});

it('rejects embeddings without a model field', function () {
    [$plainTextKey] = provisionEmbeddingsTargetForTeam();

    $response = $this->withToken($plainTextKey)->postJson('/api/v1/embeddings', [
        'input' => 'hello',
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('error.code', 'missing_model');
});

it('rejects embeddings without an input field', function () {
    [$plainTextKey, $modelExternalId] = provisionEmbeddingsTargetForTeam();

    $response = $this->withToken($plainTextKey)->postJson('/api/v1/embeddings', [
        'model' => $modelExternalId,
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('error.code', 'missing_input');
});

it('rejects embeddings without a valid API key', function () {
    $this->postJson('/api/v1/embeddings', [
        'model' => 'text-embedding-3-small',
        'input' => 'hello',
    ])->assertUnauthorized();
});

it('records usage and debits the wallet for successful embeddings', function () {
    [$plainTextKey, $modelExternalId, $team] = provisionEmbeddingsTargetForTeam();

    $balanceBefore = $team->wallet()->first()->balance_cents;

    Http::fake([
        'https://openai.mock/v1/embeddings' => Http::response([
            'object' => 'list',
            'data' => [['object' => 'embedding', 'index' => 0, 'embedding' => [0.1]]],
            'model' => 'text-embedding-3-small',
            'usage' => ['prompt_tokens' => 10, 'total_tokens' => 10],
        ], 200),
    ]);

    $this->withToken($plainTextKey)->postJson('/api/v1/embeddings', [
        'model' => $modelExternalId,
        'input' => 'chargeable input',
    ])->assertOk();

    $team->refresh();
    $balanceAfter = $team->wallet()->first()->balance_cents;

    expect($balanceAfter)->toBeLessThan($balanceBefore);
});

function provisionEmbeddingsTargetForTeam(): array
{
    $user = User::factory()->create();
    $team = $user->currentTeam;

    TeamQuotaPolicy::create([
        'team_id' => $team->id,
        'plan_code' => 'free',
        'daily_token_limit' => 100000,
        'monthly_token_limit' => 1000000,
        'effective_from' => now()->subMinute(),
        'is_active' => true,
    ]);

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
        'sell_input_per_1m_usd' => 1000.0,
        'cost_input_per_1m_usd' => 500.0,
        'is_active' => true,
    ]);

    PlanProviderEntitlement::create([
        'plan_code' => 'free',
        'llm_provider_id' => $provider->id,
        'is_enabled' => true,
    ]);

    PlanModelEntitlement::create([
        'plan_code' => 'free',
        'llm_model_id' => $model->id,
        'is_enabled' => true,
    ]);

    app(RechargeTeamWallet::class)->handle(
        team: $team,
        amountCents: 100_00,
        description: 'Test seed balance',
    );

    $apiKey = app(GenerateApiKey::class)->handle(
        team: $team,
        name: 'Embeddings Access Key',
        createdBy: $user->id,
    );

    return [$apiKey->plainTextKey, $model->external_model_id, $team];
}
