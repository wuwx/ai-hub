<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('gateway:check-provider-health')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->description('Poll active LLM providers and update their health status');

Schedule::command('gateway:prune-request-logs --days=30')
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->description(
        'Delete request logs older than 30 days; usage ledgers retain billing totals',
    );

Schedule::command(
    'gateway:detect-anomalous-usage --window=60 --min-requests=50 --error-rate=50',
)
    ->hourly()
    ->withoutOverlapping()
    ->description(
        'Scan recent traffic for anomalous API key usage and notify users',
    );
