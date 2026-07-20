<?php

it('returns prometheus-format metrics', function () {
    $response = $this->get('/api/metrics');

    $response->assertOk();
    $response->assertHeader(
        'Content-Type',
        'text/plain; version=0.0.4; charset=UTF-8',
    );

    $content = $response->getContent();

    expect($content)
        ->toContain('ai_hub_users')
        ->and($content)
        ->toContain('ai_hub_api_keys')
        ->and($content)
        ->toContain('ai_hub_subscriptions');
});

it('includes TYPE declarations for gauges', function () {
    $response = $this->get('/api/metrics');

    $content = $response->getContent();

    expect($content)
        ->toContain('# TYPE ai_hub_users gauge');
});

it('does not require authentication', function () {
    $this->get('/api/metrics')->assertOk();
});

it('includes provider availability metrics', function () {
    $response = $this->get('/api/metrics');

    $content = $response->getContent();

    expect($content)
        ->toContain('ai_hub_provider_active');
});

it('includes subscription status metrics', function () {
    $response = $this->get('/api/metrics');

    $content = $response->getContent();

    expect($content)
        ->toContain('ai_hub_subscriptions{status="active"}')
        ->and($content)
        ->toContain('ai_hub_subscriptions{status="trialing"}')
        ->and($content)
        ->toContain('ai_hub_subscriptions{status="past_due"}');
});
