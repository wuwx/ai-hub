<?php

use App\Actions\ApiKeys\GenerateApiKey;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

test('api keys page requires authentication', function () {
    $team = Team::factory()->create();

    $response = $this->get(route('api-keys.index', ['current_team' => $team->slug]));

    $response->assertRedirect(route('login'));
});

test('api keys page can be rendered by owners', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);
    $user->refresh();

    $response = $this
        ->actingAs($user)
        ->get(route('api-keys.index', ['current_team' => $team->slug]));

    $response->assertOk();
});

test('api keys page can be rendered by admins', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $admin->switchTeam($team);
    $admin->refresh();

    $response = $this
        ->actingAs($admin)
        ->get(route('api-keys.index', ['current_team' => $team->slug]));

    $response->assertOk();
});

test('owners can create api keys', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);
    $user->refresh();

    $this->actingAs($user);

    Livewire::test('pages::api-keys')
        ->set('newKeyName', 'Production Key')
        ->call('createKey')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('api_keys', [
        'team_id' => $team->id,
        'name' => 'Production Key',
        'created_by' => $user->id,
    ]);
});

test('admins can create api keys', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $admin->switchTeam($team);
    $admin->refresh();

    $this->actingAs($admin);

    Livewire::test('pages::api-keys')
        ->set('newKeyName', 'Admin Key')
        ->call('createKey')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('api_keys', [
        'team_id' => $team->id,
        'name' => 'Admin Key',
        'created_by' => $admin->id,
    ]);
});

test('members cannot create api keys', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($team);
    $member->refresh();

    $this->actingAs($member);

    Livewire::test('pages::api-keys')
        ->set('newKeyName', 'Member Key')
        ->call('createKey')
        ->assertForbidden();
});

test('api key name is required', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);
    $user->refresh();

    $this->actingAs($user);

    Livewire::test('pages::api-keys')
        ->set('newKeyName', '')
        ->call('createKey')
        ->assertHasErrors(['newKeyName' => 'required']);
});

test('owners can revoke api keys', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);
    $user->refresh();

    $apiKey = app(GenerateApiKey::class)->handle(
        team: $team,
        name: 'Test Key',
        createdBy: $user->id,
    );

    $this->actingAs($user);

    Livewire::test('pages::api-keys')
        ->call('revokeKey', $apiKey->apiKey->id)
        ->assertHasNoErrors();

    expect($apiKey->apiKey->fresh()->revoked_at)->not->toBeNull();
});

test('members cannot revoke api keys', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($team);
    $member->refresh();

    $apiKey = app(GenerateApiKey::class)->handle(
        team: $team,
        name: 'Test Key',
        createdBy: $owner->id,
    );

    $this->actingAs($member);

    Livewire::test('pages::api-keys')
        ->call('revokeKey', $apiKey->apiKey->id)
        ->assertForbidden();
});

test('cannot revoke another teams api key', function () {
    $user = User::factory()->create();
    $teamA = Team::factory()->create();
    $teamB = Team::factory()->create();

    $teamA->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($teamA);
    $user->refresh();

    $apiKey = app(GenerateApiKey::class)->handle(
        team: $teamB,
        name: 'Other Team Key',
    );

    $this->actingAs($user);

    Livewire::test('pages::api-keys')
        ->call('revokeKey', $apiKey->apiKey->id)
        ->assertNotFound();
});

test('api keys list shows team keys', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);
    $user->refresh();

    app(GenerateApiKey::class)->handle(team: $team, name: 'Key One', createdBy: $user->id);
    app(GenerateApiKey::class)->handle(team: $team, name: 'Key Two', createdBy: $user->id);

    $this->actingAs($user);

    Livewire::test('pages::api-keys')
        ->assertSee('Key One')
        ->assertSee('Key Two');
});

test('api keys list does not show other teams keys', function () {
    $user = User::factory()->create();
    $teamA = Team::factory()->create();
    $teamB = Team::factory()->create();

    $teamA->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($teamA);
    $user->refresh();

    app(GenerateApiKey::class)->handle(team: $teamA, name: 'Team A Key', createdBy: $user->id);
    app(GenerateApiKey::class)->handle(team: $teamB, name: 'Team B Key');

    $this->actingAs($user);

    Livewire::test('pages::api-keys')
        ->assertSee('Team A Key')
        ->assertDontSee('Team B Key');
});

test('created api key shows plain text key in component state', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);
    $user->refresh();

    $this->actingAs($user);

    $component = Livewire::test('pages::api-keys')
        ->set('newKeyName', 'New Key')
        ->call('createKey')
        ->assertHasNoErrors();

    expect($component->get('generatedPlainTextKey'))->toStartWith('ahk_');
});
