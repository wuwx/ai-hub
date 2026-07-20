<?php

use App\Models\User;

it('rejects gateway requests without api key', function () {
    $response = $this->postJson('/api/v1/chat/completions', [
        'model' => 'gpt-4.1',
        'messages' => [['role' => 'user', 'content' => 'hello']],
    ]);

    $response->assertStatus(401);
    $response->assertJsonPath('error.type', 'authentication_error');
});

it('rejects gateway requests with invalid api key', function () {
    $response = $this->withToken('ahk_invalid_key')->postJson('/api/v1/chat/completions', [
        'model' => 'gpt-4.1',
        'messages' => [['role' => 'user', 'content' => 'hello']],
    ]);

    $response->assertStatus(401);
    $response->assertJsonPath('error.type', 'authentication_error');
});

it('accepts authenticated requests and reaches gateway model validation', function () {
    $user = User::factory()->create();

    $generated = $user->createToken('Gateway Key', ['*'], null);

    $response = $this->withToken($generated->plainTextToken)->postJson('/api/v1/chat/completions', [
        'model' => 'unknown-model',
        'messages' => [['role' => 'user', 'content' => 'hello']],
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('error.code', 'model_unavailable');
});
