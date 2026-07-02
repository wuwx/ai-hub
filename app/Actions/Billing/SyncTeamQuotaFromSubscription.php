<?php

namespace App\Actions\Billing;

use App\Models\Team;
use App\Models\TeamQuotaPolicy;
use Carbon\CarbonInterface;

class SyncTeamQuotaFromSubscription
{
    public function handle(
        Team $team,
        string $planCode,
        string $status = 'active',
        ?CarbonInterface $at = null,
    ): TeamQuotaPolicy {
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
            team: $team,
            planCode: $planCode,
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

        $plan =
            $plans[$planCode] ??
            ($plans[
                (string) config('services.billing.free_plan_code', 'free')
            ] ??
                []);

        return [
            'daily_token_limit' => $this->nullableInt(
                $plan['daily_token_limit'] ?? null,
            ),
            'weekly_token_limit' => $this->nullableInt(
                $plan['weekly_token_limit'] ?? null,
            ),
            'monthly_token_limit' => $this->nullableInt(
                $plan['monthly_token_limit'] ?? null,
            ),
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
        string $planCode,
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
            $activePolicy &&
            $activePolicy->plan_code === $planCode &&
            $activePolicy->daily_token_limit === $dailyTokenLimit &&
            $activePolicy->weekly_token_limit === $weeklyTokenLimit &&
            $activePolicy->monthly_token_limit === $monthlyTokenLimit
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
