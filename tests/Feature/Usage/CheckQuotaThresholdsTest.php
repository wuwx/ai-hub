<?php

use App\Actions\Usage\CheckQuotaThresholds;
use App\Models\User;
use App\Notifications\QuotaThresholdExceeded;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

it('sends a notification when daily usage crosses the alert threshold', function () {
    Notification::fake();
    Cache::flush();

    $user = User::factory()->create();

    $this->grantQuota($user, daily: 1000, monthly: 10000)
        ->consume('daily-tokens', 850);

    app(CheckQuotaThresholds::class)->handle($user);

    Notification::assertSentTo($user, QuotaThresholdExceeded::class);
});

it('does not send a notification when usage is below the threshold', function () {
    Notification::fake();
    Cache::flush();

    $user = User::factory()->create();

    $this->grantQuota($user, daily: 1000, monthly: 10000)
        ->consume('daily-tokens', 500);

    app(CheckQuotaThresholds::class)->handle($user);

    Notification::assertNothingSent();
});

it('does not send duplicate notifications within the same period', function () {
    Notification::fake();
    Cache::flush();

    $user = User::factory()->create();

    $this->grantQuota($user, daily: 1000, monthly: 10000)
        ->consume('daily-tokens', 900);

    app(CheckQuotaThresholds::class)->handle($user);
    app(CheckQuotaThresholds::class)->handle($user);

    Notification::assertSentTo($user, QuotaThresholdExceeded::class, 1);
});

it('sends a monthly threshold notification independently from the daily one', function () {
    Notification::fake();
    Cache::flush();

    $user = User::factory()->create();

    $this->grantQuota($user, daily: 10000, monthly: 1000)
        ->consume('monthly-tokens', 850);

    app(CheckQuotaThresholds::class)->handle($user);

    Notification::assertSentTo($user, QuotaThresholdExceeded::class, function (QuotaThresholdExceeded $notification) {
        return $notification->period === 'monthly';
    });
});

it('does nothing when no quota is granted', function () {
    Notification::fake();
    Cache::flush();

    $user = User::factory()->create();

    app(CheckQuotaThresholds::class)->handle($user);

    Notification::assertNothingSent();
});
