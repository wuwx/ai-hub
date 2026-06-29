<?php

namespace App\Filament\Widgets;

use App\Actions\Usage\GetTeamUsageSnapshot;
use App\Enums\TeamPermission;
use App\Models\User;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class UsageRequestsChart extends ChartWidget
{
    protected ?string $heading = 'Requests (Last 14 Days)';

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

    protected function getData(): array
    {
        $team = Auth::user()?->currentTeam;

        if (! $team) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        $snapshot = app(GetTeamUsageSnapshot::class)->handle($team);
        $chart = $snapshot['requests_chart'];

        return [
            'datasets' => [
                [
                    'label' => 'Requests',
                    'data' => $chart['values'],
                ],
            ],
            'labels' => $chart['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
