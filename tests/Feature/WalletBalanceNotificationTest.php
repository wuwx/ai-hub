<?php

use App\Actions\Billing\RechargeTeamWallet;
use App\Models\BillingInvoice;
use App\Models\TeamWallet;
use App\Models\User;
use App\Notifications\Teams\InvoiceOverdue;
use App\Notifications\Teams\WalletBalanceLow;
use Illuminate\Support\Facades\Notification;

it('notifies team owner when wallet balance is low', function () {
    Notification::fake();

    $user = User::factory()->create();
    $team = $user->currentTeam;

    app(RechargeTeamWallet::class)->handle(
        team: $team,
        amountCents: 300,
        description: 'Small balance',
    );

    $this->artisan('billing:check-wallet-balances --threshold=500')
        ->assertSuccessful()
        ->expectsOutputToContain('notified 1 owner(s)');

    Notification::assertSentTo($user, WalletBalanceLow::class);
});

it('does not notify when wallet balance is above threshold', function () {
    Notification::fake();

    $user = User::factory()->create();
    $team = $user->currentTeam;

    app(RechargeTeamWallet::class)->handle(
        team: $team,
        amountCents: 10000,
        description: 'Large balance',
    );

    $this->artisan('billing:check-wallet-balances --threshold=500')
        ->assertSuccessful();

    Notification::assertNotSentTo($user, WalletBalanceLow::class);
});

it('does not notify post-paid teams', function () {
    Notification::fake();

    $user = User::factory()->create();
    $team = $user->currentTeam;

    // Convert to post-paid with negative balance
    TeamWallet::create([
        'team_id' => $team->id,
        'balance_cents' => -500,
        'credit_grant_cents' => 0,
        'currency' => 'USD',
        'is_postpaid' => true,
    ]);

    $this->artisan('billing:check-wallet-balances --threshold=500')
        ->assertSuccessful();

    Notification::assertNotSentTo($user, WalletBalanceLow::class);
});

it('deduplicates notifications within the same day', function () {
    Notification::fake();

    $user = User::factory()->create();
    $team = $user->currentTeam;

    app(RechargeTeamWallet::class)->handle(
        team: $team,
        amountCents: 200,
        description: 'Small balance',
    );

    $this->artisan('billing:check-wallet-balances --threshold=500')->assertSuccessful();

    // Second run on the same day should not re-notify
    $this->artisan('billing:check-wallet-balances --threshold=500')->assertSuccessful();

    Notification::assertSentTo($user, WalletBalanceLow::class, function ($notification) {
        return $notification->balanceCents === 200;
    });

    // Exactly one notification should have been sent
    Notification::assertSentToTimes($user, WalletBalanceLow::class, 1);
});

it('notifies team owner when an invoice becomes overdue', function () {
    Notification::fake();

    $user = User::factory()->create();
    $team = $user->currentTeam;

    BillingInvoice::create([
        'team_id' => $team->id,
        'invoice_number' => 'INV-2026-001',
        'billing_month' => now()->startOfMonth(),
        'currency' => 'USD',
        'status' => 'issued',
        'subtotal_cents' => 10000,
        'tax_cents' => 0,
        'total_cents' => 10000,
        'issued_at' => now()->subDays(30),
        'due_at' => now()->subDay(),
    ]);

    $this->artisan('billing:mark-overdue-invoices')
        ->assertSuccessful()
        ->expectsOutputToContain('Marked 1 invoice(s) as overdue');

    Notification::assertSentTo($user, InvoiceOverdue::class, function ($notification) {
        return $notification->invoiceNumber === 'INV-2026-001'
            && $notification->totalCents === 10000;
    });
});

it('does not send overdue notification for already-overdue invoices', function () {
    Notification::fake();

    $user = User::factory()->create();
    $team = $user->currentTeam;

    BillingInvoice::create([
        'team_id' => $team->id,
        'invoice_number' => 'INV-2026-002',
        'billing_month' => now()->startOfMonth(),
        'currency' => 'USD',
        'status' => 'overdue',
        'subtotal_cents' => 5000,
        'tax_cents' => 0,
        'total_cents' => 5000,
        'issued_at' => now()->subDays(30),
        'due_at' => now()->subDay(),
    ]);

    $this->artisan('billing:mark-overdue-invoices')
        ->assertSuccessful()
        ->expectsOutputToContain('Marked 0 invoice(s) as overdue');

    Notification::assertNotSentTo($user, InvoiceOverdue::class);
});
