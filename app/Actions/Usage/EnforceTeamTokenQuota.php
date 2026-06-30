<?php

namespace App\Actions\Usage;

use App\Exceptions\QuotaExceededException;
use App\Models\Team;
use App\Models\TeamQuotaPolicy;
use App\Models\TeamWalletTransaction;
use App\Models\UsageLedger;
use Carbon\CarbonInterface;

class EnforceTeamTokenQuota
{
    public function handle(Team $team, int $requestedTokens, ?CarbonInterface $at = null): void
    {
        if ($requestedTokens <= 0) {
            return;
        }

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

        $this->enforceDailyLimit($team, $policy, $requestedTokens, $at);
        $this->enforceWeeklyLimit($team, $policy, $requestedTokens, $at);
        $this->enforceMonthlyLimit($team, $policy, $requestedTokens, $at);
        $this->enforceDailySpendLimit($team, $policy, $at);
    }

    protected function enforceDailyLimit(Team $team, TeamQuotaPolicy $policy, int $requestedTokens, CarbonInterface $at): void
    {
        if (! $policy->daily_token_limit) {
            return;
        }

        $usedToday = (int) UsageLedger::query()
            ->where('team_id', $team->id)
            ->where('bucket_type', 'day')
            ->whereDate('bucket_date', $at->toDateString())
            ->sum('token_total');

        if ($usedToday + $requestedTokens > $policy->daily_token_limit) {
            throw new QuotaExceededException('daily', $policy->daily_token_limit, $usedToday, $requestedTokens);
        }
    }

    protected function enforceWeeklyLimit(Team $team, TeamQuotaPolicy $policy, int $requestedTokens, CarbonInterface $at): void
    {
        if (! $policy->weekly_token_limit) {
            return;
        }

        $startOfWeek = $at->copy()->startOfWeek();
        $weeklyUsageQuery = UsageLedger::query()
            ->where('team_id', $team->id)
            ->where('bucket_type', 'week')
            ->whereDate('bucket_date', $startOfWeek->toDateString());

        $hasWeeklyUsageLedger = (clone $weeklyUsageQuery)->exists();

        $usedThisWeek = $hasWeeklyUsageLedger
            ? (int) $weeklyUsageQuery->sum('token_total')
            : $this->sumWeeklyUsageFromDailyBuckets($team, $at);

        if ($usedThisWeek + $requestedTokens > $policy->weekly_token_limit) {
            throw new QuotaExceededException('weekly', $policy->weekly_token_limit, $usedThisWeek, $requestedTokens);
        }
    }

    protected function sumWeeklyUsageFromDailyBuckets(Team $team, CarbonInterface $at): int
    {
        $startOfWeek = $at->copy()->startOfWeek();
        $endOfWeek = $at->copy()->endOfWeek();

        return (int) UsageLedger::query()
            ->where('team_id', $team->id)
            ->where('bucket_type', 'day')
            ->whereDate('bucket_date', '>=', $startOfWeek->toDateString())
            ->whereDate('bucket_date', '<=', $endOfWeek->toDateString())
            ->sum('token_total');
    }

    protected function enforceMonthlyLimit(Team $team, TeamQuotaPolicy $policy, int $requestedTokens, CarbonInterface $at): void
    {
        if (! $policy->monthly_token_limit) {
            return;
        }

        $monthBucketDate = $at->copy()->startOfMonth()->toDateString();

        $usedThisMonth = (int) UsageLedger::query()
            ->where('team_id', $team->id)
            ->where('bucket_type', 'month')
            ->whereDate('bucket_date', $monthBucketDate)
            ->sum('token_total');

        if ($usedThisMonth + $requestedTokens > $policy->monthly_token_limit) {
            throw new QuotaExceededException('monthly', $policy->monthly_token_limit, $usedThisMonth, $requestedTokens);
        }
    }

    /**
     * Enforce a daily spend cap (in cents) based on wallet debits recorded today.
     * This is independent of token limits — it prevents runaway costs when
     * expensive models are used heavily.
     */
    protected function enforceDailySpendLimit(Team $team, TeamQuotaPolicy $policy, CarbonInterface $at): void
    {
        if (! $policy->daily_spend_limit_cents || $policy->daily_spend_limit_cents <= 0) {
            return;
        }

        $spentToday = (int) TeamWalletTransaction::query()
            ->where('team_id', $team->id)
            ->where('type', 'debit')
            ->whereDate('created_at', $at->toDateString())
            ->sum('amount_cents');

        // amount_cents is stored as a positive number for debits
        if ($spentToday >= $policy->daily_spend_limit_cents) {
            throw new QuotaExceededException(
                'daily_spend',
                $policy->daily_spend_limit_cents,
                $spentToday,
                0,
            );
        }
    }
}
