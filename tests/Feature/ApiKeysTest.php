<?php

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;
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

    $this->assertDatabaseHas('personal_access_tokens', [
        'tokenable_id' => $user->id,
        'tokenable_type' => User::class,
        'name' => 'Production Key',
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

    $generated = $user->createToken('Test Key', ['*'], null);

    $this->actingAs($user);

    Livewire::test('pages::api-keys')
        ->call('revokeKey', $generated->accessToken->id)
        ->assertHasNoErrors();

    expect(PersonalAccessToken::find($generated->accessToken->id))->toBeNull();
});

test('cannot revoke another users api key', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $generated = $otherUser->createToken('Other User Key', ['*'], null);

    $this->actingAs($user);

    Livewire::test('pages::api-keys')
        ->call('revokeKey', $generated->accessToken->id)
        ->assertNotFound();
});

test('api keys list shows user keys', function () {
    $user = User::factory()->create();

    $user->createToken('Key One', ['*'], null);
    $user->createToken('Key Two', ['*'], null);

    $this->actingAs($user);

    Livewire::test('pages::api-keys')
        ->assertSee('Key One')
        ->assertSee('Key Two');
});

test('api keys list does not show other users keys', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $user->createToken('My Key', ['*'], null);
    $otherUser->createToken('Other Key', ['*'], null);

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

    expect($component->get('generatedPlainTextKey'))->toMatch('/^\d+\|/');
});
