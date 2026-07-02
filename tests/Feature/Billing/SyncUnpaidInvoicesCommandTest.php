<?php

use App\Actions\Billing\CheckStripeCheckoutPayment;
use App\Models\BillingInvoice;
use App\Models\User;

beforeEach(function () {
    $user = User::factory()->create();
    $this->team = $user->currentTeam;
});

it('marks unpaid invoices as paid when Stripe confirms payment', function () {
    $invoice = BillingInvoice::create([
        'team_id' => $this->team->id,
        'invoice_number' => 'INV-202607-T000001',
        'billing_month' => '2026-07-01',
        'currency' => 'USD',
        'status' => 'issued',
        'payment_reference' => 'cs_test_123',
        'subtotal_cents' => 1000,
        'tax_cents' => 0,
        'total_cents' => 1000,
        'issued_at' => now(),
        'due_at' => now()->addDays(30),
    ]);

    $this->mock(CheckStripeCheckoutPayment::class, function ($mock) {
        $mock->shouldReceive('handle')
            ->with('cs_test_123')
            ->andReturn('paid');
    });

    $this->artisan('billing:sync-unpaid-invoices')
        ->assertSuccessful()
        ->expectsOutputToContain('1 marked as paid');

    $invoice->refresh();
    expect($invoice->status)->toBe('paid');
    expect($invoice->paid_at)->not->toBeNull();
});

it('does not update invoices when Stripe shows unpaid', function () {
    $invoice = BillingInvoice::create([
        'team_id' => $this->team->id,
        'invoice_number' => 'INV-202607-T000002',
        'billing_month' => '2026-07-01',
        'currency' => 'USD',
        'status' => 'issued',
        'payment_reference' => 'cs_test_456',
        'subtotal_cents' => 1000,
        'tax_cents' => 0,
        'total_cents' => 1000,
        'issued_at' => now(),
        'due_at' => now()->addDays(30),
    ]);

    $this->mock(CheckStripeCheckoutPayment::class, function ($mock) {
        $mock->shouldReceive('handle')
            ->with('cs_test_456')
            ->andReturn('unpaid');
    });

    $this->artisan('billing:sync-unpaid-invoices')
        ->assertSuccessful()
        ->expectsOutputToContain('0 marked as paid');

    $invoice->refresh();
    expect($invoice->status)->toBe('issued');
    expect($invoice->paid_at)->toBeNull();
});

it('skips invoices without Stripe checkout session reference', function () {
    BillingInvoice::create([
        'team_id' => $this->team->id,
        'invoice_number' => 'INV-202607-T000003',
        'billing_month' => '2026-07-01',
        'currency' => 'USD',
        'status' => 'issued',
        'payment_reference' => null,
        'subtotal_cents' => 1000,
        'tax_cents' => 0,
        'total_cents' => 1000,
        'issued_at' => now(),
        'due_at' => now()->addDays(30),
    ]);

    $this->artisan('billing:sync-unpaid-invoices')
        ->assertSuccessful()
        ->expectsOutputToContain('No unpaid invoices');
});

it('handles overdue invoices as well', function () {
    $invoice = BillingInvoice::create([
        'team_id' => $this->team->id,
        'invoice_number' => 'INV-202607-T000004',
        'billing_month' => '2026-07-01',
        'currency' => 'USD',
        'status' => 'overdue',
        'payment_reference' => 'cs_test_789',
        'subtotal_cents' => 1000,
        'tax_cents' => 0,
        'total_cents' => 1000,
        'issued_at' => now()->subDays(40),
        'due_at' => now()->subDays(10),
    ]);

    $this->mock(CheckStripeCheckoutPayment::class, function ($mock) {
        $mock->shouldReceive('handle')
            ->with('cs_test_789')
            ->andReturn('paid');
    });

    $this->artisan('billing:sync-unpaid-invoices')
        ->assertSuccessful()
        ->expectsOutputToContain('1 marked as paid');

    $invoice->refresh();
    expect($invoice->status)->toBe('paid');
});

it('reports errors when Stripe API call fails', function () {
    BillingInvoice::create([
        'team_id' => $this->team->id,
        'invoice_number' => 'INV-202607-T000005',
        'billing_month' => '2026-07-01',
        'currency' => 'USD',
        'status' => 'issued',
        'payment_reference' => 'cs_test_error',
        'subtotal_cents' => 1000,
        'tax_cents' => 0,
        'total_cents' => 1000,
        'issued_at' => now(),
        'due_at' => now()->addDays(30),
    ]);

    $this->mock(CheckStripeCheckoutPayment::class, function ($mock) {
        $mock->shouldReceive('handle')
            ->andThrow(new Exception('Stripe API error'));
    });

    $this->artisan('billing:sync-unpaid-invoices')
        ->assertFailed()
        ->expectsOutputToContain('1 error(s)');
});
