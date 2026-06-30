<?php

use App\Models\User;

it('displays the playground page for authenticated users', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $response = $this->actingAs($user)
        ->get("/{$team->slug}/playground");

    $response->assertOk();
    $response->assertSee('Playground');
    $response->assertSee('Select a model');
});

it('requires authentication', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $this->get("/{$team->slug}/playground")->assertRedirect('/login');
});

it('requires team membership', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;

    $otherUser = User::factory()->create();

    $this->actingAs($otherUser)
        ->get("/{$team->slug}/playground")
        ->assertForbidden();
});

it('shows available models for the team', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $response = $this->actingAs($user)
        ->get("/{$team->slug}/playground");

    $response->assertOk();
    // The page should contain the model select dropdown
    $response->assertSee('playground-model-select');
});
