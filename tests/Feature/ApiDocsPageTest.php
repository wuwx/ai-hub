<?php

it('displays the API docs page', function () {
    $response = $this->get('/docs');

    $response->assertOk();
    $response->assertSee('API Reference');
});

it('lists all API endpoints in the documentation', function () {
    $response = $this->get('/docs');

    $response->assertOk();
    $response->assertSee('/v1/chat/completions');
    $response->assertSee('/v1/embeddings');
    $response->assertSee('/v1/messages');
    $response->assertSee('/v1/responses');
    $response->assertSee('/v1/models');
});

it('documents error codes', function () {
    $response = $this->get('/docs');

    $response->assertOk();
    $response->assertSee('insufficient_balance');
    $response->assertSee('quota_exceeded');
    $response->assertSee('provider_circuit_open');
});

it('documents authentication methods', function () {
    $response = $this->get('/docs');

    $response->assertOk();
    $response->assertSee('Authorization: Bearer');
    $response->assertSee('x-api-key');
});

it('does not require authentication', function () {
    $this->get('/docs')->assertOk();
});
