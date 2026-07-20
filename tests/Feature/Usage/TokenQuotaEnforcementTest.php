<?php

use App\Actions\Usage\EnforceTokenQuota;
use App\Exceptions\QuotaExceededException;
use App\Models\User;

it('allows requests when no quota is granted', function () {
    $user = User::factory()->create();

    $action = app(EnforceTokenQuota::class);

    $action->handle($user, 100);

    expect(true)->toBeTrue();
});

it('throws when daily quota would be exceeded', function () {
    $user = User::factory()->create();

    $this->grantQuota($user, daily: 100, monthly: 1000)
        ->consume('daily-tokens', 95);

    expect(fn () => app(EnforceTokenQuota::class)->handle($user, 10))
        ->toThrow(QuotaExceededException::class);
});

it('throws when monthly quota would be exceeded', function () {
    $user = User::factory()->create();

    $this->grantQuota($user, daily: 1000, monthly: 100)
        ->consume('monthly-tokens', 96);

    expect(fn () => app(EnforceTokenQuota::class)->handle($user, 5))
        ->toThrow(QuotaExceededException::class);
});

it('throws when weekly quota would be exceeded', function () {
    $user = User::factory()->create();

    $owner = $this->grantQuota($user, daily: 1000, weekly: 100, monthly: 10000);
    $owner->consume('weekly-tokens', 60);
    $owner->consume('weekly-tokens', 35);

    expect(fn () => app(EnforceTokenQuota::class)->handle($user, 10))
        ->toThrow(QuotaExceededException::class);
});

it('throws when weekly quota would be exceeded from weekly usage', function () {
    $user = User::factory()->create();

    $this->grantQuota($user, daily: 1000, weekly: 100, monthly: 10000)
        ->consume('weekly-tokens', 95);

    expect(fn () => app(EnforceTokenQuota::class)->handle($user, 10))
        ->toThrow(QuotaExceededException::class);
});
