<?php

namespace App\Filament\Widgets;

use App\Actions\Usage\GetUsageSnapshot;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class UsageRequestsChart extends ChartWidget
{
    protected ?string $heading = 'Requests (Last 14 Days)';

    public static function canView(): bool
    {
        return Auth::check();
    }

    protected function getData(): array
    {
        $user = Auth::user();

        if (! $user) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        $snapshot = app(GetUsageSnapshot::class)->handle($user);
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
