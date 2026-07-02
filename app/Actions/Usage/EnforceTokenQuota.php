<?php

namespace App\Actions\Usage;

use App\Exceptions\QuotaExceededException;
use App\Models\QuotaPolicy;
use App\Models\UsageLedger;
use App\Models\User;
use Carbon\CarbonInterface;

class EnforceTokenQuota
{
    public function handle(
        User $user,
        int $requestedTokens,
        ?CarbonInterface $at = null,
    ): void {
        if ($requestedTokens <= 0) {
            return;
        }

        $at ??= now();

        $policy = QuotaPolicy::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->where('effective_from', '<=', $at)
            ->where(function ($query) use ($at) {
                $query
                    ->whereNull('effective_to')
                    ->orWhere('effective_to', '>', $at);
            })
            ->orderByDesc('effective_from')
            ->first();

        if (! $policy) {
            return;
        }

        $this->enforceDailyLimit($user, $policy, $requestedTokens, $at);
        $this->enforceWeeklyLimit($user, $policy, $requestedTokens, $at);
        $this->enforceMonthlyLimit($user, $policy, $requestedTokens, $at);
    }

    protected function enforceDailyLimit(
        User $user,
        QuotaPolicy $policy,
        int $requestedTokens,
        CarbonInterface $at,
    ): void {
        if (! $policy->daily_token_limit) {
            return;
        }

        $usedToday = (int) UsageLedger::query()
            ->where('user_id', $user->id)
            ->where('bucket_type', 'day')
            ->whereDate('bucket_date', $at->toDateString())
            ->sum('token_total');

        if ($usedToday + $requestedTokens > $policy->daily_token_limit) {
            throw new QuotaExceededException(
                'daily',
                $policy->daily_token_limit,
                $usedToday,
                $requestedTokens,
            );
        }
    }

    protected function enforceWeeklyLimit(
        User $user,
        QuotaPolicy $policy,
        int $requestedTokens,
        CarbonInterface $at,
    ): void {
        if (! $policy->weekly_token_limit) {
            return;
        }

        $startOfWeek = $at->copy()->startOfWeek();
        $weeklyUsageQuery = UsageLedger::query()
            ->where('user_id', $user->id)
            ->where('bucket_type', 'week')
            ->whereDate('bucket_date', $startOfWeek->toDateString());

        $hasWeeklyUsageLedger = (clone $weeklyUsageQuery)->exists();

        $usedThisWeek = $hasWeeklyUsageLedger
            ? (int) $weeklyUsageQuery->sum('token_total')
            : $this->sumWeeklyUsageFromDailyBuckets($user, $at);

        if ($usedThisWeek + $requestedTokens > $policy->weekly_token_limit) {
            throw new QuotaExceededException(
                'weekly',
                $policy->weekly_token_limit,
                $usedThisWeek,
                $requestedTokens,
            );
        }
    }

    protected function sumWeeklyUsageFromDailyBuckets(
        User $user,
        CarbonInterface $at,
    ): int {
        $startOfWeek = $at->copy()->startOfWeek();
        $endOfWeek = $at->copy()->endOfWeek();

        return (int) UsageLedger::query()
            ->where('user_id', $user->id)
            ->where('bucket_type', 'day')
            ->whereDate('bucket_date', '>=', $startOfWeek->toDateString())
            ->whereDate('bucket_date', '<=', $endOfWeek->toDateString())
            ->sum('token_total');
    }

    protected function enforceMonthlyLimit(
        User $user,
        QuotaPolicy $policy,
        int $requestedTokens,
        CarbonInterface $at,
    ): void {
        if (! $policy->monthly_token_limit) {
            return;
        }

        $monthBucketDate = $at->copy()->startOfMonth()->toDateString();

        $usedThisMonth = (int) UsageLedger::query()
            ->where('user_id', $user->id)
            ->where('bucket_type', 'month')
            ->whereDate('bucket_date', $monthBucketDate)
            ->sum('token_total');

        if ($usedThisMonth + $requestedTokens > $policy->monthly_token_limit) {
            throw new QuotaExceededException(
                'monthly',
                $policy->monthly_token_limit,
                $usedThisMonth,
                $requestedTokens,
            );
        }
    }
}
