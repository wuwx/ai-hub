<?php

use App\Http\Middleware\ResolveGatewayProvider;
use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\User;
use Database\Seeders\SubscriptionifySeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Tests\TestCase;

it('resolves the gateway provider and stores it on the request', function () {
    [$request, $provider] = provisionRequest('gpt-4.1');

    $ran = false;
    $response = (new ResolveGatewayProvider)->handle($request, function (Request $req) use (&$ran, $provider) {
        $ran = true;

        $resolved = $req->attributes->get('gateway.provider');
        expect($resolved)->toBeInstanceOf(AiProvider::class);
        expect($resolved->is($provider))->toBeTrue();

        return response()->noContent();
    });

    expect($ran)->toBeTrue();
    expect($response->getStatusCode())->toBe(204);
});

it('injects the provider Authorization header onto the incoming request', function () {
    [$request] = provisionRequest('gpt-4.1');

    (new ResolveGatewayProvider)->handle($request, fn () => response()->noContent());

    expect($request->headers->get('Authorization'))->toBe('Bearer upstream-secret');
});

it('injects additional provider headers from options.headers', function () {
    [$request] = provisionRequest('gpt-4.1');

    (new ResolveGatewayProvider)->handle($request, fn () => response()->noContent());

    expect($request->headers->get('OpenAI-Organization'))->toBe('org-test');
});

it('throws when the requested model does not exist', function () {
    [$request] = provisionRequest('does-not-exist');

    (new ResolveGatewayProvider)->handle($request, fn () => response()->noContent());
})->throws(ModelNotFoundException::class);

it('throws when the provider is inactive', function () {
    [$request, $provider] = provisionRequest('gpt-4.1');
    $provider->update(['is_active' => false]);

    (new ResolveGatewayProvider)->handle($request, fn () => response()->noContent());
})->throws(ModelNotFoundException::class);

function provisionRequest(string $modelExternalId): array
{
    $user = User::factory()->create();

    (new SubscriptionifySeeder)->run();
    TestCase::subscribeUserToFreePlan($user);

    $provider = AiProvider::create([
        'name' => 'OpenAI',
        'slug' => 'openai-'.uniqid(),
        'adapter_type' => 'openai_compatible',
        'base_url' => 'https://api.openai.com',
        'auth_mode' => 'bearer',
        'secret_ref' => 'Bearer upstream-secret',
        'options' => [
            'headers' => [
                'OpenAI-Organization' => 'org-test',
            ],
        ],
        'is_active' => true,
    ]);

    $model = AiModel::create([
        'ai_provider_id' => $provider->id,
        'name' => 'GPT 4.1',
        'external_model_id' => 'gpt-4.1',
        'is_active' => true,
    ]);

    TestCase::entitleProvider($provider);
    TestCase::entitleModel($model);

    $request = Request::create('/api/v1/chat/completions', 'POST', content: json_encode([
        'model' => $modelExternalId,
    ]));
    $request->headers->set('Content-Type', 'application/json');

    return [$request, $provider];
}
