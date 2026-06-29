<?php

use App\Actions\Billing\CreateStripeCheckoutSession;
use App\Models\BillingInvoice;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('creates a stripe checkout session and stores payment reference on invoice', function () {
    config()->set('services.stripe.secret', 'sk_test_123');
    config()->set('services.billing.checkout_success_url', 'https://app.example.com/billing/success');
    config()->set('services.billing.checkout_cancel_url', 'https://app.example.com/billing/cancel');

    $user = User::factory()->create();
    $team = $user->currentTeam;

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

    Http::fake([
        'https://api.stripe.com/v1/checkout/sessions' => Http::response([
            'id' => 'cs_test_123',
            'url' => 'https://checkout.stripe.com/pay/cs_test_123',
        ], 200),
    ]);

    $updatedInvoice = app(CreateStripeCheckoutSession::class)->handle($invoice);

    Http::assertSent(function (Request $request) use ($invoice) {
        $data = $request->data();

        return str_contains($request->url(), 'checkout/sessions')
            && ($data['metadata[invoice_number]'] ?? null) === $invoice->invoice_number
            && ($data['line_items[0][price_data][unit_amount]'] ?? null) === 500;
    });

    expect($updatedInvoice->payment_provider)->toBe('stripe');
    expect($updatedInvoice->payment_reference)->toBe('cs_test_123');
    expect($updatedInvoice->payment_url)->toBe('https://checkout.stripe.com/pay/cs_test_123');
});
