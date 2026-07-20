<?php

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Livewire;

test('users can rotate their api keys', function () {
    $user = User::factory()->create();

    $generated = $user->createToken('Test Key', ['*'], null);

    $oldId = $generated->accessToken->id;

    $this->actingAs($user);

    $component = Livewire::test('pages::api-keys')
        ->call('rotateKey', $oldId);

    // After rotation the old token is gone and a fresh one exists.
    expect(PersonalAccessToken::find($oldId))->toBeNull();
    expect($component->get('rotatedKeyId'))->not->toBe($oldId);
    expect($component->get('rotatedPlainTextKey'))->not->toBeNull();
    expect(PersonalAccessToken::find($component->get('rotatedKeyId'))->name)->toBe('Test Key');
});

test('rotating key shows new key to user', function () {
    $user = User::factory()->create();

    $generated = $user->createToken('Test Key', ['*'], null);

    $this->actingAs($user);

    $component = Livewire::test('pages::api-keys')
        ->call('rotateKey', $generated->accessToken->id);

    expect($component->get('rotatedPlainTextKey'))->not->toBeNull();
    expect($component->get('rotatedPlainTextKey'))->toMatch('/^\d+\|/');
    expect($component->get('rotatedKeyId'))->not->toBe($generated->accessToken->id);
});

test('dismiss rotated key clears state', function () {
    $user = User::factory()->create();

    $generated = $user->createToken('Test Key', ['*'], null);

    $this->actingAs($user);

    $component = Livewire::test('pages::api-keys')
        ->call('rotateKey', $generated->accessToken->id)
        ->call('dismissRotatedKey');

    expect($component->get('rotatedPlainTextKey'))->toBeNull();
    expect($component->get('rotatedKeyId'))->toBeNull();
});
