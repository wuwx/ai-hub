<?php

use App\Models\BillingInvoice;
use App\Models\BillingInvoiceItem;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = $this->user->currentTeam;

    $this->invoice = BillingInvoice::create([
        'team_id' => $this->team->id,
        'invoice_number' => 'INV-2026-001',
        'billing_month' => now()->startOfMonth(),
        'currency' => 'USD',
        'status' => 'paid',
        'subtotal_cents' => 10000,
        'tax_cents' => 0,
        'total_cents' => 10000,
        'issued_at' => now()->subDays(15),
        'due_at' => now()->subDays(5),
        'paid_at' => now()->subDays(3),
    ]);

    BillingInvoiceItem::create([
        'billing_invoice_id' => $this->invoice->id,
        'description' => 'GPT-4.1 usage',
        'token_input' => 50000,
        'token_output' => 30000,
        'token_total' => 80000,
        'unit_amount_micros' => 125,
        'line_subtotal_cents' => 6000,
    ]);

    BillingInvoiceItem::create([
        'billing_invoice_id' => $this->invoice->id,
        'description' => 'Claude usage',
        'token_input' => 20000,
        'token_output' => 10000,
        'token_total' => 30000,
        'unit_amount_micros' => 150,
        'line_subtotal_cents' => 4000,
    ]);
});

it('displays the invoice view page', function () {
    $response = $this->actingAs($this->user)
        ->get("/{$this->team->slug}/billing/invoices/{$this->invoice->id}");

    $response->assertOk();
    $response->assertSee('INV-2026-001');
    $response->assertSee('GPT-4.1 usage');
    $response->assertSee('Claude usage');
});

it('shows the invoice total', function () {
    $response = $this->actingAs($this->user)
        ->get("/{$this->team->slug}/billing/invoices/{$this->invoice->id}");

    $response->assertOk();
    $response->assertSee('100.00 USD');
});

it('includes a print button', function () {
    $response = $this->actingAs($this->user)
        ->get("/{$this->team->slug}/billing/invoices/{$this->invoice->id}");

    $response->assertOk();
    $response->assertSee('Print / Save as PDF');
});

it('requires authentication', function () {
    $this->get("/{$this->team->slug}/billing/invoices/{$this->invoice->id}")
        ->assertRedirect('/login');
});

it('forbids access from non-team members', function () {
    $otherUser = User::factory()->create();

    $this->actingAs($otherUser)
        ->get("/{$this->team->slug}/billing/invoices/{$this->invoice->id}")
        ->assertForbidden();
});

it('returns 404 for invoices from other teams', function () {
    $otherUser = User::factory()->create();
    $otherTeam = $otherUser->currentTeam;

    $otherInvoice = BillingInvoice::create([
        'team_id' => $otherTeam->id,
        'invoice_number' => 'INV-OTHER-001',
        'billing_month' => now()->startOfMonth(),
        'currency' => 'USD',
        'status' => 'issued',
        'subtotal_cents' => 5000,
        'tax_cents' => 0,
        'total_cents' => 5000,
    ]);

    $this->actingAs($this->user)
        ->get("/{$this->team->slug}/billing/invoices/{$otherInvoice->id}")
        ->assertNotFound();
});
