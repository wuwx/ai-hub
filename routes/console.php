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

Schedule::call(function () {
    TeamInvitation::query()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->delete();
})->daily()->description('Delete expired team invitations');
