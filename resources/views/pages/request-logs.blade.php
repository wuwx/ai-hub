<?php

use App\Enums\TeamPermission;
use App\Models\RequestLog;
use App\Models\Team;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Request Logs')] class extends Component
{
    public int $perPage = 25;

    public ?string $filterStatusCode = null;

    public ?string $filterModel = null;

    public function mount(): void
    {
        $team = Auth::user()->currentTeam;

        abort_unless($team && Auth::user()->hasTeamPermission($team, TeamPermission::ViewUsage), 403);
    }

    #[Computed]
    public function team(): ?Team
    {
        return Auth::user()->currentTeam;
    }

    /**
     * @return Collection<int, array{id: int, trace_id: ?string, model_name: string, provider_name: string, http_method: string, endpoint: string, status_code: int, token_input: int, token_output: int, token_total: int, latency_ms: int, is_streaming: bool, error_code: ?string, error_message: ?string, requested_at: string}>
     */
    #[Computed]
    public function logs(): Collection
    {
        if (! $this->team) {
            return collect();
        }

        $query = RequestLog::query()
            ->where('team_id', $this->team->id)
            ->leftJoin('llm_models', 'llm_models.id', '=', 'request_logs.llm_model_id')
            ->leftJoin('llm_providers', 'llm_providers.id', '=', 'request_logs.llm_provider_id')
            ->orderByDesc('request_logs.requested_at');

        if ($this->filterStatusCode) {
            $query->where('request_logs.status_code', (int) $this->filterStatusCode);
        }

        if ($this->filterModel) {
            $query->where('llm_models.name', $this->filterModel);
        }

        return $query
            ->limit($this->perPage)
            ->get([
                'request_logs.*',
                'llm_models.name as model_name',
                'llm_providers.name as provider_name',
            ])
            ->map(fn ($row) => [
                'id' => $row->id,
                'trace_id' => $row->trace_id,
                'model_name' => (string) ($row->model_name ?? 'Unknown'),
                'provider_name' => (string) ($row->provider_name ?? 'Unknown'),
                'http_method' => $row->http_method ?? 'POST',
                'endpoint' => $row->endpoint ?? '',
                'status_code' => (int) ($row->status_code ?? 0),
                'token_input' => (int) ($row->token_input ?? 0),
                'token_output' => (int) ($row->token_output ?? 0),
                'token_total' => (int) ($row->token_total ?? 0),
                'latency_ms' => (int) ($row->latency_ms ?? 0),
                'is_streaming' => (bool) $row->is_streaming,
                'error_code' => $row->error_code,
                'error_message' => $row->error_message,
                'requested_at' => $row->requested_at?->toDateTimeString() ?? '—',
            ]);
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function availableModels(): array
    {
        if (! $this->team) {
            return [];
        }

        return RequestLog::query()
            ->where('team_id', $this->team->id)
            ->join('llm_models', 'llm_models.id', '=', 'request_logs.llm_model_id')
            ->distinct()
            ->pluck('llm_models.name')
            ->sort()
            ->values()
            ->toArray();
    }

    public function loadMore(): void
    {
        $this->perPage += 25;
    }

    public function resetFilters(): void
    {
        $this->filterStatusCode = null;
        $this->filterModel = null;
    }

    public function render()
    {
        return $this->view();
    }
}; ?>

<section class="w-full p-6">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl" level="1">{{ __('Request Logs') }}</flux:heading>
                <flux:subheading>{{ __('Detailed API request and response history') }}</flux:subheading>
            </div>

            @if ($this->filterStatusCode || $this->filterModel)
                <flux:button variant="ghost" size="sm" wire:click="resetFilters" icon="x-mark">
                    {{ __('Clear Filters') }}
                </flux:button>
            @endif
        </div>

        {{-- Filters --}}
        <div class="flex flex-wrap gap-3">
            <flux:select wire:model.live="filterStatusCode" :label="__('Status Code')" class="w-40">
                <option value="">{{ __('All') }}</option>
                <option value="200">200 OK</option>
                <option value="201">201 Created</option>
                <option value="400">400 Bad Request</option>
                <option value="401">401 Unauthorized</option>
                <option value="429">429 Rate Limited</option>
                <option value="500">500 Server Error</option>
                <option value="502">502 Bad Gateway</option>
                <option value="503">503 Unavailable</option>
            </flux:select>

            @if (count($this->availableModels) > 0)
                <flux:select wire:model.live="filterModel" :label="__('Model')" class="w-52">
                    <option value="">{{ __('All Models') }}</option>
                    @foreach ($this->availableModels as $model)
                        <option value="{{ $model }}">{{ $model }}</option>
                    @endforeach
                </flux:select>
            @endif
        </div>

        {{-- Logs Table --}}
        <div class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            @if ($this->logs->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-t border-zinc-200 dark:border-zinc-700">
                                <th class="px-4 py-3 text-left font-medium text-zinc-500 dark:text-zinc-400">{{ __('Time') }}</th>
                                <th class="px-4 py-3 text-left font-medium text-zinc-500 dark:text-zinc-400">{{ __('Model') }}</th>
                                <th class="px-4 py-3 text-center font-medium text-zinc-500 dark:text-zinc-400">{{ __('Status') }}</th>
                                <th class="px-4 py-3 text-right font-medium text-zinc-500 dark:text-zinc-400">{{ __('Tokens') }}</th>
                                <th class="px-4 py-3 text-right font-medium text-zinc-500 dark:text-zinc-400">{{ __('Latency') }}</th>
                                <th class="px-4 py-3 text-center font-medium text-zinc-500 dark:text-zinc-400">{{ __('Stream') }}</th>
                                <th class="px-4 py-3 text-left font-medium text-zinc-500 dark:text-zinc-400">{{ __('Error') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->logs as $log)
                                <tr class="border-t border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                    <td class="whitespace-nowrap px-4 py-3 text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ $log['requested_at'] }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-medium">{{ $log['model_name'] }}</div>
                                        <div class="text-xs text-zinc-400">{{ $log['provider_name'] }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        @if ($log['status_code'] >= 200 && $log['status_code'] < 300)
                                            <flux:badge color="green" size="sm">{{ $log['status_code'] }}</flux:badge>
                                        @elseif ($log['status_code'] >= 400 && $log['status_code'] < 500)
                                            <flux:badge color="orange" size="sm">{{ $log['status_code'] }}</flux:badge>
                                        @elseif ($log['status_code'] >= 500)
                                            <flux:badge color="red" size="sm">{{ $log['status_code'] }}</flux:badge>
                                        @else
                                            <flux:badge color="zinc" size="sm">{{ $log['status_code'] }}</flux:badge>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-xs">
                                        <span class="text-zinc-400">{{ number_format($log['token_input']) }}</span>
                                        <span class="text-zinc-300 dark:text-zinc-600">→</span>
                                        <span class="text-zinc-400">{{ number_format($log['token_output']) }}</span>
                                        <span class="ml-1 font-medium text-zinc-600 dark:text-zinc-300">({{ number_format($log['token_total']) }})</span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-xs">
                                        @if ($log['latency_ms'] >= 5000)
                                            <span class="text-red-500">{{ number_format($log['latency_ms']) }}ms</span>
                                        @elseif ($log['latency_ms'] >= 2000)
                                            <span class="text-orange-500">{{ number_format($log['latency_ms']) }}ms</span>
                                        @else
                                            <span>{{ number_format($log['latency_ms']) }}ms</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        @if ($log['is_streaming'])
                                            <flux:badge color="blue" size="sm">{{ __('SSE') }}</flux:badge>
                                        @else
                                            <flux:text class="text-zinc-400">—</flux:text>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-xs">
                                        @if ($log['error_message'])
                                            <span class="text-red-500" title="{{ $log['error_message'] }}">
                                                {{ Str::limit($log['error_message'], 40) }}
                                    </span>
                                @else
                                    <flux:text class="text-zinc-400">—</flux:text>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="flex items-center justify-between p-4">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('Showing :count logs', ['count' => $this->logs->count()]) }}
            </flux:text>
            @if ($this->logs->count() >= $this->perPage)
                <flux:button variant="filled" size="sm" wire:click="loadMore">
                    {{ __('Load More') }}
                </flux:button>
            @endif
        </div>
    @else
        <div class="flex flex-col items-center justify-center py-12 text-center">
            <flux:icon name="document-text" class="size-12 text-zinc-300 dark:text-zinc-600" />
            <flux:heading level="3" class="mt-4">{{ __('No request logs yet') }}</flux:heading>
            <flux:subheading class="mt-1">{{ __('API request logs will appear here once you start making calls.') }}</flux:subheading>
        </div>
    @endif
</div>
    </div>
</section>
