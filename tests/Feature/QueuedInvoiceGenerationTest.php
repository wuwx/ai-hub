<?php

use App\Actions\Billing\GenerateMonthlyTeamInvoice;
use App\Actions\Billing\RechargeTeamWallet;
use App\Jobs\GenerateTeamMonthlyInvoiceJob;
use App\Models\BillingInvoice;
use App\Models\TeamWallet;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;

it('dispatches a job when the invoice generation command runs', function () {
    Queue::fake();

    $user = User::factory()->create();
    $team = $user->currentTeam;

    // Make it post-paid so the invoice gets generated
    TeamWallet::create([
        'team_id' => $team->id,
        'balance_cents' => 0,
        'credit_grant_cents' => 0,
        'currency' => 'USD',
        'is_postpaid' => true,
    ]);

    $this->artisan('billing:generate-monthly-invoices --month='.now()->subMonth()->format('Y-m'))
        ->assertSuccessful()
        ->expectsOutputToContain('Generated 1 invoice(s)');

    Queue::assertPushed(GenerateTeamMonthlyInvoiceJob::class);
});

it('processes the job and generates an invoice', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    TeamWallet::create([
        'team_id' => $team->id,
        'balance_cents' => 0,
        'credit_grant_cents' => 0,
        'currency' => 'USD',
        'is_postpaid' => true,
    ]);

    $targetMonth = CarbonImmutable::now()->subMonth()->startOfMonth();

    $job = new GenerateTeamMonthlyInvoiceJob($team->id, $targetMonth);
    $job->handle(app(GenerateMonthlyTeamInvoice::class));

    $invoice = BillingInvoice::where('team_id', $team->id)->first();

    expect($invoice)->not->toBeNull();
});

it('skips pre-paid teams in the job unless include-prepaid is set', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    app(RechargeTeamWallet::class)->handle(
        team: $team,
        amountCents: 5000,
        description: 'Pre-paid balance',
    );

    $targetMonth = CarbonImmutable::now()->subMonth()->startOfMonth();

    $job = new GenerateTeamMonthlyInvoiceJob($team->id, $targetMonth, includePrepaid: false);
    $job->handle(app(GenerateMonthlyTeamInvoice::class));

    $invoice = BillingInvoice::where('team_id', $team->id)->first();

    expect($invoice)->toBeNull();
});
