<?php

namespace Tests\Feature\Http\Middleware;

use App\Models\AiProvider;
use App\Models\User;
use Database\Seeders\SubscriptionifySeeder;
use Tests\TestCase;

it('rejects gateway requests without an api key', function () {
    $response = $this->postJson('/api/v1/chat/completions', [
        'model' => 'gpt-4.1',
        'messages' => [['role' => 'user', 'content' => 'hello']],
    ]);

    $response->assertStatus(401);
    $response->assertJsonPath('error.type', 'authentication_error');
});

it('rejects gateway requests with an invalid api key', function () {
    $response = $this->withToken('not-a-real-token')->postJson('/api/v1/chat/completions', [
        'model' => 'gpt-4.1',
        'messages' => [['role' => 'user', 'content' => 'hello']],
    ]);

    $response->assertStatus(401);
    $response->assertJsonPath('error.type', 'authentication_error');
});

it('accepts authenticated requests and reaches gateway model validation', function () {
    $user = User::factory()->create();

    (new SubscriptionifySeeder)->run();
    TestCase::subscribeUserToFreePlan($user);

    $provider = AiProvider::create([
        'name' => 'OpenAI',
        'slug' => 'openai-'.uniqid(),
        'adapter_type' => 'openai_compatible',
        'base_url' => 'https://api.openai.com',
        'auth_mode' => 'bearer',
        'secret_ref' => 'test-secret',
        'options' => [],
        'is_active' => true,
    ]);

    TestCase::entitleProvider($provider);

    $response = $this->withToken(
        $user->createToken('Gateway Access Key', ['*'], null)->plainTextToken
    )->postJson('/api/v1/chat/completions', [
        'model' => 'does-not-exist',
        'messages' => [['role' => 'user', 'content' => 'hello']],
    ]);

    $response->assertStatus(404);
});
