<?php

namespace App\Actions\Usage;

use App\Models\User;
use App\Notifications\QuotaThresholdExceeded;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

class CheckQuotaThresholds
{
    /**
     * Inspect the user's current usage against their active quota plan and
     * notify them when a configured alert threshold is crossed.
     *
     * Each threshold fires at most once per billing period via a cache lock,
     * so repeat requests within the same day/month won't spam the inbox.
     */
    public function handle(User $user, ?CarbonInterface $at = null): void
    {
        $at ??= now();

        $this->checkPeriod($user, 'daily', 'daily-tokens', $at);
        $this->checkPeriod($user, 'monthly', 'monthly-tokens', $at);
    }

    protected function checkPeriod(
        User $user,
        string $period,
        string $slug,
        CarbonInterface $at,
    ): void {
        if (! $user->hasFeature($slug) || $user->isUnlimitedUsage($slug)) {
            return;
        }

        $info = $user->featureInfo($slug);
        $limit = (int) $info->limit;
        $used = (int) $info->used;

        if ($limit <= 0) {
            return;
        }

        $threshold = (int) config('services.billing.alert_threshold', 80);

        if ($threshold <= 0) {
            return;
        }

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
