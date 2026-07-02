<?php

use App\Actions\ApiKeys\GenerateApiKey;
use App\Models\User;
use Livewire\Livewire;

test('users can rotate their api keys', function () {
    $user = User::factory()->create();

    $generated = app(GenerateApiKey::class)->handle(
        user: $user,
        name: 'Test Key',
        expiresAt: null,
        createdBy: $user->id,
    );

    $oldLastFour = $generated->apiKey->last_four;

    $this->actingAs($user);

    $result = Livewire::test('pages::api-keys')
        ->call('rotateKey', $generated->apiKey->id);

    // After rotation, the last four should change
    $generated->apiKey->refresh();
    expect($generated->apiKey->last_four)->not->toBe($oldLastFour);
    expect($generated->apiKey->revoked_at)->toBeNull();
});

test('rotating key shows new key to user', function () {
    $user = User::factory()->create();

    $generated = app(GenerateApiKey::class)->handle(
        user: $user,
        name: 'Test Key',
        expiresAt: null,
        createdBy: $user->id,
    );

    $this->actingAs($user);

    $component = Livewire::test('pages::api-keys')
        ->call('rotateKey', $generated->apiKey->id);

    // Check the component state has the rotated key
    expect($component->get('rotatedPlainTextKey'))->not->toBeNull();
    expect($component->get('rotatedKeyId'))->toBe($generated->apiKey->id);
    expect($component->get('rotatedPlainTextKey'))->toStartWith('ahk_');
});

test('dismiss rotated key clears state', function () {
    $user = User::factory()->create();

    $generated = app(GenerateApiKey::class)->handle(
        user: $user,
        name: 'Test Key',
        expiresAt: null,
        createdBy: $user->id,
    );

    $this->actingAs($user);

    $component = Livewire::test('pages::api-keys')
        ->call('rotateKey', $generated->apiKey->id)
        ->call('dismissRotatedKey');

    expect($component->get('rotatedPlainTextKey'))->toBeNull();
    expect($component->get('rotatedKeyId'))->toBeNull();
});
