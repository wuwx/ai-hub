<?php

namespace App\Filament\Pages;

use App\Actions\Usage\GetUsageSnapshot;
use App\Filament\Widgets\UsageOverviewStats;
use App\Filament\Widgets\UsageRequestsChart;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class UsageOverview extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Usage & Limits';

    protected static ?string $title = 'Usage Overview';

    protected string $view = 'filament.pages.usage-overview';

    public static function canAccess(): bool
    {
        return Auth::check();
    }

    protected function getHeaderWidgets(): array
    {
        return [
            UsageOverviewStats::class,
            UsageRequestsChart::class,
        ];
    }

    /**
     * @return array{topModels: array<int, array{name: string, tokens: int}>}
     */
    protected function getViewData(): array
    {
        $user = Auth::user();

        if (! $user) {
            return ['topModels' => []];
        }

        $snapshot = app(GetUsageSnapshot::class)->handle($user);

        return [
            'topModels' => $snapshot['top_models'],
        ];
    }
}
