<?php

use App\Enums\TeamPermission;
use App\Models\LlmModel;
use App\Models\Team;
use App\Models\UsageLedger;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Usage')] class extends Component
{
    #[Computed]
    public function team(): ?Team
    {
        return Auth::user()->currentTeam;
    }

    #[Computed]
    public function canView(): bool
    {
        if (! $this->team) {
            return false;
        }

        return Auth::user()->hasTeamPermission($this->team, TeamPermission::ViewUsage);
    }

    /**
     * @return array{start: string, end: string}
     */
    #[Computed]
    public function billingCycle(): array
    {
        if (! $this->team) {
            $now = now();

            return ['start' => $now->startOfMonth()->toDateString(), 'end' => $now->endOfMonth()->toDateString()];
        }

        $subscription = $this->team->subscription();

        if ($subscription && $subscription->valid() && $subscription->created_at) {
            $start = $subscription->created_at->startOfMonth();
            $end = $start->copy()->endOfMonth();

            return [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ];
        }

        $now = now();

        return ['start' => $now->startOfMonth()->toDateString(), 'end' => $now->endOfMonth()->toDateString()];
    }

    /**
     * @return Collection<int, array{model_name: string, provider_name: string, token_input: int, token_output: int, token_total: int, request_count: int, error_count: int}>
     */
    #[Computed]
    public function modelUsage(): Collection
    {
        if (! $this->team) {
            return collect();
        }

        $cycle = $this->billingCycle;

        return UsageLedger::query()
            ->leftJoin('llm_models', 'llm_models.id', '=', 'usage_ledgers.llm_model_id')
            ->leftJoin('llm_providers', 'llm_providers.id', '=', 'usage_ledgers.llm_provider_id')
            ->where('usage_ledgers.team_id', $this->team->id)
            ->where('usage_ledgers.bucket_type', 'day')
            ->whereBetween('usage_ledgers.bucket_date', [Carbon::parse($cycle['start'])->startOfDay(), Carbon::parse($cycle['end'])->endOfDay()])
            ->whereNotNull('usage_ledgers.llm_model_id')
            ->groupBy('usage_ledgers.llm_model_id', 'llm_models.name', 'llm_providers.name')
            ->orderByDesc(DB::raw('SUM(usage_ledgers.token_total)'))
            ->get([
                'llm_models.name as model_name',
                'llm_providers.name as provider_name',
                DB::raw('COALESCE(SUM(usage_ledgers.token_input), 0) as token_input'),
                DB::raw('COALESCE(SUM(usage_ledgers.token_output), 0) as token_output'),
                DB::raw('COALESCE(SUM(usage_ledgers.token_total), 0) as token_total'),
                DB::raw('COALESCE(SUM(usage_ledgers.request_count), 0) as request_count'),
                DB::raw('COALESCE(SUM(usage_ledgers.error_count), 0) as error_count'),
            ])
            ->map(fn ($row) => [
                'model_name' => (string) ($row->model_name ?: 'Unknown'),
                'provider_name' => (string) ($row->provider_name ?: 'Unknown'),
                'token_input' => (int) $row->token_input,
                'token_output' => (int) $row->token_output,
                'token_total' => (int) $row->token_total,
                'request_count' => (int) $row->request_count,
                'error_count' => (int) $row->error_count,
            ]);
    }

    /**
     * @return array{summary: array{total_tokens: int, total_requests: int, total_errors: int, error_rate: float}, daily: array<int, array{date: string, label: string, tokens: int, height: float}>}
     */
    #[Computed]
    public function chartData(): array
    {
        if (! $this->team) {
            return ['summary' => ['total_tokens' => 0, 'total_requests' => 0, 'total_errors' => 0, 'error_rate' => 0.0], 'daily' => []];
        }

        $cycle = $this->billingCycle;
        $startDate = Carbon::parse($cycle['start']);
        $endDate = Carbon::parse($cycle['end']);

        /** @var Collection<string, int> $rows */
        $rows = DB::table('usage_ledgers')
            ->where('team_id', $this->team->id)
            ->where('bucket_type', 'day')
            ->whereBetween('bucket_date', [Carbon::parse($cycle['start'])->startOfDay(), Carbon::parse($cycle['end'])->endOfDay()])
            ->selectRaw('DATE(bucket_date) as bucket_date, SUM(token_total) as token_total, SUM(request_count) as request_count, SUM(error_count) as error_count')
            ->groupBy('bucket_date')
            ->orderBy('bucket_date')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->bucket_date => $row]);

        $daily = [];
        $totalTokens = 0;
        $totalRequests = 0;
        $totalErrors = 0;
        $maxTokens = 1;

        $date = $startDate->copy();
        while ($date->lte($endDate)) {
            $dateStr = $date->toDateString();
            $row = $rows->get($dateStr);
            $tokens = (int) ($row->token_total ?? 0);
            $totalTokens += $tokens;
            $totalRequests += (int) ($row->request_count ?? 0);
            $totalErrors += (int) ($row->error_count ?? 0);
            $maxTokens = max($maxTokens, $tokens);

            $daily[] = [
                'date' => $dateStr,
                'label' => $date->format('m-d'),
                'tokens' => $tokens,
            ];

            $date = $date->addDay();
        }

        // Calculate bar heights as percentages (0-100)
        foreach ($daily as &$day) {
            $day['height'] = round(($day['tokens'] / $maxTokens) * 100, 1);
        }
        unset($day);

        $errorRate = $totalRequests > 0
            ? round(($totalErrors / $totalRequests) * 100, 2)
            : 0.0;

        return [
            'summary' => [
                'total_tokens' => $totalTokens,
                'total_requests' => $totalRequests,
                'total_errors' => $totalErrors,
                'error_rate' => $errorRate,
            ],
            'daily' => $daily,
        ];
    }

    public function render()
    {
        return $this->view();
    }
}; ?>

<section class="w-full p-6">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <div>
            <flux:heading size="xl" level="1">{{ __('Usage') }}</flux:heading>
            <flux:subheading>
                {{ __('Billing cycle: :start — :end', ['start' => $this->billingCycle['start'], 'end' => $this->billingCycle['end']]) }}
            </flux:subheading>
        </div>

        {{-- Summary Cards --}}
        <div class="grid auto-rows-min gap-4 md:grid-cols-4">
            <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total Tokens') }}</flux:text>
                <flux:heading level="2" size="lg">{{ number_format($this->chartData['summary']['total_tokens']) }}</flux:heading>
            </div>
            <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total Requests') }}</flux:text>
                <flux:heading level="2" size="lg">{{ number_format($this->chartData['summary']['total_requests']) }}</flux:heading>
            </div>
            <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total Errors') }}</flux:text>
                <flux:heading level="2" size="lg">{{ number_format($this->chartData['summary']['total_errors']) }}</flux:heading>
            </div>
            <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Error Rate') }}</flux:text>
                <flux:heading level="2" size="lg">{{ $this->chartData['summary']['error_rate'] }}%</flux:heading>
            </div>
        </div>

        {{-- Daily Token Usage Bar Chart --}}
        <div class="rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading level="2" class="mb-4">{{ __('Daily Token Usage') }}</flux:heading>

            @if (count($this->chartData['daily']) > 0)
                <div class="flex items-end gap-1" style="height: 200px;">
                    @foreach ($this->chartData['daily'] as $day)
                        <div class="group relative flex flex-1 flex-col items-center justify-end" style="height: 100%;">
                            <div class="absolute bottom-full mb-2 hidden rounded bg-zinc-800 px-2 py-1 text-xs text-white group-hover:block dark:bg-zinc-200 dark:text-zinc-800">
                                {{ $day['date'] }}: {{ number_format($day['tokens']) }}
                            </div>
                            <div
                                class="w-full min-w-[4px] rounded-t bg-blue-500 transition-all hover:bg-blue-600 dark:bg-blue-400 dark:hover:bg-blue-300"
                                style="height: {{ max($day['height'], $day['tokens'] > 0 ? 2 : 0) }}%;"
                            ></div>
                        </div>
                    @endforeach
                </div>

                {{-- X-axis labels --}}
                <div class="mt-2 flex gap-1">
                    @foreach ($this->chartData['daily'] as $day)
                        <div class="flex-1 text-center text-[10px] text-zinc-400">
                            @if ($loop->index % max(1, intdiv(count($this->chartData['daily']), 10)) === 0)
                                {{ $day['label'] }}
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <div class="flex h-48 items-center justify-center text-zinc-400">
                    <flux:text>{{ __('No usage data available for this billing cycle.') }}</flux:text>
                </div>
            @endif
        </div>

        {{-- Per-Model Usage Table --}}
        <div class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <div class="p-6">
                <flux:heading level="2">{{ __('Model Usage') }}</flux:heading>
            </div>

            @if ($this->modelUsage->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-t border-zinc-200 dark:border-zinc-700">
                                <th class="px-6 py-3 text-left font-medium text-zinc-500 dark:text-zinc-400">{{ __('Model') }}</th>
                                <th class="px-6 py-3 text-left font-medium text-zinc-500 dark:text-zinc-400">{{ __('Provider') }}</th>
                                <th class="px-6 py-3 text-right font-medium text-zinc-500 dark:text-zinc-400">{{ __('Input Tokens') }}</th>
                                <th class="px-6 py-3 text-right font-medium text-zinc-500 dark:text-zinc-400">{{ __('Output Tokens') }}</th>
                                <th class="px-6 py-3 text-right font-medium text-zinc-500 dark:text-zinc-400">{{ __('Total Tokens') }}</th>
                                <th class="px-6 py-3 text-right font-medium text-zinc-500 dark:text-zinc-400">{{ __('Requests') }}</th>
                                <th class="px-6 py-3 text-right font-medium text-zinc-500 dark:text-zinc-400">{{ __('Errors') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->modelUsage as $row)
                                <tr class="border-t border-zinc-200 dark:border-zinc-700">
                                    <td class="px-6 py-3 font-medium">{{ $row['model_name'] }}</td>
                                    <td class="px-6 py-3 text-zinc-500 dark:text-zinc-400">{{ $row['provider_name'] }}</td>
                                    <td class="px-6 py-3 text-right font-mono">{{ number_format($row['token_input']) }}</td>
                                    <td class="px-6 py-3 text-right font-mono">{{ number_format($row['token_output']) }}</td>
                                    <td class="px-6 py-3 text-right font-mono font-medium">{{ number_format($row['token_total']) }}</td>
                                    <td class="px-6 py-3 text-right font-mono">{{ number_format($row['request_count']) }}</td>
                                    <td class="px-6 py-3 text-right font-mono {{ $row['error_count'] > 0 ? 'text-red-500' : '' }}">{{ number_format($row['error_count']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="px-6 py-12 text-center text-zinc-400">
                    <flux:text>{{ __('No model usage recorded for this billing cycle.') }}</flux:text>
                </div>
            @endif
        </div>
    </div>
</section>
