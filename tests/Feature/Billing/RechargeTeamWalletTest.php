<?php

use App\Actions\Billing\RechargeTeamWallet;
use App\Models\TeamWallet;
use App\Models\User;

it('creates a wallet and credits the balance', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $transaction = app(RechargeTeamWallet::class)->handle(
        team: $team,
        amountCents: 50_00,
        description: 'Initial deposit',
    );

    expect($transaction->amount_cents)->toBe(5000);
    expect($transaction->type)->toBe('recharge');
    expect($transaction->balance_after_cents)->toBe(5000);

    $team->load('wallet');
    expect($team->wallet->balance_cents)->toBe(5000);
    expect($team->wallet->last_recharged_at)->not->toBeNull();
});

it('accumulates balance across multiple recharges', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    app(RechargeTeamWallet::class)->handle($team, 20_00, 'First');
    app(RechargeTeamWallet::class)->handle($team, 30_00, 'Second');

    $team->load('wallet');
    expect($team->wallet->balance_cents)->toBe(5000);
});

it('stores a reference id for idempotency', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $transaction = app(RechargeTeamWallet::class)->handle(
        team: $team,
        amountCents: 10_00,
        description: 'Stripe charge',
        referenceId: 'stripe_ch_xxx',
    );

    expect($transaction->reference_id)->toBe('stripe_ch_xxx');
});

it('throws for non-positive recharge amounts', function () {
    $user = User::factory()->create();

    app(RechargeTeamWallet::class)->handle($user->currentTeam, 0, 'Bad');
})->throws(InvalidArgumentException::class);

it('sets the wallet currency from billing config', function () {
    config()->set('services.billing.currency', 'EUR');

    $user = User::factory()->create();

    app(RechargeTeamWallet::class)->handle($user->currentTeam, 10_00, 'Seed');

    $wallet = TeamWallet::query()->where('team_id', $user->currentTeam->id)->first();
    expect($wallet->currency)->toBe('EUR');
});
