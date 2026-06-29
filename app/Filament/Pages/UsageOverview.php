<?php

namespace App\Filament\Pages;

use App\Actions\Usage\GetTeamUsageSnapshot;
use App\Enums\TeamPermission;
use App\Filament\Widgets\UsageOverviewStats;
use App\Filament\Widgets\UsageRequestsChart;
use App\Models\User;
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
        /** @var User|null $user */
        $user = Auth::user();
        $team = $user?->currentTeam;

        if (! $user || ! $team) {
            return false;
        }

        return $user->hasTeamPermission($team, TeamPermission::ViewUsage);
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
        $team = Auth::user()?->currentTeam;

        if (! $team) {
            return ['topModels' => []];
        }

        $snapshot = app(GetTeamUsageSnapshot::class)->handle($team);

        return [
            'topModels' => $snapshot['top_models'],
        ];
    }
}
