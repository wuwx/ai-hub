<?php

use App\Actions\Usage\CheckQuotaThresholds;
use App\Models\QuotaPolicy;
use App\Models\UsageLedger;
use App\Models\User;
use App\Notifications\QuotaThresholdExceeded;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

it('sends a notification when daily usage crosses the alert threshold', function () {
    Notification::fake();
    Cache::flush();

    $user = User::factory()->create();

    QuotaPolicy::create([
        'user_id' => $user->id,
        'daily_token_limit' => 1000,
        'daily_alert_threshold' => 80,
        'monthly_token_limit' => 10000,
        'monthly_alert_threshold' => 80,
        'effective_from' => now()->subDay(),
        'is_active' => true,
    ]);

    UsageLedger::create([
        'user_id' => $user->id,
        'bucket_date' => now()->toDateString(),
        'bucket_type' => 'day',
        'token_total' => 850,
        'token_input' => 400,
        'token_output' => 450,
        'request_count' => 5,
        'error_count' => 0,
    ]);

    app(CheckQuotaThresholds::class)->handle($user);

    Notification::assertSentTo($user, QuotaThresholdExceeded::class);
});

it('does not send a notification when usage is below the threshold', function () {
    Notification::fake();
    Cache::flush();

    $user = User::factory()->create();

    QuotaPolicy::create([
        'user_id' => $user->id,
        'daily_token_limit' => 1000,
        'daily_alert_threshold' => 80,
        'monthly_token_limit' => 10000,
        'monthly_alert_threshold' => 80,
        'effective_from' => now()->subDay(),
        'is_active' => true,
    ]);

    UsageLedger::create([
        'user_id' => $user->id,
        'bucket_date' => now()->toDateString(),
        'bucket_type' => 'day',
        'token_total' => 500,
        'token_input' => 300,
        'token_output' => 200,
        'request_count' => 3,
        'error_count' => 0,
    ]);

    app(CheckQuotaThresholds::class)->handle($user);

    Notification::assertNothingSent();
});

it('does not send duplicate notifications within the same period', function () {
    Notification::fake();
    Cache::flush();

    $user = User::factory()->create();

    QuotaPolicy::create([
        'user_id' => $user->id,
        'daily_token_limit' => 1000,
        'daily_alert_threshold' => 80,
        'monthly_token_limit' => 10000,
        'monthly_alert_threshold' => 80,
        'effective_from' => now()->subDay(),
        'is_active' => true,
    ]);

    UsageLedger::create([
        'user_id' => $user->id,
        'bucket_date' => now()->toDateString(),
        'bucket_type' => 'day',
        'token_total' => 900,
        'token_input' => 500,
        'token_output' => 400,
        'request_count' => 6,
        'error_count' => 0,
    ]);

    app(CheckQuotaThresholds::class)->handle($user);
    app(CheckQuotaThresholds::class)->handle($user);

    Notification::assertSentTo($user, QuotaThresholdExceeded::class, 1);
});

it('sends a monthly threshold notification independently from the daily one', function () {
    Notification::fake();
    Cache::flush();

    $user = User::factory()->create();

    QuotaPolicy::create([
        'user_id' => $user->id,
        'daily_token_limit' => 10000,
        'daily_alert_threshold' => 90,
        'monthly_token_limit' => 1000,
        'monthly_alert_threshold' => 80,
        'effective_from' => now()->subDay(),
        'is_active' => true,
    ]);

    UsageLedger::create([
        'user_id' => $user->id,
        'bucket_date' => now()->startOfMonth()->toDateString(),
        'bucket_type' => 'month',
        'token_total' => 850,
        'token_input' => 500,
        'token_output' => 350,
        'request_count' => 10,
        'error_count' => 0,
    ]);

    app(CheckQuotaThresholds::class)->handle($user);

    Notification::assertSentTo($user, QuotaThresholdExceeded::class, function (QuotaThresholdExceeded $notification) {
        return $notification->period === 'monthly';
    });
});

it('does nothing when no active quota policy exists', function () {
    Notification::fake();
    Cache::flush();

    $user = User::factory()->create();

    app(CheckQuotaThresholds::class)->handle($user);

    Notification::assertNothingSent();
});
