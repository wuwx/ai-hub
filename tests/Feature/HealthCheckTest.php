<?php

it('returns health check results', function () {
    $response = $this->get('/health?fresh');

    $response->assertOk();
});

it('does not require authentication', function () {
    $this->get('/health?fresh')->assertOk();
});

it('reports the database and cache as healthy', function () {
    $response = $this->get('/health?fresh');

    $response->assertOk();
    $response->assertSee('Database');
    $response->assertSee('Cache');
});
