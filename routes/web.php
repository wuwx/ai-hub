<?php

use App\Http\Controllers\Billing\WalletRechargeController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');

        Route::livewire('api-keys', 'pages::api-keys')->name('api-keys.index');

        Route::livewire('usage', 'pages::usage')->name('usage.index');

        Route::livewire('billing', 'pages::billing')->name('billing.index');

        Route::livewire('request-logs', 'pages::request-logs')->name('request-logs.index');

        // Customer self-service wallet top-up endpoint.
        Route::post('billing/wallet/recharge', [WalletRechargeController::class, 'store'])
            ->name('billing.wallet.recharge');
    });

Route::middleware(['auth'])->group(function () {
    Route::livewire('invitations/{invitation}/accept', 'pages::teams.accept-invitation')->name('invitations.accept');
});

require __DIR__.'/settings.php';
