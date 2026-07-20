<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('gateway:prune-request-logs --days=30')
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->description(
        'Delete request logs older than 30 days; usage ledgers retain billing totals',
    );
