<?php

use App\Http\Controllers\DataExportController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::view('docs', 'docs')->name('docs');
Route::view('terms', 'terms')->name('terms');
Route::view('privacy', 'privacy')->name('privacy');

Route::middleware(['auth', 'verified'])
    ->group(function () {
        Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');

        Route::livewire('api-keys', 'pages::api-keys')->name('api-keys.index');

        Route::livewire('usage', 'pages::usage')->name('usage.index');

        Route::livewire('playground', 'pages::playground')->name(
            'playground.index',
        );

        Route::livewire('billing', 'pages::billing')->name('billing.index');

        Route::livewire('request-logs', 'pages::request-logs')->name(
            'request-logs.index',
        );

        // CSV data export endpoints.
        Route::get('usage/export', [
            DataExportController::class,
            'exportUsage',
        ])->name('usage.export');
    });

require __DIR__.'/settings.php';
