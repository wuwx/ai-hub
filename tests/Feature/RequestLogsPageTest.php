<?php

use App\Models\LlmModel;
use App\Models\LlmProvider;
use App\Models\RequestLog;
use App\Models\User;
use Livewire\Livewire;

test('request logs page requires authentication', function () {

    $response = $this->get(route('request-logs.index'));

    $response->assertRedirect(route('login'));
});

test('authenticated users can view request logs page', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('request-logs.index'));

    $response->assertOk();
});

test('request logs page shows empty state when no logs', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::request-logs')
        ->assertSee('No request logs yet');
});

test('request logs page displays log entries', function () {
    $user = User::factory()->create();

    $provider = LlmProvider::create([
        'name' => 'OpenAI',
        'slug' => 'openai',
        'base_url' => 'https://api.openai.com/v1',
        'is_active' => true,
    ]);

    $model = LlmModel::create([
        'name' => 'gpt-4o',
        'llm_provider_id' => $provider->id,
        'external_model_id' => 'gpt-4o',
        'is_active' => true,
    ]);

    RequestLog::create([
        'user_id' => $user->id,
        'llm_provider_id' => $provider->id,
        'llm_model_id' => $model->id,
        'http_method' => 'POST',
        'endpoint' => '/v1/chat/completions',
        'protocol' => 'openai',
        'status_code' => 200,
        'token_input' => 100,
        'token_output' => 200,
        'token_total' => 300,
        'latency_ms' => 1200,
        'is_streaming' => false,
        'requested_at' => now(),
    ]);

    $this->actingAs($user);

    Livewire::test('pages::request-logs')
        ->assertSee('gpt-4o')
        ->assertSee('200')
        ->assertSee('1,200ms');
});

test('request logs page filters by status code', function () {
    $user = User::factory()->create();

    $provider = LlmProvider::create([
        'name' => 'OpenAI',
        'slug' => 'openai',
        'base_url' => 'https://api.openai.com/v1',
        'is_active' => true,
    ]);

    RequestLog::create([
        'user_id' => $user->id,
        'llm_provider_id' => $provider->id,
        'http_method' => 'POST',
        'endpoint' => '/v1/chat/completions',
        'protocol' => 'openai',
        'status_code' => 200,
        'token_input' => 100,
        'token_output' => 200,
        'token_total' => 300,
        'latency_ms' => 500,
        'is_streaming' => false,
        'requested_at' => now(),
    ]);

    RequestLog::create([
        'user_id' => $user->id,
        'llm_provider_id' => $provider->id,
        'http_method' => 'POST',
        'endpoint' => '/v1/chat/completions',
        'protocol' => 'openai',
        'status_code' => 500,
        'token_input' => 50,
        'token_output' => 0,
        'token_total' => 50,
        'latency_ms' => 200,
        'is_streaming' => false,
        'error_message' => 'Internal server error',
        'requested_at' => now(),
    ]);

    $this->actingAs($user);

    Livewire::test('pages::request-logs')
        ->set('filterStatusCode', '500')
        ->assertSee('Internal server error');
});
