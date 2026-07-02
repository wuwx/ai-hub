<?php

use App\Models\User;

it('displays the playground page for authenticated users', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get('/playground');

    $response->assertOk();
    $response->assertSee('Playground');
    $response->assertSee('Select a model');
});

it('requires authentication', function () {
    $this->get('/playground')->assertRedirect('/login');
});

it('shows available models for the user', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get('/playground');

    $response->assertOk();
    // The page should contain the model select dropdown
    $response->assertSee('playground-model-select');
});
