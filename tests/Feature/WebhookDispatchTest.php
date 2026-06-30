<?php

use App\Actions\Webhooks\DispatchWebhookEvent;
use App\Models\TeamWebhookEndpoint;
use App\Models\User;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;

it('dispatches a webhook event to all active endpoints for a team', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    TeamWebhookEndpoint::create([
        'team_id' => $team->id,
        'url' => 'https://customer.example.com/webhooks',
        'is_active' => true,
    ]);

    Http::fake([
        'https://customer.example.com/webhooks' => Http::response([], 200),
    ]);

    app(DispatchWebhookEvent::class)->handle($team, 'quota.threshold_exceeded', [
        'period' => 'daily',
        'used' => 8000,
        'limit' => 10000,
        'percentage' => 80,
    ]);

    Http::assertSent(function (HttpRequest $request) {
        return $request->url() === 'https://customer.example.com/webhooks'
            && $request->hasHeader('X-Webhook-Event', 'quota.threshold_exceeded')
            && $request->hasHeader('X-Webhook-Signature')
            && $request['event'] === 'quota.threshold_exceeded'
            && $request['data']['period'] === 'daily';
    });
});

it('includes an HMAC signature in the webhook header', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $endpoint = TeamWebhookEndpoint::create([
        'team_id' => $team->id,
        'url' => 'https://customer.example.com/webhooks',
        'secret' => 'test-secret',
        'is_active' => true,
    ]);

    Http::fake([
        'https://customer.example.com/webhooks' => Http::response([], 200),
    ]);

    app(DispatchWebhookEvent::class)->handle($team, 'test.event', ['foo' => 'bar']);

    Http::assertSent(function (HttpRequest $request) {
        $expectedSignature = 'sha256='.hash_hmac('sha256', $request->body(), 'test-secret');

        return $request->header('X-Webhook-Signature')[0] === $expectedSignature;
    });
});

it('does not send webhooks to inactive endpoints', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    TeamWebhookEndpoint::create([
        'team_id' => $team->id,
        'url' => 'https://inactive.example.com/webhooks',
        'is_active' => false,
    ]);

    Http::fake();

    app(DispatchWebhookEvent::class)->handle($team, 'test.event');

    Http::assertNothingSent();
});

it('respects event subscriptions when filtering endpoints', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $matchingEndpoint = TeamWebhookEndpoint::create([
        'team_id' => $team->id,
        'url' => 'https://matching.example.com/webhooks',
        'events' => ['invoice.overdue'],
        'is_active' => true,
    ]);

    $nonMatchingEndpoint = TeamWebhookEndpoint::create([
        'team_id' => $team->id,
        'url' => 'https://non-matching.example.com/webhooks',
        'events' => ['wallet.balance_low'],
        'is_active' => true,
    ]);

    Http::fake([
        'https://matching.example.com/webhooks' => Http::response([], 200),
        'https://non-matching.example.com/webhooks' => Http::response([], 200),
    ]);

    app(DispatchWebhookEvent::class)->handle($team, 'invoice.overdue', [
        'invoice_number' => 'INV-001',
    ]);

    Http::assertSent(function (HttpRequest $request) {
        return $request->url() === 'https://matching.example.com/webhooks';
    });

    Http::assertNotSent(function (HttpRequest $request) {
        return $request->url() === 'https://non-matching.example.com/webhooks';
    });
});

it('sends to all events when events list is empty', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    TeamWebhookEndpoint::create([
        'team_id' => $team->id,
        'url' => 'https://all-events.example.com/webhooks',
        'events' => null,
        'is_active' => true,
    ]);

    Http::fake([
        'https://all-events.example.com/webhooks' => Http::response([], 200),
    ]);

    app(DispatchWebhookEvent::class)->handle($team, 'any.event', ['key' => 'value']);

    Http::assertSent(function (HttpRequest $request) {
        return $request->url() === 'https://all-events.example.com/webhooks';
    });
});

it('resets failure count on successful delivery', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $endpoint = TeamWebhookEndpoint::create([
        'team_id' => $team->id,
        'url' => 'https://recovery.example.com/webhooks',
        'is_active' => true,
        'failure_count' => 5,
    ]);

    Http::fake([
        'https://recovery.example.com/webhooks' => Http::response([], 200),
    ]);

    app(DispatchWebhookEvent::class)->handle($team, 'test.event');

    expect($endpoint->fresh()->failure_count)->toBe(0);
});

it('increments failure count and auto-disables after 10 failures', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $endpoint = TeamWebhookEndpoint::create([
        'team_id' => $team->id,
        'url' => 'https://failing.example.com/webhooks',
        'is_active' => true,
        'failure_count' => 9,
    ]);

    Http::fake([
        'https://failing.example.com/webhooks' => Http::response([], 500),
    ]);

    app(DispatchWebhookEvent::class)->handle($team, 'test.event');

    $endpoint->refresh();

    expect($endpoint->failure_count)->toBe(10)
        ->and($endpoint->is_active)->toBeFalse();
});

it('auto-generates a secret when not provided', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $endpoint = TeamWebhookEndpoint::create([
        'team_id' => $team->id,
        'url' => 'https://auto-secret.example.com/webhooks',
        'is_active' => true,
    ]);

    expect($endpoint->secret)->not->toBeNull()->toHaveLength(32);
});
