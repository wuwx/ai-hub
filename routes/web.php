<?php

use Illuminate\Support\Facades\Route;
use Spatie\Health\Http\Controllers\HealthCheckResultsController;

Route::get('health', HealthCheckResultsController::class);

Route::view('/', 'welcome')->name('home');

Route::view('docs', 'docs')->name('docs');
Route::view('terms', 'terms')->name('terms');
Route::view('privacy', 'privacy')->name('privacy');

Route::middleware(['auth', 'verified'])
    ->group(function () {
        Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');

        Route::livewire('api-keys', 'pages::api-keys')->name('api-keys.index');

        Route::livewire('playground', 'pages::playground')->name(
            'playground.index',
        );

        Route::livewire('billing', 'pages::billing')->name('billing.index');
    });

require __DIR__.'/settings.php';
