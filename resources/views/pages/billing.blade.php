<?php

use App\Actions\Billing\SyncQuotaFromSubscription;
use App\Models\User;
use App\Services\PlanService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Revoltify\Subscriptionify\Models\Plan;

new #[Title("Billing")] class extends Component {
    public function mount(): void
    {
        abort_unless(Auth::check(), 403);
    }

    #[Computed]
    public function canViewBilling(): bool
    {
        return Auth::check();
    }

    #[Computed]
    public function canManageBilling(): bool
    {
        return Auth::check();
    }

    #[Computed]
    public function currentSubscription(): ?\Revoltify\Subscriptionify\Models\Subscription
    {
        $user = Auth::user();

        return $user ? $user->subscription() : null;
    }

    #[Computed]
    public function currentPlanCode(): string
    {
        $user = Auth::user();

        if ($user && $user->subscribed()) {
            return $user->subscription()->plan->slug;
        }

        return app(PlanService::class)->freePlanCode();
    }

    /**
     * @return array{monthly: ?int, daily: ?int}|null
     */
    #[Computed]
    public function quotaLimits(): ?array
    {
        $user = Auth::user();
        if (! $user || ! $user->subscribed()) {
            return null;
        }

        return [
            'monthly' => $this->limitFromFeature($user, 'monthly-tokens'),
            'daily' => $this->limitFromFeature($user, 'daily-tokens'),
        ];
    }

    private function limitFromFeature(User $user, string $slug): ?int
    {
        if (! $user->hasFeature($slug) || $user->isUnlimitedUsage($slug)) {
            return null;
        }

        return (int) $user->featureInfo($slug)->limit;
    }

    /**
     * @return Collection<int, array{code: string, name: string, description: string, daily_token_limit: ?int, weekly_token_limit: ?int, monthly_token_limit: ?int, is_current: bool}>
     */
    #[Computed]
    public function plans(): Collection
    {
        $currentPlanCode = $this->currentPlanCode;

        return app(PlanService::class)
            ->allPlans()
            ->map(function (Plan $plan) use ($currentPlanCode) {
                return [
                    "code" => $plan->slug,
                    "name" => $plan->name,
                    "description" => $plan->description ?? "",
                    "daily_token_limit" => $this->tokenLimitFor($plan, 'daily-tokens'),
                    "weekly_token_limit" => $this->tokenLimitFor($plan, 'weekly-tokens'),
                    "monthly_token_limit" => $this->tokenLimitFor($plan, 'monthly-tokens'),
                    "is_current" => $plan->slug === $currentPlanCode,
                ];
            });
    }

    private function tokenLimitFor(Plan $plan, string $slug): ?int
    {
        $feature = $plan->features()->where('slug', $slug)->first();

        if (! $feature) {
            return null;
        }

        $value = (int) ($feature->pivot->value ?? 0);

        return $value > 0 ? $value : null;
    }

    /**
     * Switch the user's plan. Plan changes are quota-only: no Stripe checkout
     * is performed, the Subscriptionify subscription (and thus the enforced
     * token quota) is updated directly via {@see SyncQuotaFromSubscription}.
     */
    public function subscribeToPlan(string $planCode): void
    {
        abort_unless($this->canManageBilling, 403);

        $user = Auth::user();
        abort_unless($user, 404);

        $plan = app(PlanService::class)->findByCode($planCode);
        abort_unless($plan, 404);

        app(SyncQuotaFromSubscription::class)->handle(
            user: $user,
            planCode: $planCode,
            status: "active",
        );

        unset($this->currentSubscription, $this->currentPlanCode, $this->quotaLimits, $this->plans);

        \Flux\Flux::toast(
            variant: "success",
            text: __("Plan updated to :plan.", [
                "plan" => $plan->name ?? $planCode,
            ]),
        );
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
        <div>
            <flux:heading size="xl" level="1">{{ __('Billing & Subscription') }}</flux:heading>
            <flux:subheading>{{ __('Manage your plan and subscription') }}</flux:subheading>
        </div>

        {{-- Current Plan & Quota Summary --}}
        <div class="grid gap-4 md:grid-cols-2">
            {{-- Current Plan Card --}}
            <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Current Plan') }}</flux:text>
                <flux:heading level="2" size="lg" class="mt-1">
                    {{ ucfirst($this->currentPlanCode) }}
                </flux:heading>
                <div class="mt-2 flex items-center gap-2">
                    @if ($this->currentSubscription)
                        @if ($this->currentSubscription->valid())
                            <flux:badge color="green" size="sm">{{ __(ucfirst((string) $this->currentSubscription->getStatus()->value)) }}</flux:badge>
                        @else
                            <flux:badge color="red" size="sm">{{ __(ucfirst((string) $this->currentSubscription->getStatus()->value)) }}</flux:badge>
                        @endif
                    @else
                        <flux:badge color="zinc" size="sm">{{ __('No active subscription') }}</flux:badge>
                    @endif
                </div>
            </div>

            {{-- Quota Usage Card --}}
            <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Monthly Token Limit') }}</flux:text>
                @if ($this->quotaLimits)
                    <flux:heading level="2" size="lg" class="mt-1">
                        @if ($this->quotaLimits['monthly'] === null)
                            {{ __('Unlimited') }}
                        @else
                            {{ number_format($this->quotaLimits['monthly']) }}
                        @endif
                    </flux:heading>
                    @if ($this->quotaLimits['daily'] !== null)
                        <flux:text class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">
                            {{ __('Daily limit: :limit', ['limit' => number_format($this->quotaLimits['daily'])]) }}
                        </flux:text>
                    @endif
                @else
                    <flux:heading level="2" size="lg" class="mt-1">{{ __('N/A') }}</flux:heading>
                @endif
            </div>
        </div>

        {{-- Subscription Plans --}}
        <div>
            <flux:heading level="2" class="mb-4">{{ __('Choose Your Plan') }}</flux:heading>
            <flux:subheading class="mb-6">{{ __('Select the plan that best fits your needs. Upgrade or downgrade at any time.') }}</flux:subheading>

            <div class="grid gap-4 md:grid-cols-3">
                @foreach ($this->plans as $plan)
                    <div class="relative flex flex-col rounded-lg border {{ $plan['is_current'] ? 'border-blue-500 ring-2 ring-blue-500/20 dark:border-blue-400 dark:ring-blue-400/20' : 'border-zinc-200 dark:border-zinc-700' }} bg-white p-6 dark:bg-zinc-900">
                        @if ($plan['is_current'])
                            <div class="absolute -top-3 left-1/2 -translate-x-1/2">
                                <flux:badge color="blue" size="sm">{{ __('Current Plan') }}</flux:badge>
                            </div>
                        @endif

                        <flux:heading level="3" size="lg">{{ $plan['name'] }}</flux:heading>
                        <flux:text class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $plan['description'] }}</flux:text>

                        <div class="mt-4 flex items-baseline gap-1">
                            @if ($plan['monthly_token_limit'] === null)
                                <flux:heading level="2" size="xl">{{ __('Unlimited') }}</flux:heading>
                            @else
                                <flux:heading level="2" size="xl">{{ number_format($plan['monthly_token_limit']) }}</flux:heading>
                                <flux:text class="text-sm text-zinc-400 dark:text-zinc-500">{{ __('tokens / month') }}</flux:text>
                            @endif
                        </div>

                        @if ($plan['daily_token_limit'] !== null)
                            <flux:text class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">
                                {{ number_format($plan['daily_token_limit']) }} {{ __('tokens / day') }}
                            </flux:text>
                        @endif

                        <div class="mt-6">
                            @if ($plan['is_current'])
                                <flux:button variant="filled" disabled class="w-full">
                                    {{ __('Current Plan') }}
                                </flux:button>
                            @elseif ($plan['code'] === 'enterprise')
                                <flux:button variant="primary" class="w-full" href="mailto:sales@example.com">
                                    {{ __('Contact Sales') }}
                                </flux:button>
                            @else
                                <flux:button variant="primary" class="w-full" wire:click="subscribeToPlan('{{ $plan['code'] }}')">
                                    {{ $this->currentPlanCode === (string) config('services.billing.free_plan_code', 'free') ? __('Subscribe Now') : __('Switch Plan') }}
                                </flux:button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>
