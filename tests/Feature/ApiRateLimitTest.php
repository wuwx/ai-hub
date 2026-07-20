<?php

use App\Models\User;

it('returns 429 when api rate limit is exceeded', function () {
    $user = User::factory()->create();

    $generated = $user->createToken('Rate Limit Key', ['*'], null);

    // Exhaust the 60 requests/minute limit.
    for ($i = 0; $i < 60; $i++) {
        $this->withToken($generated->plainTextToken)
            ->getJson('/api/v1/models');
    }

    $response = $this->withToken($generated->plainTextToken)
        ->getJson('/api/v1/models');

    $response->assertStatus(429);
});

it('allows requests within the rate limit', function () {
    $user = User::factory()->create();

    $generated = $user->createToken('Rate Limit Key', ['*'], null);

    $response = $this->withToken($generated->plainTextToken)
        ->getJson('/api/v1/models');

    $response->assertStatus(200);
});
