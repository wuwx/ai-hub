<?php

it('returns health check results', function () {
    $response = $this->getJson('/api/health?fresh');

    $response->assertOk();
    $response->assertJsonStructure([
        'finishedAt',
        'checkResults' => [
            '*' => [
                'name',
                'label',
                'notificationMessage',
                'shortSummary',
                'status',
                'meta',
            ],
        ],
    ]);
});

it('does not require authentication', function () {
    $this->getJson('/api/health?fresh')->assertOk();
});

it('reports the database and cache as healthy', function () {
    $response = $this->getJson('/api/health?fresh');

    $response->assertOk();

    $names = $response->json('checkResults.*.name');

    expect($names)->toContain('Database');
    expect($names)->toContain('Cache');
});
