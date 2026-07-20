<?php

use App\Actions\ApiKeys\GenerateApiKey;
use App\Actions\ApiKeys\RotateApiKey;
use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken;

it('generates a plaintext key backed by a sanctum token', function () {
    $user = User::factory()->create();

    $result = app(GenerateApiKey::class)->handle(
        user: $user,
        name: 'Primary Key',
        expiresAt: Carbon::now()->addMonth(),
    );

    expect($result->plainTextToken)->toMatch('/^\d+\|[A-Za-z0-9]{40,}$/');
    expect($result->token->tokenable_id)->toBe($user->id);
    expect($result->token->name)->toBe('Primary Key');

    $this->assertDatabaseHas('personal_access_tokens', [
        'id' => $result->token->id,
        'name' => 'Primary Key',
        'tokenable_id' => $user->id,
        'tokenable_type' => User::class,
    ]);
});

it('rotates an api key into a new token', function () {
    $user = User::factory()->create();

    $generated = app(GenerateApiKey::class)->handle(
        user: $user,
        name: 'Rotate Me',
    );

    $token = $generated->token;

    $rotated = app(RotateApiKey::class)->handle($token);

    expect($rotated->plainTextToken)->toMatch('/^\d+\|/');
    expect($rotated->token->id)->not->toBe($token->id);
    expect($rotated->token->name)->toBe('Rotate Me');
    expect($rotated->plainTextToken)->not->toBe($generated->plainTextToken);

    // The old token is deleted during rotation.
    expect(PersonalAccessToken::find($token->id))->toBeNull();
});
