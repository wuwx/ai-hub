<?php

namespace App\Filament\Pages;

use App\Models\TeamWalletTransaction;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
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
        // This is separate from team-level permissions — it shows cross-team financials.
        return true;
    }

    /**
     * @return array{totals: array<string, mixed>, byModel: array<int, array<string, mixed>>, byTeam: array<int, array<string, mixed>>}
     */
    protected function getViewData(): array
    {
        return [
            'totals' => $this->getTotals(),
            'byModel' => $this->getByModel(),
            'byTeam' => $this->getByTeam(),
        ];
    }

    /**
     * @return array{revenue_cents: int, cost_cents: int, profit_cents: int, margin_pct: float, today_revenue_cents: int, month_revenue_cents: int}
     */
    protected function getTotals(): array
    {
        $row = DB::table('team_wallet_transactions')
            ->selectRaw('
                COALESCE(SUM(CASE WHEN type = "debit" THEN -amount_cents ELSE 0 END), 0) as revenue_cents,
                COALESCE(SUM(CASE WHEN type = "debit" THEN COALESCE(JSON_UNEXTRACT(metadata, "$.cost_cents"), 0) ELSE 0 END), 0) as cost_cents
            ')
            ->first();

        // SQLite doesn't support JSON_UNEXTRACT in tests; compute cost from metadata via PHP
        $revenueCents = (int) TeamWalletTransaction::where('type', 'debit')->sum(DB::raw('ABS(amount_cents)'));

        $costCents = 0;
        TeamWalletTransaction::where('type', 'debit')->chunk(500, function ($transactions) use (&$costCents) {
            foreach ($transactions as $tx) {
                $metadata = is_array($tx->metadata) ? $tx->metadata : json_decode($tx->metadata ?? '{}', true);
                $costCents += (int) ($metadata['cost_cents'] ?? 0);
            }
        });

        $profitCents = $revenueCents - $costCents;
        $marginPct = $revenueCents > 0 ? round(($profitCents / $revenueCents) * 100, 1) : 0.0;

        $todayRevenue = (int) TeamWalletTransaction::where('type', 'debit')
            ->whereDate('created_at', today())
            ->sum(DB::raw('ABS(amount_cents)'));

        $monthRevenue = (int) TeamWalletTransaction::where('type', 'debit')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum(DB::raw('ABS(amount_cents)'));

        return [
            'revenue_cents' => $revenueCents,
            'cost_cents' => $costCents,
            'profit_cents' => $profitCents,
            'margin_pct' => $marginPct,
            'today_revenue_cents' => $todayRevenue,
            'month_revenue_cents' => $monthRevenue,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function getByModel(): array
    {
        $transactions = TeamWalletTransaction::where('type', 'debit')->get();

        $byModel = [];

        foreach ($transactions as $tx) {
            $metadata = is_array($tx->metadata) ? $tx->metadata : json_decode($tx->metadata ?? '{}', true);
            $modelId = $metadata['model_external_id'] ?? 'unknown';

            if (! isset($byModel[$modelId])) {
                $byModel[$modelId] = [
                    'model' => $modelId,
                    'revenue_cents' => 0,
                    'cost_cents' => 0,
                    'request_count' => 0,
                ];
            }

            $byModel[$modelId]['revenue_cents'] += abs($tx->amount_cents);
            $byModel[$modelId]['cost_cents'] += (int) ($metadata['cost_cents'] ?? 0);
            $byModel[$modelId]['request_count']++;
        }

        // Calculate profit and sort by profit descending
        return collect($byModel)
            ->map(fn ($row) => array_merge($row, [
                'profit_cents' => $row['revenue_cents'] - $row['cost_cents'],
                'margin_pct' => $row['revenue_cents'] > 0
                    ? round((($row['revenue_cents'] - $row['cost_cents']) / $row['revenue_cents']) * 100, 1)
                    : 0.0,
            ]))
            ->sortByDesc('profit_cents')
            ->values()
            ->toArray();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function getByTeam(): array
    {
        $transactions = TeamWalletTransaction::where('type', 'debit')
            ->with('team')
            ->get()
            ->groupBy('team_id');

        $byTeam = [];

        foreach ($transactions as $teamId => $txs) {
            $revenue = $txs->sum(fn ($tx) => abs($tx->amount_cents));
            $cost = $txs->sum(fn ($tx) => (int) (is_array($tx->metadata) ? ($tx->metadata['cost_cents'] ?? 0) : 0));

            $byTeam[] = [
                'team_name' => $txs->first()?->team?->name ?? "Team #{$teamId}",
                'revenue_cents' => $revenue,
                'cost_cents' => $cost,
                'profit_cents' => $revenue - $cost,
                'request_count' => $txs->count(),
            ];
        }

        return collect($byTeam)
            ->sortByDesc('profit_cents')
            ->values()
            ->toArray();
    }
}
