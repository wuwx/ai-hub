<?php

use App\Models\BillingInvoice;
use App\Models\User;

it('marks issued invoices as overdue when due date has passed', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $overdueCandidate = BillingInvoice::create([
        'team_id' => $team->id,
        'invoice_number' => 'INV-202606-T000001',
        'billing_month' => '2026-06-01',
        'currency' => 'USD',
        'status' => 'issued',
        'subtotal_cents' => 100,
        'tax_cents' => 0,
        'total_cents' => 100,
        'issued_at' => now()->subDays(20),
        'due_at' => now()->subDay(),
    ]);

    $stillIssued = BillingInvoice::create([
        'team_id' => $team->id,
        'invoice_number' => 'INV-202606-T000002',
        'billing_month' => '2026-07-01',
        'currency' => 'USD',
        'status' => 'issued',
        'subtotal_cents' => 200,
        'tax_cents' => 0,
        'total_cents' => 200,
        'issued_at' => now()->subDays(10),
        'due_at' => now()->addDay(),
    ]);

    $this->artisan('billing:mark-overdue-invoices')->assertSuccessful();

    $overdueCandidate->refresh();
    $stillIssued->refresh();

    expect($overdueCandidate->status)->toBe('overdue');
    expect($stillIssued->status)->toBe('issued');
});
