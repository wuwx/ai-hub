<?php

namespace App\Actions\Usage;

use App\Actions\Webhooks\DispatchWebhookEvent;
use App\Models\Team;
use App\Models\TeamQuotaPolicy;
use App\Models\UsageLedger;
use App\Notifications\Teams\QuotaThresholdExceeded;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

class CheckQuotaThresholds
{
    public function __construct(
        private readonly DispatchWebhookEvent $dispatchWebhookEvent,
    ) {
        //
    }

    /**
     * Inspect the team's current usage against its active quota policy and
     * notify the team owner when a configured alert threshold is crossed.
     *
     * Each threshold fires at most once per billing period via a cache lock,
     * so repeat requests within the same day/month won't spam the inbox.
     */
    public function handle(Team $team, ?CarbonInterface $at = null): void
    {
        $at ??= now();

        $policy = TeamQuotaPolicy::query()
            ->where('team_id', $team->id)
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

        $this->checkPeriod($team, $policy, 'daily', $policy->daily_token_limit, $policy->daily_alert_threshold, $at);
        $this->checkPeriod($team, $policy, 'monthly', $policy->monthly_token_limit, $policy->monthly_alert_threshold, $at);
    }

    protected function checkPeriod(
        Team $team,
        TeamQuotaPolicy $policy,
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
            ->where('team_id', $team->id)
            ->where('bucket_type', $bucketType)
            ->whereDate('bucket_date', $bucketDate)
            ->sum('token_total');

        $percentage = ($used / $limit) * 100;

        if ($percentage < $threshold) {
            return;
        }

        // Dedupe: alert once per threshold per period. The cache entry lives
        // until the end of the current period so it resets naturally.
        $cacheKey = sprintf('gateway:quota-alert:%d:%s:%d', $team->id, $period, $threshold);
        $ttl = $period === 'daily'
            ? $at->copy()->endOfDay()
            : $at->copy()->endOfMonth();

        if (! Cache::add($cacheKey, true, $ttl)) {
            return;
        }

        $owner = $team->owner();

        if ($owner) {
            $owner->notify(new QuotaThresholdExceeded(
                period: $period,
                used: $used,
                limit: $limit,
                percentage: $percentage,
                teamName: $team->name,
            ));
        }

        $this->dispatchWebhookEvent->handle($team, 'quota.threshold_exceeded', [
            'period' => $period,
            'used' => $used,
            'limit' => $limit,
            'percentage' => $percentage,
        ]);
    }
}
