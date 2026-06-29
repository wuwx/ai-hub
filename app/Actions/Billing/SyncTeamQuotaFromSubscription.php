<?php

namespace App\Actions\Billing;

use App\Models\Team;
use App\Models\TeamBillingSubscription;
use App\Models\TeamQuotaPolicy;
use App\Models\TeamWallet;
use Carbon\CarbonInterface;

class SyncTeamQuotaFromSubscription
{
    public function handle(TeamBillingSubscription $subscription, ?CarbonInterface $at = null): TeamQuotaPolicy
    {
        $at ??= now();

        $planCode = $subscription->plan_code;
        $status = strtolower($subscription->status);

        if (! in_array($status, ['active', 'trialing'], true)) {
            $planCode = (string) config('services.billing.free_plan_code', 'free');
        }

        $plan = $this->resolvePlan($planCode);

        // Provision a post-paid wallet for teams on an active subscription so
        // the gateway's pre-flight balance check admits traffic. The wallet
        // balance goes negative as usage is debited and is settled via the
        // monthly invoice + Stripe checkout flow.
        if (in_array($status, ['active', 'trialing'], true)) {
            TeamWallet::query()->firstOrCreate(
                ['team_id' => $subscription->team_id],
                [
                    'balance_cents' => 0,
                    'credit_grant_cents' => 0,
                    'currency' => (string) config('services.billing.currency', 'USD'),
                    'is_postpaid' => true,
                ],
            );
        }

        return $this->upsertPolicy(
            team: $subscription->team,
            dailyTokenLimit: $plan['daily_token_limit'],
            weeklyTokenLimit: $plan['weekly_token_limit'],
            monthlyTokenLimit: $plan['monthly_token_limit'],
            at: $at,
        );
    }

    /**
     * @return array{daily_token_limit: ?int, weekly_token_limit: ?int, monthly_token_limit: ?int}
     */
    protected function resolvePlan(string $planCode): array
    {
        $plans = (array) config('services.billing.plans', []);

        $plan = $plans[$planCode] ?? $plans[(string) config('services.billing.free_plan_code', 'free')] ?? [];

        return [
            'daily_token_limit' => $this->nullableInt($plan['daily_token_limit'] ?? null),
            'weekly_token_limit' => $this->nullableInt($plan['weekly_token_limit'] ?? null),
            'monthly_token_limit' => $this->nullableInt($plan['monthly_token_limit'] ?? null),
        ];
    }

    protected function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return max(0, (int) $value);
    }

    protected function upsertPolicy(
        Team $team,
        ?int $dailyTokenLimit,
        ?int $weeklyTokenLimit,
        ?int $monthlyTokenLimit,
        CarbonInterface $at,
    ): TeamQuotaPolicy {
        $activePolicy = TeamQuotaPolicy::query()
            ->where('team_id', $team->id)
            ->where('is_active', true)
            ->orderByDesc('effective_from')
            ->first();

        if (
            $activePolicy
            && $activePolicy->daily_token_limit === $dailyTokenLimit
            && $activePolicy->weekly_token_limit === $weeklyTokenLimit
            && $activePolicy->monthly_token_limit === $monthlyTokenLimit
        ) {
            return $activePolicy;
        }

        TeamQuotaPolicy::query()
            ->where('team_id', $team->id)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'effective_to' => $at,
            ]);

        return TeamQuotaPolicy::create([
            'team_id' => $team->id,
            'daily_token_limit' => $dailyTokenLimit,
            'weekly_token_limit' => $weeklyTokenLimit,
            'monthly_token_limit' => $monthlyTokenLimit,
            'daily_alert_threshold' => 80,
            'monthly_alert_threshold' => 80,
            'effective_from' => $at,
            'effective_to' => null,
            'is_active' => true,
        ]);
    }
}
