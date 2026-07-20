<?php

use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken;

it('generates a plaintext key backed by a sanctum token', function () {
    $user = User::factory()->create();

    $result = $user->createToken('Primary Key', ['*'], Carbon::now()->addMonth(),
    );

    expect($result->plainTextToken)->toMatch('/^\d+\|[A-Za-z0-9]{40,}$/');
    expect($result->accessToken->tokenable_id)->toBe($user->id);
    expect($result->accessToken->name)->toBe('Primary Key');

    $this->assertDatabaseHas('personal_access_tokens', [
        'id' => $result->accessToken->id,
        'name' => 'Primary Key',
        'tokenable_id' => $user->id,
        'tokenable_type' => User::class,
    ]);
});

it('rotates an api key into a new token', function () {
    $user = User::factory()->create();

    $generated = $user->createToken('Rotate Me', ['*'], null);

    $token = $generated->accessToken;

    $token->delete();

    $rotated = $user->createToken($token->name, ['*'], $token->expires_at);

    expect($rotated->plainTextToken)->toMatch('/^\d+\|/');
    expect($rotated->accessToken->id)->not->toBe($token->id);
    expect($rotated->accessToken->name)->toBe('Rotate Me');
    expect($rotated->plainTextToken)->not->toBe($generated->plainTextToken);

    // The old token is deleted during rotation.
    expect(PersonalAccessToken::find($token->id))->toBeNull();
});
