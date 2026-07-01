<?php

use App\Models\User;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Customer;
use Stripe\StripeClient;

function mockStripeForWalletRecharge(string $sessionId, string $sessionUrl): StripeClient
{
    $session = StripeSession::constructFrom([
        'id' => $sessionId,
        'url' => $sessionUrl,
    ]);

    $customer = Customer::constructFrom(['id' => 'cus_test_ctrl']);

    $customersService = Mockery::mock();
    $customersService->shouldReceive('retrieve')->andReturn($customer);
    $customersService->shouldReceive('create')->andReturn($customer);

    $checkoutSessionsService = Mockery::mock();
    $checkoutSessionsService->shouldReceive('create')->andReturn($session);

    $checkoutService = Mockery::mock();
    $checkoutService->sessions = $checkoutSessionsService;

    $mock = Mockery::mock(StripeClient::class);
    $mock->customers = $customersService;
    $mock->checkout = $checkoutService;

    return $mock;
}

it('creates a stripe checkout session for an authenticated team member', function () {
    config()->set('cashier.secret', 'sk_test_123');

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $team->update(['stripe_id' => 'cus_test_ctrl']);

    app()->bind(StripeClient::class, fn () => mockStripeForWalletRecharge(
        'cs_test_controller_1',
        'https://checkout.stripe.com/pay/cs_test_controller_1',
    ));

    $response = $this->actingAs($user)
        ->postJson("/{$team->slug}/billing/wallet/recharge", [
            'amount_cents' => 50_00,
        ]);

    $response->assertCreated();
    $response->assertJsonPath('session_id', 'cs_test_controller_1');
    $response->assertJsonPath('url', 'https://checkout.stripe.com/pay/cs_test_controller_1');
});

it('rejects unauthenticated requests to the wallet recharge endpoint', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    // Web-guarded routes redirect to login (302) instead of returning 401.
    $this->postJson("/{$team->slug}/billing/wallet/recharge", [
        'amount_cents' => 50_00,
    ])->assertRedirect();
});

it('rejects a team member who does not belong to the requested team', function () {
    config()->set('cashier.secret', 'sk_test_123');

    $owner = User::factory()->create();
    $team = $owner->currentTeam;

    $intruder = User::factory()->create();

    $this->actingAs($intruder)
        ->postJson("/{$team->slug}/billing/wallet/recharge", [
            'amount_cents' => 50_00,
        ])->assertForbidden();
});

it('validates the amount is at least 100 cents', function () {
    config()->set('cashier.secret', 'sk_test_123');

    $user = User::factory()->create();
    $team = $user->currentTeam;

    $this->actingAs($user)
        ->post("/{$team->slug}/billing/wallet/recharge", [
            'amount_cents' => 50,
        ])->assertSessionHasErrors(['amount_cents']);
});

it('accepts a custom currency code', function () {
    config()->set('cashier.secret', 'sk_test_123');

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $team->update(['stripe_id' => 'cus_test_ctrl']);

    $capturedParams = null;

    $session = StripeSession::constructFrom([
        'id' => 'cs_test_eur',
        'url' => 'https://checkout.stripe.com/pay/cs_test_eur',
    ]);
    $customer = Customer::constructFrom(['id' => 'cus_test_ctrl']);

    $customersService = Mockery::mock();
    $customersService->shouldReceive('retrieve')->andReturn($customer);

    $checkoutSessionsService = Mockery::mock();
    $checkoutSessionsService->shouldReceive('create')->once()->andReturnUsing(function ($params) use (&$capturedParams, $session) {
        $capturedParams = $params;

        return $session;
    });

    $checkoutService = Mockery::mock();
    $checkoutService->sessions = $checkoutSessionsService;

    $mock = Mockery::mock(StripeClient::class);
    $mock->customers = $customersService;
    $mock->checkout = $checkoutService;

    app()->bind(StripeClient::class, fn () => $mock);

    $this->actingAs($user)
        ->postJson("/{$team->slug}/billing/wallet/recharge", [
            'amount_cents' => 25_00,
            'currency' => 'EUR',
        ])->assertCreated();

    expect($capturedParams)->not->toBeNull();
    expect($capturedParams['metadata']['recharge_currency'])->toBe('eur');
});
