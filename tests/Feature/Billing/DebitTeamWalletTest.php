<?php

use App\Actions\Billing\DebitTeamWallet;
use App\Actions\Billing\RechargeTeamWallet;
use App\Exceptions\InsufficientWalletBalanceException;
use App\Models\TeamWalletTransaction;
use App\Models\User;

it('debits the wallet atomically and records a transaction', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    app(RechargeTeamWallet::class)->handle($team, 50_00, 'Seed');

    $transaction = app(DebitTeamWallet::class)->handle(
        team: $team,
        amountCents: 15_00,
        description: 'Test debit',
    );

    expect($transaction)->toBeInstanceOf(TeamWalletTransaction::class);
    expect($transaction->amount_cents)->toBe(-1500);
    expect($transaction->balance_after_cents)->toBe(3500);
    expect($transaction->type)->toBe('debit');
});

it('throws when pre-paid balance is insufficient', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    app(RechargeTeamWallet::class)->handle($team, 10_00, 'Seed');

    app(DebitTeamWallet::class)->handle(
        team: $team,
        amountCents: 25_00,
        description: 'Should fail',
    );
})->throws(InsufficientWalletBalanceException::class);

it('burns promo credit before cash balance', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    // Seed cash + promo credit
    app(RechargeTeamWallet::class)->handle($team, 20_00, 'Cash');
    $team->load('wallet');
    $team->wallet->update(['credit_grant_cents' => 10_00]);

    app(DebitTeamWallet::class)->handle(
        team: $team,
        amountCents: 15_00,
        description: 'Mixed debit',
    );

    $team->wallet->refresh();
    // 1000 promo + 500 from cash used
    expect($team->wallet->credit_grant_cents)->toBe(0);
    expect($team->wallet->balance_cents)->toBe(1500);
});

it('returns null for zero-amount debits', function () {
    $user = User::factory()->create();

    $transaction = app(DebitTeamWallet::class)->handle(
        team: $user->currentTeam,
        amountCents: 0,
        description: 'Zero',
    );

    expect($transaction)->toBeNull();
});

it('hasEnoughBalance returns false for teams without a wallet', function () {
    $user = User::factory()->create();

    expect(app(DebitTeamWallet::class)->hasEnoughBalance($user->currentTeam, 100))->toBeFalse();
});

it('hasEnoughBalance returns true when wallet covers the amount', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    app(RechargeTeamWallet::class)->handle($team, 50_00, 'Seed');

    expect(app(DebitTeamWallet::class)->hasEnoughBalance($team, 25_00))->toBeTrue();
    expect(app(DebitTeamWallet::class)->hasEnoughBalance($team, 75_00))->toBeFalse();
});

it('allows negative balance for post-paid teams', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    app(RechargeTeamWallet::class)->handle($team, 5_00, 'Seed');
    $team->load('wallet');
    $team->wallet->update(['is_postpaid' => true]);

    // Post-paid: any amount is allowed
    expect(app(DebitTeamWallet::class)->hasEnoughBalance($team, 1_000_00))->toBeTrue();

    $transaction = app(DebitTeamWallet::class)->handle(
        team: $team,
        amountCents: 50_00,
        description: 'Post-paid debit',
    );

    $team->wallet->refresh();
    expect($team->wallet->balance_cents)->toBe(-4500);
    expect($transaction)->not->toBeNull();
});
