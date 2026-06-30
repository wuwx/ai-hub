<?php

use App\Actions\Fortify\CreateNewUser;
use App\Models\TeamWallet;
use App\Models\TeamWalletTransaction;

it('grants signup credit when a user is created via CreateNewUser', function () {
    $action = app(CreateNewUser::class);

    $user = $action->create([
        'name' => 'Test User',
        'email' => 'test-signup@example.com',
        'password' => 'SecurePassword123!',
        'password_confirmation' => 'SecurePassword123!',
    ]);

    $team = $user->currentTeam;

    expect($team)->not->toBeNull();

    $wallet = TeamWallet::where('team_id', $team->id)->first();

    expect($wallet)->not->toBeNull()
        ->and($wallet->balance_cents)->toBe(500);

    $transaction = TeamWalletTransaction::where('team_id', $team->id)->first();

    expect($transaction)->not->toBeNull()
        ->and($transaction->type)->toBe('recharge')
        ->and($transaction->amount_cents)->toBe(500)
        ->and($transaction->description)->toBe('Signup credit')
        ->and($transaction->metadata)->toBe(['type' => 'signup_bonus']);
});

it('respects the configured signup credit amount', function () {
    config(['services.billing.signup_credit_cents' => 1000]);

    $action = app(CreateNewUser::class);

    $user = $action->create([
        'name' => 'Test User 2',
        'email' => 'test-signup2@example.com',
        'password' => 'SecurePassword123!',
        'password_confirmation' => 'SecurePassword123!',
    ]);

    $wallet = $user->currentTeam->wallet;

    expect($wallet->balance_cents)->toBe(1000);
});

it('does not grant credit when signup_credit_cents is zero', function () {
    config(['services.billing.signup_credit_cents' => 0]);

    $action = app(CreateNewUser::class);

    $user = $action->create([
        'name' => 'Test User 3',
        'email' => 'test-signup3@example.com',
        'password' => 'SecurePassword123!',
        'password_confirmation' => 'SecurePassword123!',
    ]);

    $team = $user->currentTeam;

    $transaction = TeamWalletTransaction::where('team_id', $team->id)->first();

    expect($transaction)->toBeNull();
});
