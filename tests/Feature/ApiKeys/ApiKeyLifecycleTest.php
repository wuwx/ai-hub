<?php

use App\Actions\ApiKeys\GenerateApiKey;
use App\Actions\ApiKeys\RotateApiKey;
use App\Models\User;
use Illuminate\Support\Carbon;

it('generates a plaintext key and stores only its hash', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $result = app(GenerateApiKey::class)->handle(
        team: $team,
        name: 'Primary Key',
        expiresAt: Carbon::now()->addMonth(),
        createdBy: $user->id,
    );

    expect($result->plainTextKey)->toStartWith('ahk_');
    expect($result->apiKey->team_id)->toBe($team->id);
    expect($result->apiKey->created_by)->toBe($user->id);
    expect($result->apiKey->key_hash)->toBe(hash('sha256', $result->plainTextKey));
    expect($result->apiKey->last_four)->toBe(substr($result->plainTextKey, -4));

    $this->assertDatabaseHas('api_keys', [
        'id' => $result->apiKey->id,
        'name' => 'Primary Key',
        'last_four' => substr($result->plainTextKey, -4),
        'key_hash' => hash('sha256', $result->plainTextKey),
    ]);
});

it('rotates an api key and clears revoked status', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $generated = app(GenerateApiKey::class)->handle(
        team: $team,
        name: 'Rotate Me',
        createdBy: $user->id,
    );

    $apiKey = $generated->apiKey;
    $apiKey->update(['revoked_at' => now()]);

    $rotated = app(RotateApiKey::class)->handle($apiKey);

    expect($rotated->plainTextKey)->toStartWith('ahk_');
    expect($rotated->apiKey->id)->toBe($apiKey->id);
    expect($rotated->apiKey->key_hash)->toBe(hash('sha256', $rotated->plainTextKey));
    expect($rotated->apiKey->last_four)->toBe(substr($rotated->plainTextKey, -4));
    expect($rotated->apiKey->revoked_at)->toBeNull();
    expect($rotated->plainTextKey)->not->toBe($generated->plainTextKey);
});
