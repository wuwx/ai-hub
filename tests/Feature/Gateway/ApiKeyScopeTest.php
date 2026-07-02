<?php

use App\Actions\ApiKeys\GenerateApiKey;
use App\Actions\Billing\RechargeTeamWallet;
use App\Models\ApiKey;
use App\Models\LlmModel;
use App\Models\LlmProvider;
use App\Models\PlanModelEntitlement;
use App\Models\PlanProviderEntitlement;
use App\Models\TeamQuotaPolicy;
use App\Models\User;
use Illuminate\Support\Facades\Http;

function provisionScopedKeyForTeam(array $allowedModels = []): array
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
        'sell_input_per_1m_usd' => 1.0,
        'sell_output_per_1m_usd' => 2.0,
        'is_active' => true,
    ]);

    $modelB = LlmModel::create([
        'llm_provider_id' => $provider->id,
        'name' => 'GPT-B',
        'external_model_id' => 'gpt-b',
        'sell_input_per_1m_usd' => 1.0,
        'sell_output_per_1m_usd' => 2.0,
        'is_active' => true,
    ]);

    PlanProviderEntitlement::create([
        'plan_code' => 'free',
        'llm_provider_id' => $provider->id,
        'is_enabled' => true,
    ]);

    foreach ([$modelA, $modelB] as $model) {
        PlanModelEntitlement::create([
            'plan_code' => 'free',
            'llm_model_id' => $model->id,
            'is_enabled' => true,
        ]);
    }

    app(RechargeTeamWallet::class)->handle($team, 100_00, 'Seed');

    $apiKeyResult = app(GenerateApiKey::class)->handle(
        team: $team,
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
    [$plainTextKey, $allowedModel] = provisionScopedKeyForTeam(['gpt-a']);

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
    [$plainTextKey] = provisionScopedKeyForTeam(['gpt-a']);

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
    [$plainTextKey, , $modelB] = provisionScopedKeyForTeam([]);

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
