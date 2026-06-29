<?php

use App\Models\LlmProvider;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;

it('marks a provider healthy when the upstream responds 2xx', function () {
    Http::fake([
        'https://openai.mock/v1/models' => Http::response(['data' => []], 200),
    ]);

    $provider = LlmProvider::create([
        'name' => 'OpenAI Mock',
        'slug' => 'openai-mock-'.uniqid(),
        'adapter_type' => 'openai_compatible',
        'base_url' => 'https://openai.mock',
        'auth_mode' => 'bearer',
        'secret_ref' => 'literal://sk-test-key',
        'is_active' => true,
    ]);

    $this->artisan('gateway:check-provider-health')
        ->assertSuccessful()
        ->expectsOutputToContain('Health check complete: 1 healthy, 0 unhealthy.');

    $provider->refresh();

    expect($provider->last_health_status)->toBe('healthy');
    expect($provider->last_health_checked_at)->not->toBeNull();
    expect($provider->last_health_error)->toBeNull();
});

it('marks a provider unhealthy when the upstream returns a server error', function () {
    Http::fake([
        'https://broken.mock/v1/models' => Http::response(['error' => 'down'], 503),
    ]);

    $provider = LlmProvider::create([
        'name' => 'Broken Mock',
        'slug' => 'broken-mock-'.uniqid(),
        'adapter_type' => 'openai_compatible',
        'base_url' => 'https://broken.mock',
        'auth_mode' => 'bearer',
        'secret_ref' => 'literal://sk-test-key',
        'is_active' => true,
    ]);

    $this->artisan('gateway:check-provider-health')
        ->assertSuccessful()
        ->expectsOutputToContain('Health check complete: 0 healthy, 1 unhealthy.');

    $provider->refresh();

    expect($provider->last_health_status)->toBe('unhealthy');
    expect($provider->last_health_error)->toContain('HTTP 503');
});

it('marks a provider unhealthy when the connection fails', function () {
    Http::fake([
        'https://unreachable.mock/v1/models' => Http::failedConnection('Connection refused'),
    ]);

    $provider = LlmProvider::create([
        'name' => 'Unreachable Mock',
        'slug' => 'unreachable-mock-'.uniqid(),
        'adapter_type' => 'openai_compatible',
        'base_url' => 'https://unreachable.mock',
        'auth_mode' => 'bearer',
        'secret_ref' => 'literal://sk-test-key',
        'is_active' => true,
    ]);

    $this->artisan('gateway:check-provider-health')
        ->assertSuccessful()
        ->expectsOutputToContain('Health check complete: 0 healthy, 1 unhealthy.');

    $provider->refresh();

    expect($provider->last_health_status)->toBe('unhealthy');
    expect($provider->last_health_error)->toContain('Connection refused');
});

it('skips inactive providers', function () {
    Http::fake();

    LlmProvider::create([
        'name' => 'Inactive Mock',
        'slug' => 'inactive-mock-'.uniqid(),
        'adapter_type' => 'openai_compatible',
        'base_url' => 'https://inactive.mock',
        'auth_mode' => 'bearer',
        'secret_ref' => 'literal://sk-test-key',
        'is_active' => false,
    ]);

    $this->artisan('gateway:check-provider-health')
        ->assertSuccessful()
        ->expectsOutputToContain('No active providers to check.');

    Http::assertNothingSent();
});

it('sends authorization header for bearer auth mode', function () {
    Http::fake([
        'https://secure.mock/v1/models' => function (HttpRequest $request) {
            expect($request->header('Authorization'))->toBe(['Bearer sk-secret-value']);

            return Http::response(['data' => []], 200);
        },
    ]);

    LlmProvider::create([
        'name' => 'Secure Mock',
        'slug' => 'secure-mock-'.uniqid(),
        'adapter_type' => 'openai_compatible',
        'base_url' => 'https://secure.mock',
        'auth_mode' => 'bearer',
        'secret_ref' => 'literal://sk-secret-value',
        'is_active' => true,
    ]);

    $this->artisan('gateway:check-provider-health')->assertSuccessful();
});
