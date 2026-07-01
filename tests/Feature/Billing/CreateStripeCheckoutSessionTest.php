<?php

use App\Actions\Billing\CreateStripeCheckoutSession;
use App\Models\BillingInvoice;
use App\Models\User;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Customer;
use Stripe\StripeClient;

function mockStripeClientForCheckout(string $sessionId = 'cs_test_123', string $sessionUrl = 'https://checkout.stripe.com/pay/cs_test_123'): StripeClient
{
    $session = StripeSession::constructFrom([
        'id' => $sessionId,
        'url' => $sessionUrl,
    ]);

    $customer = Customer::constructFrom(['id' => 'cus_test_123']);

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

it('creates a stripe checkout session and stores payment reference on invoice', function () {
    config()->set('cashier.secret', 'sk_test_123');
    config()->set('services.billing.checkout_success_url', 'https://app.example.com/billing/success');
    config()->set('services.billing.checkout_cancel_url', 'https://app.example.com/billing/cancel');

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $team->update(['stripe_id' => 'cus_test_123']);

    $invoice = BillingInvoice::create([
        'team_id' => $team->id,
        'invoice_number' => 'INV-202606-T000001',
        'billing_month' => '2026-06-01',
        'currency' => 'USD',
        'status' => 'issued',
        'subtotal_cents' => 500,
        'tax_cents' => 0,
        'total_cents' => 500,
        'issued_at' => now(),
    ]);

    app()->bind(StripeClient::class, fn () => mockStripeClientForCheckout('cs_test_123', 'https://checkout.stripe.com/pay/cs_test_123'));

    $updatedInvoice = app(CreateStripeCheckoutSession::class)->handle($invoice);

    expect($updatedInvoice->payment_provider)->toBe('stripe');
    expect($updatedInvoice->payment_reference)->toBe('cs_test_123');
    expect($updatedInvoice->payment_url)->toBe('https://checkout.stripe.com/pay/cs_test_123');
});
