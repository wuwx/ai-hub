<?php

use App\Actions\Billing\CreateWalletRechargeSession;
use App\Models\User;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Customer;
use Stripe\Exception\UnknownApiErrorException;
use Stripe\StripeClient;

function mockStripeWalletClient(string $sessionId, string $sessionUrl): StripeClient
{
    $session = StripeSession::constructFrom([
        'id' => $sessionId,
        'url' => $sessionUrl,
    ]);

    $customer = Customer::constructFrom(['id' => 'cus_test_wallet']);

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

it('creates a stripe checkout session for a wallet top-up', function () {
    config()->set('cashier.secret', 'sk_test_123');
    config()->set('services.billing.wallet_recharge_success_url', 'https://app.example.com/billing/wallet/success');
    config()->set('services.billing.wallet_recharge_cancel_url', 'https://app.example.com/billing/wallet/cancel');

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $team->update(['stripe_id' => 'cus_test_wallet']);

    app()->bind(StripeClient::class, fn () => mockStripeWalletClient('cs_test_wallet_1', 'https://checkout.stripe.com/pay/cs_test_wallet_1'));

    $result = app(CreateWalletRechargeSession::class)->handle($team, 50_00);

    expect($result['session_id'])->toBe('cs_test_wallet_1');
    expect($result['url'])->toBe('https://checkout.stripe.com/pay/cs_test_wallet_1');
});

it('includes the requested currency in the stripe payload', function () {
    config()->set('cashier.secret', 'sk_test_123');

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $team->update(['stripe_id' => 'cus_test_wallet']);

    $capturedParams = null;

    $session = StripeSession::constructFrom(['id' => 'cs_test_2', 'url' => 'https://checkout.stripe.com/pay/cs_test_2']);
    $customer = Customer::constructFrom(['id' => 'cus_test_wallet']);

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

    app(CreateWalletRechargeSession::class)->handle($team, 10_00, 'EUR');

    expect($capturedParams)->not->toBeNull();
    expect($capturedParams['metadata']['recharge_currency'])->toBe('eur');
});

it('throws when the recharge amount is non-positive', function () {
    config()->set('cashier.secret', 'sk_test_123');

    $user = User::factory()->create();

    app(CreateWalletRechargeSession::class)->handle($user->currentTeam, 0);
})->throws(RuntimeException::class, 'Recharge amount must be positive.');

it('throws when stripe secret is not configured', function () {
    config()->set('cashier.secret', '');

    $user = User::factory()->create();

    app(CreateWalletRechargeSession::class)->handle($user->currentTeam, 10_00);
})->throws(RuntimeException::class, 'Stripe secret key is not configured.');

it('throws when the stripe api rejects the request', function () {
    config()->set('cashier.secret', 'sk_test_123');

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $team->update(['stripe_id' => 'cus_test_wallet']);

    $customer = Customer::constructFrom(['id' => 'cus_test_wallet']);

    $customersService = Mockery::mock();
    $customersService->shouldReceive('retrieve')->andReturn($customer);

    $checkoutSessionsService = Mockery::mock();
    $checkoutSessionsService->shouldReceive('create')->andThrow(new UnknownApiErrorException('Stripe API error'));

    $checkoutService = Mockery::mock();
    $checkoutService->sessions = $checkoutSessionsService;

    $mock = Mockery::mock(StripeClient::class);
    $mock->customers = $customersService;
    $mock->checkout = $checkoutService;

    app()->bind(StripeClient::class, fn () => $mock);

    app(CreateWalletRechargeSession::class)->handle($team, 10_00);
})->throws(RuntimeException::class, 'Unable to create wallet recharge session via Cashier.');
