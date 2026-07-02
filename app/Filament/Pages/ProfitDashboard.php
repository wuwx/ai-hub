<?php

namespace App\Filament\Pages;

use App\Actions\Billing\ResolveModelPricing;
use App\Models\LlmModel;
use App\Models\Plan;
use App\Models\QuotaPolicy;
use App\Models\RequestLog;
use App\Services\PlanService;
use BackedEnum;
use Carbon\CarbonInterface;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use UnitEnum;

class ProfitDashboard extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?string $title = 'Profit Dashboard';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.profit-dashboard';

    public static function canAccess(): bool
    {
        // Only admin panel users (Filament admin) can view the profit dashboard.
        // This is the admin panel — it shows cross-user financials.
        return true;
    }

    /**
     * @return array{totals: array<string, mixed>, byModel: array<int, array<string, mixed>>, byUser: array<int, array<string, mixed>>}
     */
    protected function getViewData(): array
    {
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        return [
            'totals' => $this->getTotals($monthStart, $monthEnd),
            'byModel' => $this->getByModel($monthStart, $monthEnd),
            'byUser' => $this->getByUser($monthStart, $monthEnd),
        ];
    }

    /**
     * Revenue is the flat monthly subscription fee for each user's active
     * plan (real MRR, driven by Stripe subscriptions synced into
     * QuotaPolicy). Cost is the upstream LLM spend incurred this month.
     * There is no more per-request metered billing, so profit here reflects
     * subscription income against infrastructure cost, not per-token margin.
     *
     * @return array{revenue_cents: int, cost_cents: int, profit_cents: int, margin_pct: float, active_paid_teams: int, free_teams: int}
     */
    protected function getTotals(
        CarbonInterface $monthStart,
        CarbonInterface $monthEnd,
    ): array {
        $freeCode = app(PlanService::class)->freePlanCode();
        $planCounts = $this->activePlanCounts();

        $revenueCents = $this->monthlyRecurringRevenueCents($planCounts);
        $costCents = $this->usageCostCents($monthStart, $monthEnd);

        $profitCents = $revenueCents - $costCents;
        $marginPct =
            $revenueCents > 0
                ? round(($profitCents / $revenueCents) * 100, 1)
                : 0.0;

        return [
            'revenue_cents' => $revenueCents,
            'cost_cents' => $costCents,
            'profit_cents' => $profitCents,
            'margin_pct' => $marginPct,
            'active_paid_users' => (int) $planCounts->except($freeCode)->sum(),
            'free_users' => (int) $planCounts->get($freeCode, 0),
        ];
    }

    /**
     * @return Collection<string, int> user count keyed by plan code
     */
    protected function activePlanCounts(): Collection
    {
        return QuotaPolicy::query()
            ->where('is_active', true)
            ->selectRaw('plan_code, COUNT(*) as user_count')
            ->groupBy('plan_code')
            ->pluck('user_count', 'plan_code');
    }

    /**
     * @param  Collection<string, int>  $planCounts
     */
    protected function monthlyRecurringRevenueCents(Collection $planCounts): int
    {
        $plans = Plan::query()->active()->get()->keyBy('code');
        $revenueCents = 0;

        foreach ($planCounts as $planCode => $userCount) {
            $revenueCents +=
                (int) ($plans[$planCode]->monthly_price_cents ?? 0) *
                $userCount;
        }

        return $revenueCents;
    }

    protected function usageCostCents(
        CarbonInterface $monthStart,
        CarbonInterface $monthEnd,
    ): int {
        return collect($this->getByModel($monthStart, $monthEnd))->sum(
            'cost_cents',
        );
    }

    /**
     * @return array<int, array{model: string, request_count: int, cost_cents: int}>
     */
    protected function getByModel(
        CarbonInterface $monthStart,
        CarbonInterface $monthEnd,
    ): array {
        $resolver = app(ResolveModelPricing::class);

        $rows = RequestLog::query()
            ->whereBetween('requested_at', [$monthStart, $monthEnd])
            ->selectRaw(
                'llm_model_id, SUM(token_input) as token_input, SUM(token_output) as token_output, COUNT(*) as request_count',
            )
            ->groupBy('llm_model_id')
            ->get();

        $models = LlmModel::query()
            ->whereIn('id', $rows->pluck('llm_model_id')->filter()->values())
            ->get()
            ->keyBy('id');

        return $rows
            ->map(function ($row) use ($models, $resolver) {
                $model = $row->llm_model_id
                    ? $models->get((int) $row->llm_model_id)
                    : null;

                return [
                    'model' => $model?->external_model_id ?? 'unknown',
                    'request_count' => (int) $row->request_count,
                    'cost_cents' => $model
                        ? $resolver->costCents(
                            $model,
                            (int) $row->token_input,
                            (int) $row->token_output,
                        )
                        : 0,
                ];
            })
            ->sortByDesc('cost_cents')
            ->values()
            ->toArray();
    }

    /**
     * @return array<int, array{user_name: string, plan_code: string, request_count: int, revenue_cents: int, cost_cents: int, profit_cents: int}>
     */
    protected function getByUser(
        CarbonInterface $monthStart,
        CarbonInterface $monthEnd,
    ): array {
        $plans = Plan::query()->active()->get()->keyBy('code');
        $freeCode = app(PlanService::class)->freePlanCode();
        $resolver = app(ResolveModelPricing::class);

        $policies = QuotaPolicy::query()
            ->where('is_active', true)
            ->with('user')
            ->get()
            ->keyBy('user_id');

        $usageRows = RequestLog::query()
            ->whereBetween('requested_at', [$monthStart, $monthEnd])
            ->selectRaw(
                'user_id, llm_model_id, SUM(token_input) as token_input, SUM(token_output) as token_output, COUNT(*) as request_count',
            )
            ->groupBy('user_id', 'llm_model_id')
            ->get()
            ->groupBy('user_id');

        $models = LlmModel::query()->get()->keyBy('id');

        $byUser = [];

        foreach ($usageRows as $userId => $rows) {
            $policy = $policies->get($userId);
            $planCode = $policy->plan_code ?? $freeCode;

            $costCents = 0;
            $requestCount = 0;

            foreach ($rows as $row) {
                $model = $row->llm_model_id
                    ? $models->get((int) $row->llm_model_id)
                    : null;
                $costCents += $model
                    ? $resolver->costCents(
                        $model,
                        (int) $row->token_input,
                        (int) $row->token_output,
                    )
                    : 0;
                $requestCount += (int) $row->request_count;
            }

            $revenueCents =
                (int) ($plans[$planCode]->monthly_price_cents ?? 0);

            $byUser[] = [
                'user_name' => $policy?->user?->name ?? "User #{$userId}",
                'plan_code' => $planCode,
                'request_count' => $requestCount,
                'revenue_cents' => $revenueCents,
                'cost_cents' => $costCents,
                'profit_cents' => $revenueCents - $costCents,
            ];
        }

        return collect($byUser)
            ->sortByDesc('profit_cents')
            ->values()
            ->toArray();
    }
}
