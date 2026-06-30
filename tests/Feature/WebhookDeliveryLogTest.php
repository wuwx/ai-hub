<?php

use App\Actions\Webhooks\DispatchWebhookEvent;
use App\Models\TeamWebhookEndpoint;
use App\Models\User;
use App\Models\WebhookDelivery;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

it('records a delivery log on successful webhook dispatch', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $endpoint = TeamWebhookEndpoint::create([
        'team_id' => $team->id,
        'url' => 'https://customer.example.com/webhooks',
        'is_active' => true,
    ]);

    Http::fake([
        'https://customer.example.com/webhooks' => Http::response(['ok' => true], 200),
    ]);

    app(DispatchWebhookEvent::class)->handle($team, 'test.event', ['key' => 'value']);

    $delivery = WebhookDelivery::where('team_webhook_endpoint_id', $endpoint->id)->first();

    expect($delivery)->not->toBeNull()
        ->and($delivery->event)->toBe('test.event')
        ->and($delivery->succeeded)->toBeTrue()
        ->and($delivery->response_status_code)->toBe(200)
        ->and($delivery->latency_ms)->toBeInt()
        ->and($delivery->payload['event'])->toBe('test.event')
        ->and($delivery->payload['data'])->toBe(['key' => 'value']);
});

it('records a delivery log on failed webhook dispatch', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $endpoint = TeamWebhookEndpoint::create([
        'team_id' => $team->id,
        'url' => 'https://failing.example.com/webhooks',
        'is_active' => true,
    ]);

    Http::fake([
        'https://failing.example.com/webhooks' => Http::response(['error' => 'bad'], 500),
    ]);

    app(DispatchWebhookEvent::class)->handle($team, 'test.event');

    $delivery = WebhookDelivery::where('team_webhook_endpoint_id', $endpoint->id)->first();

    expect($delivery)->not->toBeNull()
        ->and($delivery->succeeded)->toBeFalse()
        ->and($delivery->response_status_code)->toBe(500);
});

it('records error message when webhook delivery throws', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $endpoint = TeamWebhookEndpoint::create([
        'team_id' => $team->id,
        'url' => 'https://unreachable.example.com/webhooks',
        'is_active' => true,
    ]);

    Http::fake(function () {
        throw new ConnectionException('Could not resolve host');
    });

    app(DispatchWebhookEvent::class)->handle($team, 'test.event');

    $delivery = WebhookDelivery::where('team_webhook_endpoint_id', $endpoint->id)->first();

    expect($delivery)->not->toBeNull()
        ->and($delivery->succeeded)->toBeFalse()
        ->and($delivery->response_status_code)->toBeNull()
        ->and($delivery->error)->toContain('Could not resolve host');
});

it('stores the response body for debugging', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $endpoint = TeamWebhookEndpoint::create([
        'team_id' => $team->id,
        'url' => 'https://verbose.example.com/webhooks',
        'is_active' => true,
    ]);

    Http::fake([
        'https://verbose.example.com/webhooks' => Http::response('Server error details here', 500),
    ]);

    app(DispatchWebhookEvent::class)->handle($team, 'test.event');

    $delivery = WebhookDelivery::where('team_webhook_endpoint_id', $endpoint->id)->first();

    expect($delivery->response_body)->toBe('Server error details here');
});
