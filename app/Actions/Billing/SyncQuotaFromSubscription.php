<?php

namespace App\Actions\Billing;

use App\Models\Plan;
use App\Models\QuotaPolicy;
use App\Models\User;
use Carbon\CarbonInterface;

class SyncQuotaFromSubscription
{
    public function handle(
        User $user,
        string $planCode,
        string $status = 'active',
        ?CarbonInterface $at = null,
    ): QuotaPolicy {
        $at ??= now();

        $status = strtolower($status);

        if (! in_array($status, ['active', 'trialing'], true)) {
            $planCode = (string) config(
                'services.billing.free_plan_code',
                'free',
            );
        }

        $plan = $this->resolvePlan($planCode);

        return $this->upsertPolicy(
            user: $user,
            planCode: $planCode,
            dailyTokenLimit: $plan->daily_token_limit,
            weeklyTokenLimit: $plan->weekly_token_limit,
            monthlyTokenLimit: $plan->monthly_token_limit,
            at: $at,
        );
    }

    protected function resolvePlan(string $planCode): Plan
    {
        $plan = Plan::query()->byCode($planCode)->first();

        if ($plan) {
            return $plan;
        }

        $freeCode = (string) config('services.billing.free_plan_code', 'free');

        return Plan::query()->byCode($freeCode)->first() ?? new Plan;
    }

    protected function upsertPolicy(
        User $user,
        string $planCode,
        ?int $dailyTokenLimit,
        ?int $weeklyTokenLimit,
        ?int $monthlyTokenLimit,
        CarbonInterface $at,
    ): QuotaPolicy {
        $activePolicy = QuotaPolicy::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->orderByDesc('effective_from')
            ->first();

        if (
            $activePolicy &&
            $activePolicy->plan_code === $planCode &&
            $activePolicy->daily_token_limit === $dailyTokenLimit &&
            $activePolicy->weekly_token_limit === $weeklyTokenLimit &&
            $activePolicy->monthly_token_limit === $monthlyTokenLimit
        ) {
            return $activePolicy;
        }

        QuotaPolicy::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'effective_to' => $at,
            ]);

        return QuotaPolicy::create([
            'user_id' => $user->id,
            'plan_code' => $planCode,
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
