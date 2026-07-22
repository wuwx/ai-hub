<?php

use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\User;
use Database\Seeders\SubscriptionifySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function provisionGatewayTarget(string $adapterType, string $externalModelId): array
{
    $user = User::factory()->create();

    (new SubscriptionifySeeder)->run();
    TestCase::subscribeUserToFreePlan($user);

    $provider = AiProvider::create([
        'name' => 'Provider '.$adapterType,
        'slug' => 'provider-'.$adapterType.'-'.uniqid(),
        'adapter_type' => $adapterType,
        'base_url' => $adapterType === 'anthropic_compatible' ? 'https://anthropic.mock' : 'https://openai.mock',
        'auth_mode' => 'bearer',
        'secret_ref' => 'test-secret',
        'options' => [
            'endpoints' => [
                'chat' => '/v1/chat/completions',
                'responses' => '/v1/responses',
                'messages' => '/v1/messages',
            ],
        ],
        'is_active' => true,
    ]);

    $model = AiModel::create([
        'ai_provider_id' => $provider->id,
        'name' => strtoupper($externalModelId),
        'external_model_id' => $externalModelId,
        'is_active' => true,
    ]);

    TestCase::entitleProvider($provider);
    TestCase::entitleModel($model);

    $apiKey = $user->createToken('Gateway Access Key', ['*'], null);

    return [$apiKey->plainTextToken, $model->external_model_id];
}

function provisionEmbeddingsTarget(string $externalModelId): array
{
    $user = User::factory()->create();

    (new SubscriptionifySeeder)->run();
    TestCase::subscribeUserToFreePlan($user);

    $provider = AiProvider::create([
        'name' => 'Mock Provider',
        'slug' => 'mock-provider-'.uniqid(),
        'adapter_type' => 'openai_compatible',
        'base_url' => 'https://mockprovider.local',
        'auth_mode' => 'bearer',
        'secret_ref' => 'test-secret',
        'options' => [
            'endpoints' => ['embeddings' => '/v1/embeddings'],
        ],
        'is_active' => true,
    ]);

    $model = AiModel::create([
        'ai_provider_id' => $provider->id,
        'name' => strtoupper($externalModelId),
        'external_model_id' => $externalModelId,
        'is_active' => true,
    ]);

    TestCase::entitleProvider($provider);
    TestCase::entitleModel($model);

    $apiKey = $user->createToken('Gateway Access Key', ['*'], null);

    return [$apiKey->plainTextToken, $model->external_model_id];
}
