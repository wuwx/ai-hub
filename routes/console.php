<?php

use App\Models\TeamInvitation;
use Illuminate\Support\Facades\Schedule;

Schedule::command('billing:generate-monthly-invoices --month='.now()->subMonth()->format('Y-m'))
    ->monthlyOn(1, '01:10')
    ->withoutOverlapping()
    ->description('Generate monthly usage invoices for the previous month');

Schedule::command('billing:mark-overdue-invoices')
    ->hourly()
    ->withoutOverlapping()
    ->description('Mark issued invoices as overdue after due date');

Schedule::command('billing:check-wallet-balances --threshold=500')
    ->hourly()
    ->withoutOverlapping()
    ->description('Notify team owners when pre-paid wallet balance is low');

Schedule::call(function () {
    TeamInvitation::query()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->delete();
})->daily()->description('Delete expired team invitations');

Schedule::command('gateway:check-provider-health')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->description('Poll active LLM providers and update their health status');

Schedule::command('gateway:prune-request-logs --days=30')
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->description('Delete request logs older than 30 days; usage ledgers retain billing totals');

Schedule::command('gateway:detect-anomalous-usage --window=60 --min-requests=50 --error-rate=50')
    ->hourly()
    ->withoutOverlapping()
    ->description('Scan recent traffic for anomalous API key usage and notify team owners');
