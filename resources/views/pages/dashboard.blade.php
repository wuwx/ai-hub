<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title("Dashboard")] class extends Component {
    #[Computed]
    public function currentPlanCode(): string
    {
        $user = Auth::user();
        if (!$user) {
            return "free";
        }

        if ($user->subscribed()) {
            return $user->subscription()->plan->slug;
        }

        return (string) config('services.billing.free_plan_code', 'free');
    }

    /**
     * @return array{today_tokens: int, today_requests: int, month_tokens: int, month_requests: int, month_errors: int, month_error_rate: float, daily_limit: int|null, monthly_limit: int|null, daily_remaining: int|null, monthly_remaining: int|null, top_models: array<int, array{name: string, tokens: int}>, requests_chart: array{labels: array<int, string>, values: array<int, int>}}
     */
    #[Computed]
    public function canManageApiKeys(): bool
    {
        return Auth::check();
    }

    #[Computed]
    public function canViewBilling(): bool
    {
        return Auth::check();
    }

    public function render()
    {
        return $this->view();
    }
};
?>

<section class="w-full p-6">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        {{-- Page Header --}}
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl" level="1">{{ __('Dashboard') }}</flux:heading>
                <flux:subheading>{{ __('Overview of your account and activity') }}</flux:subheading>
            </div>
            <flux:badge color="blue" size="lg">{{ ucfirst($this->currentPlanCode) }} {{ __('Plan') }}</flux:badge>
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
</section>
