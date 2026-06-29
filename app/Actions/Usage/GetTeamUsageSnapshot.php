<?php

namespace App\Actions\Usage;

use App\Models\Team;
use App\Models\TeamQuotaPolicy;
use App\Models\UsageLedger;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class GetTeamUsageSnapshot
{
    /**
     * @return array{
     *     today_tokens: int,
     *     today_requests: int,
     *     month_tokens: int,
     *     month_requests: int,
     *     month_errors: int,
     *     month_error_rate: float,
     *     daily_limit: int|null,
     *     monthly_limit: int|null,
     *     daily_remaining: int|null,
     *     monthly_remaining: int|null,
     *     top_models: array<int, array{name: string, tokens: int}>,
     *     requests_chart: array{labels: array<int, string>, values: array<int, int>}
     * }
     */
    public function handle(Team $team, ?CarbonInterface $at = null): array
    {
        $at ??= now();

        $today = $at->toDateString();
        $monthStart = $at->copy()->startOfMonth()->toDateString();

        $todayUsage = UsageLedger::query()
            ->where('team_id', $team->id)
            ->where('bucket_type', 'day')
            ->whereDate('bucket_date', $today)
            ->selectRaw('COALESCE(SUM(token_total), 0) as token_total, COALESCE(SUM(request_count), 0) as request_count')
            ->first();

        $monthUsage = UsageLedger::query()
            ->where('team_id', $team->id)
            ->where('bucket_type', 'month')
            ->whereDate('bucket_date', $monthStart)
            ->selectRaw('COALESCE(SUM(token_total), 0) as token_total, COALESCE(SUM(request_count), 0) as request_count, COALESCE(SUM(error_count), 0) as error_count')
            ->first();

        $activePolicy = TeamQuotaPolicy::query()
            ->where('team_id', $team->id)
            ->where('is_active', true)
            ->where('effective_from', '<=', $at)
            ->where(function ($query) use ($at) {
                $query->whereNull('effective_to')->orWhere('effective_to', '>', $at);
            })
            ->orderByDesc('effective_from')
            ->first();

        $todayTokens = (int) ($todayUsage?->token_total ?? 0);
        $todayRequests = (int) ($todayUsage?->request_count ?? 0);
        $monthTokens = (int) ($monthUsage?->token_total ?? 0);
        $monthRequests = (int) ($monthUsage?->request_count ?? 0);
        $monthErrors = (int) ($monthUsage?->error_count ?? 0);

        $dailyLimit = $activePolicy?->daily_token_limit;
        $monthlyLimit = $activePolicy?->monthly_token_limit;

        $dailyRemaining = $dailyLimit !== null ? max(0, $dailyLimit - $todayTokens) : null;
        $monthlyRemaining = $monthlyLimit !== null ? max(0, $monthlyLimit - $monthTokens) : null;

        $monthErrorRate = $monthRequests > 0
            ? round(($monthErrors / $monthRequests) * 100, 2)
            : 0.0;

        return [
            'today_tokens' => $todayTokens,
            'today_requests' => $todayRequests,
            'month_tokens' => $monthTokens,
            'month_requests' => $monthRequests,
            'month_errors' => $monthErrors,
            'month_error_rate' => $monthErrorRate,
            'daily_limit' => $dailyLimit,
            'monthly_limit' => $monthlyLimit,
            'daily_remaining' => $dailyRemaining,
            'monthly_remaining' => $monthlyRemaining,
            'top_models' => $this->getTopModels($team, $monthStart),
            'requests_chart' => $this->getRequestsChart($team, $at),
        ];
    }

    /**
     * @return array<int, array{name: string, tokens: int}>
     */
    protected function getTopModels(Team $team, string $monthStart): array
    {
        return UsageLedger::query()
            ->leftJoin('llm_models', 'llm_models.id', '=', 'usage_ledgers.llm_model_id')
            ->where('usage_ledgers.team_id', $team->id)
            ->where('usage_ledgers.bucket_type', 'month')
            ->whereDate('usage_ledgers.bucket_date', $monthStart)
            ->whereNotNull('usage_ledgers.llm_model_id')
            ->groupBy('usage_ledgers.llm_model_id', 'llm_models.name')
            ->orderByDesc('tokens')
            ->limit(5)
            ->get([
                'llm_models.name as model_name',
                \DB::raw('COALESCE(SUM(usage_ledgers.token_total), 0) as tokens'),
            ])
            ->map(fn ($row) => [
                'name' => (string) ($row->model_name ?: 'Unknown model'),
                'tokens' => (int) $row->tokens,
            ])
            ->values()
            ->toArray();
    }

    /**
     * @return array{labels: array<int, string>, values: array<int, int>}
     */
    protected function getRequestsChart(Team $team, CarbonInterface $at): array
    {
        $start = $at->copy()->subDays(13)->startOfDay();

        /** @var Collection<string, int> $rows */
        $rows = UsageLedger::query()
            ->where('team_id', $team->id)
            ->where('bucket_type', 'day')
            ->whereDate('bucket_date', '>=', $start->toDateString())
            ->groupBy('bucket_date')
            ->orderBy('bucket_date')
            ->pluck('request_count', 'bucket_date');

        $labels = [];
        $values = [];

        for ($date = $start->copy(); $date->lte($at); $date->addDay()) {
            $bucketDate = $date->toDateString();
            $labels[] = $date->format('m-d');
            $values[] = (int) ($rows[$bucketDate] ?? 0);
        }

        return ['labels' => $labels, 'values' => $values];
    }
}
