<?php

use App\Actions\ApiKeys\GenerateApiKey;
use App\Models\User;
use Livewire\Livewire;

test('api keys page requires authentication', function () {
    $response = $this->get(route('api-keys.index'));

    $response->assertRedirect(route('login'));
});

test('api keys page can be rendered by authenticated users', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('api-keys.index'));

    $response->assertOk();
});

test('users can create api keys', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::api-keys')
        ->set('newKeyName', 'Production Key')
        ->call('createKey')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('api_keys', [
        'user_id' => $user->id,
        'name' => 'Production Key',
        'created_by' => $user->id,
    ]);
});

test('api key name is required', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::api-keys')
        ->set('newKeyName', '')
        ->call('createKey')
        ->assertHasErrors(['newKeyName' => 'required']);
});

test('users can revoke their api keys', function () {
    $user = User::factory()->create();

    $apiKey = app(GenerateApiKey::class)->handle(
        user: $user,
        name: 'Test Key',
        createdBy: $user->id,
    );

    $this->actingAs($user);

    Livewire::test('pages::api-keys')
        ->call('revokeKey', $apiKey->apiKey->id)
        ->assertHasNoErrors();

    expect($apiKey->apiKey->fresh()->revoked_at)->not->toBeNull();
});

test('cannot revoke another users api key', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $apiKey = app(GenerateApiKey::class)->handle(
        user: $otherUser,
        name: 'Other User Key',
    );

    $this->actingAs($user);

    Livewire::test('pages::api-keys')
        ->call('revokeKey', $apiKey->apiKey->id)
        ->assertNotFound();
});

test('api keys list shows user keys', function () {
    $user = User::factory()->create();

    app(GenerateApiKey::class)->handle(user: $user, name: 'Key One', createdBy: $user->id);
    app(GenerateApiKey::class)->handle(user: $user, name: 'Key Two', createdBy: $user->id);

    $this->actingAs($user);

    Livewire::test('pages::api-keys')
        ->assertSee('Key One')
        ->assertSee('Key Two');
});

test('api keys list does not show other users keys', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    app(GenerateApiKey::class)->handle(user: $user, name: 'My Key', createdBy: $user->id);
    app(GenerateApiKey::class)->handle(user: $otherUser, name: 'Other Key', createdBy: $otherUser->id);

    $this->actingAs($user);

    Livewire::test('pages::api-keys')
        ->assertSee('My Key')
        ->assertDontSee('Other Key');
});

test('created api key shows plain text key in component state', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $component = Livewire::test('pages::api-keys')
        ->set('newKeyName', 'New Key')
        ->call('createKey')
        ->assertHasNoErrors();

    expect($component->get('generatedPlainTextKey'))->toStartWith('ahk_');
});
