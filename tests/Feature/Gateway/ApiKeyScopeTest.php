<?php

use App\Actions\ApiKeys\GenerateApiKey;
use App\Actions\Billing\SyncQuotaFromSubscription;
use App\Models\ApiKey;
use App\Models\LlmModel;
use App\Models\LlmProvider;
use App\Models\User;
use Database\Seeders\SubscriptionifySeeder;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

function provisionScopedKey(array $allowedModels = []): array
{
    $user = User::factory()->create();

    (new SubscriptionifySeeder)->run();
    app(SyncQuotaFromSubscription::class)->handle(user: $user, planCode: 'free');

    $provider = LlmProvider::create([
        'name' => 'Scoped Provider',
        'slug' => 'scoped-provider-'.uniqid(),
        'adapter_type' => 'openai_compatible',
        'base_url' => 'https://openai.mock',
        'auth_mode' => 'bearer',
        'secret_ref' => 'test-secret',
        'options' => ['endpoints' => ['chat' => '/v1/chat/completions']],
        'is_active' => true,
    ]);

    $modelA = LlmModel::create([
        'llm_provider_id' => $provider->id,
        'name' => 'GPT-A',
        'external_model_id' => 'gpt-a',
        'is_active' => true,
    ]);

    $modelB = LlmModel::create([
        'llm_provider_id' => $provider->id,
        'name' => 'GPT-B',
        'external_model_id' => 'gpt-b',
        'is_active' => true,
    ]);

    TestCase::entitleProvider($provider);

    foreach ([$modelA, $modelB] as $model) {
        TestCase::entitleModel($model);
    }

    $apiKeyResult = app(GenerateApiKey::class)->handle(
        user: $user,
        name: 'Scoped Key',
        createdBy: $user->id,
    );

    if (! empty($allowedModels)) {
        /** @var ApiKey $key */
        $key = $apiKeyResult->apiKey;
        $key->update(['allowed_models' => $allowedModels]);
    }

    return [$apiKeyResult->plainTextKey, 'gpt-a', 'gpt-b'];
}

it('allows requests to models in the key allow-list', function () {
    [$plainTextKey, $allowedModel] = provisionScopedKey(['gpt-a']);

    Http::fake([
        'https://openai.mock/v1/chat/completions' => Http::response([
            'id' => 'ok',
            'object' => 'chat.completion',
            'choices' => [['index' => 0, 'finish_reason' => 'stop', 'message' => ['role' => 'assistant', 'content' => 'ok']]],
            'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 3, 'total_tokens' => 8],
        ], 200),
    ]);

    $response = $this->withToken($plainTextKey)->postJson('/api/v1/chat/completions', [
        'model' => $allowedModel,
        'messages' => [['role' => 'user', 'content' => 'hi']],
    ]);

    $response->assertOk();
});

it('rejects requests to models outside the key allow-list', function () {
    [$plainTextKey] = provisionScopedKey(['gpt-a']);

    Http::fake();

    $response = $this->withToken($plainTextKey)->postJson('/api/v1/chat/completions', [
        'model' => 'gpt-b',
        'messages' => [['role' => 'user', 'content' => 'hi']],
    ]);

    $response->assertStatus(403);
    $response->assertJsonPath('error.code', 'model_not_allowed');
    Http::assertNothingSent();
});

it('allows all entitled models when the allow-list is empty', function () {
    [$plainTextKey, , $modelB] = provisionScopedKey([]);

    Http::fake([
        'https://openai.mock/v1/chat/completions' => Http::response([
            'id' => 'ok',
            'object' => 'chat.completion',
            'choices' => [['index' => 0, 'finish_reason' => 'stop', 'message' => ['role' => 'assistant', 'content' => 'ok']]],
            'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 3, 'total_tokens' => 8],
        ], 200),
    ]);

    $response = $this->withToken($plainTextKey)->postJson('/api/v1/chat/completions', [
        'model' => $modelB,
        'messages' => [['role' => 'user', 'content' => 'hi']],
    ]);

    $response->assertOk();
});
