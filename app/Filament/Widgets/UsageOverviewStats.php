<?php

namespace App\Filament\Widgets;

use App\Actions\Usage\GetTeamUsageSnapshot;
use App\Enums\TeamPermission;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class UsageOverviewStats extends StatsOverviewWidget
{
    public static function canView(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();
        $team = $user?->currentTeam;

        if (! $user || ! $team) {
            return false;
        }

        return $user->hasTeamPermission($team, TeamPermission::ViewUsage);
    }

    protected function getStats(): array
    {
        $team = Auth::user()?->currentTeam;

        if (! $team) {
            return [];
        }

        $snapshot = app(GetTeamUsageSnapshot::class)->handle($team);

        $dailyLimitText = $snapshot['daily_limit'] !== null
            ? number_format($snapshot['daily_limit']).' limit'
            : 'No daily limit';

        $monthlyLimitText = $snapshot['monthly_limit'] !== null
            ? number_format($snapshot['monthly_limit']).' limit'
            : 'No monthly limit';

        return [
            Stat::make('Today Tokens', number_format($snapshot['today_tokens']))
                ->description($dailyLimitText)
                ->descriptionIcon('heroicon-o-bolt'),
            Stat::make('Month Tokens', number_format($snapshot['month_tokens']))
                ->description($monthlyLimitText)
                ->descriptionIcon('heroicon-o-calendar-days'),
            Stat::make('Month Requests', number_format($snapshot['month_requests']))
                ->description($snapshot['month_error_rate'].'% error rate')
                ->descriptionIcon('heroicon-o-exclamation-circle'),
            Stat::make('Remaining Quota', $this->formatRemainingQuota($snapshot['daily_remaining'], $snapshot['monthly_remaining']))
                ->description('day / month'),
        ];
    }

    protected function formatRemainingQuota(?int $dailyRemaining, ?int $monthlyRemaining): string
    {
        $dailyText = $dailyRemaining !== null ? number_format($dailyRemaining) : 'unlimited';
        $monthlyText = $monthlyRemaining !== null ? number_format($monthlyRemaining) : 'unlimited';

        return $dailyText.' / '.$monthlyText;
    }
}
