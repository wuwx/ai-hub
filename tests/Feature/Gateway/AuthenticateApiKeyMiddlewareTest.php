<?php

use App\Actions\ApiKeys\GenerateApiKey;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

it('rejects gateway requests without api key', function () {
    $response = $this->postJson('/api/v1/chat/completions', [
        'model' => 'gpt-4.1',
        'messages' => [['role' => 'user', 'content' => 'hello']],
    ]);

    $response->assertStatus(401);
    $response->assertJsonPath('error.type', 'authentication_error');
    $response->assertHeader('X-Trace-Id');
});

it('rejects gateway requests with invalid api key', function () {
    $response = $this->withToken('ahk_invalid_key')->postJson('/api/v1/chat/completions', [
        'model' => 'gpt-4.1',
        'messages' => [['role' => 'user', 'content' => 'hello']],
    ]);

    $response->assertStatus(401);
    $response->assertJsonPath('error.type', 'authentication_error');
    $response->assertHeader('X-Trace-Id');
});

it('accepts authenticated requests and reaches gateway model validation', function () {
    $user = User::factory()->create();

    $generated = app(GenerateApiKey::class)->handle(
        user: $user,
        name: 'Gateway Key',
        createdBy: $user->id,
    );

    $response = $this->withToken($generated->plainTextKey)->postJson('/api/v1/chat/completions', [
        'model' => 'unknown-model',
        'messages' => [['role' => 'user', 'content' => 'hello']],
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('error.code', 'model_unavailable');
});

it('accepts x-api-key header for authentication', function () {
    $user = User::factory()->create();

    $generated = app(GenerateApiKey::class)->handle(
        user: $user,
        name: 'Gateway Key Header',
        createdBy: $user->id,
    );

    $response = $this->withHeader('x-api-key', $generated->plainTextKey)->postJson('/api/v1/chat/completions', [
        'model' => 'unknown-model',
        'messages' => [['role' => 'user', 'content' => 'hello']],
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('error.code', 'model_unavailable');
});

it('throttles requests per api key when rate limit is exceeded', function () {
    Cache::flush();
    config()->set('services.llm_gateway.api_key_rate_limit_per_minute', 1);
    config()->set('services.llm_gateway.api_key_rate_limit_decay_seconds', 60);

    $user = User::factory()->create();

    $generated = app(GenerateApiKey::class)->handle(
        user: $user,
        name: 'Gateway Key Limited',
        createdBy: $user->id,
    );

    $firstResponse = $this->withToken($generated->plainTextKey)->postJson('/api/v1/chat/completions', [
        'model' => 'unknown-model',
        'messages' => [['role' => 'user', 'content' => 'hello']],
    ]);

    $secondResponse = $this->withToken($generated->plainTextKey)->postJson('/api/v1/chat/completions', [
        'model' => 'unknown-model',
        'messages' => [['role' => 'user', 'content' => 'hello again']],
    ]);

    $firstResponse->assertStatus(422);
    $secondResponse->assertStatus(429);
    $secondResponse->assertJsonPath('error.type', 'rate_limit_error');
    $secondResponse->assertJsonPath('error.code', 'too_many_requests');
    $secondResponse->assertHeader('X-Trace-Id');
});

it('applies throttling independently for different api keys', function () {
    Cache::flush();
    config()->set('services.llm_gateway.api_key_rate_limit_per_minute', 1);
    config()->set('services.llm_gateway.api_key_rate_limit_decay_seconds', 60);

    $user = User::factory()->create();

    $firstKey = app(GenerateApiKey::class)->handle(
        user: $user,
        name: 'Gateway Key A',
        createdBy: $user->id,
    );

    $secondKey = app(GenerateApiKey::class)->handle(
        user: $user,
        name: 'Gateway Key B',
        createdBy: $user->id,
    );

    $firstKeyFirstCall = $this->withToken($firstKey->plainTextKey)->postJson('/api/v1/chat/completions', [
        'model' => 'unknown-model',
        'messages' => [['role' => 'user', 'content' => 'first key first call']],
    ]);

    $firstKeySecondCall = $this->withToken($firstKey->plainTextKey)->postJson('/api/v1/chat/completions', [
        'model' => 'unknown-model',
        'messages' => [['role' => 'user', 'content' => 'first key second call']],
    ]);

    $secondKeyFirstCall = $this->withToken($secondKey->plainTextKey)->postJson('/api/v1/chat/completions', [
        'model' => 'unknown-model',
        'messages' => [['role' => 'user', 'content' => 'second key first call']],
    ]);

    $firstKeyFirstCall->assertStatus(422);
    $firstKeySecondCall->assertStatus(429);
    $secondKeyFirstCall->assertStatus(422);
});
