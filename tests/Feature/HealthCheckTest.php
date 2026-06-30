<?php

it('returns ok status when all services are healthy', function () {
    $response = $this->getJson('/api/health');

    $response->assertOk();
    $response->assertJsonPath('status', 'ok');
    $response->assertJsonStructure([
        'status',
        'timestamp',
        'checks' => [
            'database' => ['status', 'latency_ms'],
            'cache' => ['status', 'latency_ms'],
        ],
    ]);
});

it('does not require authentication', function () {
    $this->getJson('/api/health')->assertOk();
});

it('includes latency for database and cache checks', function () {
    $response = $this->getJson('/api/health');

    $response->assertOk();

    $databaseLatency = $response->json('checks.database.latency_ms');
    $cacheLatency = $response->json('checks.cache.latency_ms');

    expect($databaseLatency)->toBeInt()->toBeGreaterThanOrEqual(0);
    expect($cacheLatency)->toBeInt()->toBeGreaterThanOrEqual(0);
});
