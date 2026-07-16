<?php

namespace App\Actions\Usage;

use App\Models\QuotaPolicy;
use App\Models\UsageLedger;
use App\Models\User;
use App\Notifications\QuotaThresholdExceeded;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

class CheckQuotaThresholds
{
    /**
     * Inspect the user's current usage against their active quota policy and
     * notify them when a configured alert threshold is crossed.
     *
     * Each threshold fires at most once per billing period via a cache lock,
     * so repeat requests within the same day/month won't spam the inbox.
     */
    public function handle(User $user, ?CarbonInterface $at = null): void
    {
        $at ??= now();

        $policy = QuotaPolicy::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->where('effective_from', '<=', $at)
            ->where(function ($query) use ($at) {
                $query->whereNull('effective_to')->orWhere('effective_to', '>', $at);
            })
            ->orderByDesc('effective_from')
            ->first();

        if (! $policy) {
            return;
        }

        $this->checkPeriod($user, $policy, 'daily', $policy->daily_token_limit, $policy->daily_alert_threshold, $at);
        $this->checkPeriod($user, $policy, 'monthly', $policy->monthly_token_limit, $policy->monthly_alert_threshold, $at);
    }

    protected function checkPeriod(
        User $user,
        QuotaPolicy $policy,
        string $period,
        ?int $limit,
        ?int $threshold,
        CarbonInterface $at,
    ): void {
        if (! $limit || $limit <= 0 || ! $threshold || $threshold <= 0) {
            return;
        }

        $bucketType = $period === 'daily' ? 'day' : 'month';
        $bucketDate = $period === 'daily'
            ? $at->toDateString()
            : $at->copy()->startOfMonth()->toDateString();

        $used = (int) UsageLedger::query()
            ->where('user_id', $user->id)
            ->where('bucket_type', $bucketType)
            ->whereDate('bucket_date', $bucketDate)
            ->sum('token_total');

        $percentage = ($used / $limit) * 100;

        if ($percentage < $threshold) {
            return;
        }

        // Dedupe: alert once per threshold per period. The cache entry lives
        // until the end of the current period so it resets naturally.
        $cacheKey = sprintf('gateway:quota-alert:%d:%s:%d', $user->id, $period, $threshold);
        $ttl = $period === 'daily'
            ? $at->copy()->endOfDay()
            : $at->copy()->endOfMonth();

        if (! Cache::add($cacheKey, true, $ttl)) {
            return;
        }

        $user->notify(new QuotaThresholdExceeded(
            period: $period,
            used: $used,
            limit: $limit,
            percentage: $percentage,
            userName: $user->name,
        ));
    }
}
