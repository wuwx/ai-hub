<?php

use App\Actions\Usage\GetTeamUsageSnapshot;
use App\Enums\TeamPermission;
use App\Models\Team;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title("Dashboard")] class extends Component {
    #[Computed]
    public function team(): ?Team
    {
        return Auth::user()->currentTeam;
    }

    #[Computed]
    public function currentPlanCode(): string
    {
        if (!$this->team) {
            return "free";
        }

        $subscription = $this->team->subscription();

        if ($subscription && $subscription->valid()) {
            $stripePriceId = $subscription->stripe_price ?? "";

            if ($stripePriceId !== "") {
                $plans = (array) config("services.billing.plans", []);

                foreach ($plans as $code => $plan) {
                    if (($plan["stripe_price_id"] ?? null) === $stripePriceId) {
                        return (string) $code;
                    }
                }
            }
        }

        return (string) config("services.billing.free_plan_code", "free");
    }

    /**
     * @return array{today_tokens: int, today_requests: int, month_tokens: int, month_requests: int, month_errors: int, month_error_rate: float, daily_limit: int|null, monthly_limit: int|null, daily_remaining: int|null, monthly_remaining: int|null, top_models: array<int, array{name: string, tokens: int}>, requests_chart: array{labels: array<int, string>, values: array<int, int>}}
     */
    #[Computed]
    public function snapshot(): array
    {
        if (!$this->team) {
            return [
                "today_tokens" => 0,
                "today_requests" => 0,
                "month_tokens" => 0,
                "month_requests" => 0,
                "month_errors" => 0,
                "month_error_rate" => 0.0,
                "daily_limit" => null,
                "monthly_limit" => null,
                "daily_remaining" => null,
                "monthly_remaining" => null,
                "top_models" => [],
                "requests_chart" => ["labels" => [], "values" => []],
            ];
        }

        return app(GetTeamUsageSnapshot::class)->handle($this->team);
    }

    #[Computed]
    public function canViewUsage(): bool
    {
        if (!$this->team) {
            return false;
        }

        return Auth::user()->hasTeamPermission(
            $this->team,
            TeamPermission::ViewUsage,
        );
    }

    #[Computed]
    public function canManageApiKeys(): bool
    {
        if (!$this->team) {
            return false;
        }

        return Auth::user()->hasTeamPermission(
            $this->team,
            TeamPermission::ManageApiKeys,
        );
    }

    #[Computed]
    public function canViewBilling(): bool
    {
        if (!$this->team) {
            return false;
        }

        return Auth::user()->hasTeamPermission(
            $this->team,
            TeamPermission::ViewBilling,
        );
    }

    public function render()
    {
        return $this->view();
    }
};
?>

<section class="w-full p-6">
    <livewire:pages::teams.pending-invitations-modal />

    <div class="flex h-full w-full flex-1 flex-col gap-6">
        {{-- Page Header --}}
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl" level="1">{{ __('Dashboard') }}</flux:heading>
                <flux:subheading>{{ __('Overview of your team\'s usage and activity') }}</flux:subheading>
            </div>
            <flux:badge color="blue" size="lg">{{ ucfirst($this->currentPlanCode) }} {{ __('Plan') }}</flux:badge>
        </div>

        {{-- Stat Cards --}}
        <div class="grid auto-rows-min gap-4 md:grid-cols-4">
            <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Today\'s Tokens') }}</flux:text>
                <flux:heading level="2" size="lg" class="mt-1">{{ number_format($this->snapshot['today_tokens']) }}</flux:heading>
                @if ($this->snapshot['daily_limit'] !== null)
                    <flux:text class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">
                        {{ __(':remaining / :limit remaining', ['remaining' => number_format($this->snapshot['daily_remaining']), 'limit' => number_format($this->snapshot['daily_limit'])]) }}
                    </flux:text>
                @endif
            </div>

            <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Today\'s Requests') }}</flux:text>
                <flux:heading level="2" size="lg" class="mt-1">{{ number_format($this->snapshot['today_requests']) }}</flux:heading>
            </div>

            <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Monthly Tokens') }}</flux:text>
                <flux:heading level="2" size="lg" class="mt-1">{{ number_format($this->snapshot['month_tokens']) }}</flux:heading>
                @if ($this->snapshot['monthly_limit'] !== null)
                    <flux:text class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">
                        {{ __(':remaining / :limit remaining', ['remaining' => number_format($this->snapshot['monthly_remaining']), 'limit' => number_format($this->snapshot['monthly_limit'])]) }}
                    </flux:text>
                @endif
            </div>

            <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Error Rate') }}</flux:text>
                <flux:heading level="2" size="lg" class="mt-1">{{ $this->snapshot['month_error_rate'] }}%</flux:heading>
                <flux:text class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">
                    {{ __(':errors errors / :requests requests', ['errors' => number_format($this->snapshot['month_errors']), 'requests' => number_format($this->snapshot['month_requests'])]) }}
                </flux:text>
            </div>
        </div>

        {{-- Request Trend Chart (14 days) --}}
        <div class="rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="mb-4 flex items-center justify-between">
                <flux:heading level="2">{{ __('Request Trend (14 days)') }}</flux:heading>
                @if ($this->canViewUsage)
                    <flux:button variant="ghost" size="sm" :href="route('usage.index')" wire:navigate>
                        {{ __('View Details') }} →
                    </flux:button>
                @endif
            </div>

            @if (count($this->snapshot['requests_chart']['values']) > 0)
                @php
                    $maxVal = max(1, max($this->snapshot['requests_chart']['values']));
                @endphp
                <div class="flex items-end gap-1" style="height: 160px;">
                    @foreach ($this->snapshot['requests_chart']['labels'] as $i => $label)
                        @php
                            $val = $this->snapshot['requests_chart']['values'][$i];
                            $height = round(($val / $maxVal) * 100, 1);
                        @endphp
                        <div class="group relative flex flex-1 flex-col items-center justify-end" style="height: 100%;">
                            <div class="absolute bottom-full mb-2 hidden rounded bg-zinc-800 px-2 py-1 text-xs text-white group-hover:block dark:bg-zinc-200 dark:text-zinc-800">
                                {{ $label }}: {{ number_format($val) }}
                            </div>
                            <div
                                class="w-full min-w-[4px] rounded-t bg-blue-500 transition-all hover:bg-blue-600 dark:bg-blue-400 dark:hover:bg-blue-300"
                                style="height: {{ max($height, $val > 0 ? 2 : 0) }}%;"
                            ></div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-2 flex gap-1">
                    @foreach ($this->snapshot['requests_chart']['labels'] as $i => $label)
                        <div class="flex-1 text-center text-[10px] text-zinc-400">
                            @if ($i % max(1, intdiv(count($this->snapshot['requests_chart']['labels']), 7)) === 0)
                                {{ $label }}
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <div class="flex h-40 items-center justify-center text-zinc-400">
                    <flux:text>{{ __('No request data yet. Start making API calls to see your usage trend.') }}</flux:text>
                </div>
            @endif
        </div>

        {{-- Bottom Section: Top Models + Quick Actions --}}
        <div class="grid gap-4 md:grid-cols-2">
            {{-- Top Models --}}
            <div class="rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading level="2" class="mb-4">{{ __('Top Models This Month') }}</flux:heading>

                @if (count($this->snapshot['top_models']) > 0)
                    <div class="space-y-3">
                        @foreach ($this->snapshot['top_models'] as $model)
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="flex size-8 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/30">
                                        <flux:icon name="cube" class="size-4 text-blue-600 dark:text-blue-400" />
                                    </div>
                                    <span class="text-sm font-medium">{{ $model['name'] }}</span>
                                </div>
                                <span class="font-mono text-sm text-zinc-500 dark:text-zinc-400">{{ number_format($model['tokens']) }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center py-6 text-center text-zinc-400">
                        <flux:icon name="cube" class="size-10 text-zinc-300 dark:text-zinc-600" />
                        <flux:text class="mt-2">{{ __('No model usage yet this month.') }}</flux:text>
                    </div>
                @endif
            </div>

            {{-- Quick Actions --}}
            <div class="rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading level="2" class="mb-4">{{ __('Quick Actions') }}</flux:heading>

                <div class="space-y-3">
                    @if ($this->canManageApiKeys)
                        <a href="{{ route('api-keys.index') }}" wire:navigate class="flex items-center gap-3 rounded-lg border border-zinc-200 p-3 transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800">
                            <div class="flex size-9 items-center justify-center rounded-lg bg-green-100 dark:bg-green-900/30">
                                <flux:icon name="key" class="size-4 text-green-600 dark:text-green-400" />
                            </div>
                            <div>
                                <div class="text-sm font-medium">{{ __('Manage API Keys') }}</div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Create, rotate, or revoke keys') }}</div>
                            </div>
                        </a>
                    @endif

                    @if ($this->canViewUsage)
                        <a href="{{ route('usage.index') }}" wire:navigate class="flex items-center gap-3 rounded-lg border border-zinc-200 p-3 transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800">
                            <div class="flex size-9 items-center justify-center rounded-lg bg-purple-100 dark:bg-purple-900/30">
                                <flux:icon name="chart-bar" class="size-4 text-purple-600 dark:text-purple-400" />
                            </div>
                            <div>
                                <div class="text-sm font-medium">{{ __('View Usage') }}</div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Detailed token & model analytics') }}</div>
                            </div>
                        </a>
                    @endif

                    @if ($this->canViewBilling)
                        <a href="{{ route('billing.index') }}" wire:navigate class="flex items-center gap-3 rounded-lg border border-zinc-200 p-3 transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800">
                            <div class="flex size-9 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/30">
                                <flux:icon name="credit-card" class="size-4 text-blue-600 dark:text-blue-400" />
                            </div>
                            <div>
                                <div class="text-sm font-medium">{{ __('Billing & Subscription') }}</div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Manage plan & subscription') }}</div>
                            </div>
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>
