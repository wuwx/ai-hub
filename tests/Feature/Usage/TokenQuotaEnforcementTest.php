<?php

use App\Actions\Usage\EnforceTokenQuota;
use App\Exceptions\QuotaExceededException;
use App\Models\QuotaPolicy;
use App\Models\UsageLedger;
use App\Models\User;

it('allows requests when no active quota policy exists', function () {
    $user = User::factory()->create();

    $action = app(EnforceTokenQuota::class);

    $action->handle($user, 100);

    expect(true)->toBeTrue();
});

it('throws when daily quota would be exceeded', function () {
    $user = User::factory()->create();

    QuotaPolicy::create([
        'user_id' => $user->id,
        'daily_token_limit' => 100,
        'monthly_token_limit' => 1000,
        'effective_from' => now()->subDay(),
        'is_active' => true,
    ]);

    UsageLedger::create([
        'user_id' => $user->id,
        'bucket_date' => now()->toDateString(),
        'bucket_type' => 'day',
        'token_total' => 95,
        'token_input' => 40,
        'token_output' => 55,
        'request_count' => 1,
        'error_count' => 0,
    ]);

    expect(fn () => app(EnforceTokenQuota::class)->handle($user, 10))
        ->toThrow(QuotaExceededException::class);
});

it('throws when monthly quota would be exceeded', function () {
    $user = User::factory()->create();

    QuotaPolicy::create([
        'user_id' => $user->id,
        'daily_token_limit' => 1000,
        'monthly_token_limit' => 100,
        'effective_from' => now()->subDay(),
        'is_active' => true,
    ]);

    UsageLedger::create([
        'user_id' => $user->id,
        'bucket_date' => now()->startOfMonth()->toDateString(),
        'bucket_type' => 'month',
        'token_total' => 96,
        'token_input' => 50,
        'token_output' => 46,
        'request_count' => 3,
        'error_count' => 0,
    ]);

    expect(fn () => app(EnforceTokenQuota::class)->handle($user, 5))
        ->toThrow(QuotaExceededException::class);
});

it('throws when weekly quota would be exceeded', function () {
    $user = User::factory()->create();

    QuotaPolicy::create([
        'user_id' => $user->id,
        'daily_token_limit' => 1000,
        'weekly_token_limit' => 100,
        'monthly_token_limit' => 10000,
        'effective_from' => now()->subDay(),
        'is_active' => true,
    ]);

    UsageLedger::create([
        'user_id' => $user->id,
        'bucket_date' => now()->startOfWeek()->toDateString(),
        'bucket_type' => 'day',
        'token_total' => 60,
        'token_input' => 30,
        'token_output' => 30,
        'request_count' => 1,
        'error_count' => 0,
    ]);

    UsageLedger::create([
        'user_id' => $user->id,
        'bucket_date' => now()->startOfWeek()->addDay()->toDateString(),
        'bucket_type' => 'day',
        'token_total' => 35,
        'token_input' => 15,
        'token_output' => 20,
        'request_count' => 1,
        'error_count' => 0,
    ]);

    expect(fn () => app(EnforceTokenQuota::class)->handle($user, 10))
        ->toThrow(QuotaExceededException::class);
});

it('throws when weekly quota would be exceeded from weekly ledger bucket', function () {
    $user = User::factory()->create();

    QuotaPolicy::create([
        'user_id' => $user->id,
        'daily_token_limit' => 1000,
        'weekly_token_limit' => 100,
        'monthly_token_limit' => 10000,
        'effective_from' => now()->subDay(),
        'is_active' => true,
    ]);

    UsageLedger::create([
        'user_id' => $user->id,
        'bucket_date' => now()->startOfWeek()->toDateString(),
        'bucket_type' => 'week',
        'token_total' => 95,
        'token_input' => 40,
        'token_output' => 55,
        'request_count' => 2,
        'error_count' => 0,
    ]);

    expect(fn () => app(EnforceTokenQuota::class)->handle($user, 10))
        ->toThrow(QuotaExceededException::class);
});
