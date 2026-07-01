<?php

use App\Http\Controllers\Billing\CashierWebhookController;
use App\Http\Controllers\Billing\CheckoutReturnController;
use App\Http\Controllers\Billing\StripePortalController;
use App\Http\Controllers\Billing\WalletRechargeController;
use App\Http\Controllers\DataExportController;
use App\Http\Controllers\InvoiceViewController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::view('docs', 'docs')->name('docs');

Route::view('terms', 'terms')->name('terms');

Route::view('privacy', 'privacy')->name('privacy');

// Cashier webhook & payment confirmation routes (no auth — called by Stripe).
Route::prefix(config('cashier.path', 'stripe'))
    ->name('cashier.')
    ->group(function () {
        Route::post('webhook', [CashierWebhookController::class, 'handleWebhook'])->name('webhook');
        Route::get('payment/{id}', '\Laravel\Cashier\Http\Controllers\PaymentController@show')->name('payment');
    });

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');

        Route::livewire('api-keys', 'pages::api-keys')->name('api-keys.index');

        Route::livewire('usage', 'pages::usage')->name('usage.index');

        Route::livewire('playground', 'pages::playground')->name('playground.index');

        Route::livewire('billing', 'pages::billing')->name('billing.index');

        Route::livewire('request-logs', 'pages::request-logs')->name('request-logs.index');

        // Stripe checkout return handlers — verify payment & redirect to billing.
        Route::get('billing/success', [CheckoutReturnController::class, 'invoice'])->name('billing.success');
        Route::get('billing/cancel', fn () => to_route('billing.index'))->name('billing.cancel');
        Route::get('billing/wallet/success', [CheckoutReturnController::class, 'wallet'])->name('billing.wallet.success');
        Route::get('billing/wallet/cancel', fn () => to_route('billing.index'))->name('billing.wallet.cancel');

        // Customer self-service wallet top-up endpoint.
        Route::post('billing/wallet/recharge', [WalletRechargeController::class, 'store'])
            ->name('billing.wallet.recharge');

        // Stripe Customer Portal — manage subscription, payment methods, invoices.
        Route::post('billing/portal', [StripePortalController::class, 'store'])
            ->name('billing.portal');

        // CSV data export endpoints.
        Route::get('usage/export', [DataExportController::class, 'exportUsage'])
            ->name('usage.export');

        Route::get('billing/transactions/export', [DataExportController::class, 'exportWalletTransactions'])
            ->name('billing.transactions.export');

        Route::get('billing/invoices/export', [DataExportController::class, 'exportInvoices'])
            ->name('billing.invoices.export');

        Route::get('billing/invoices/{invoice}', [InvoiceViewController::class, 'show'])
            ->name('billing.invoices.show');
    });

Route::middleware(['auth'])->group(function () {
    Route::livewire('invitations/{invitation}/accept', 'pages::teams.accept-invitation')->name('invitations.accept');
});

require __DIR__.'/settings.php';
