<?php

use App\Actions\Billing\SyncTeamQuotaFromSubscription;
use App\Enums\TeamPermission;
use App\Models\Team;
use App\Models\TeamQuotaPolicy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title("Billing")] class extends Component {
    public function mount(): void
    {
        $team = Auth::user()->currentTeam;

        abort_unless(
            $team &&
                Auth::user()->hasTeamPermission(
                    $team,
                    TeamPermission::ViewBilling,
                ),
            403,
        );
    }

    #[Computed]
    public function team(): ?Team
    {
        return Auth::user()->currentTeam;
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

    #[Computed]
    public function canManageBilling(): bool
    {
        if (!$this->team) {
            return false;
        }

        return Auth::user()->hasTeamPermission(
            $this->team,
            TeamPermission::ManageBilling,
        );
    }

    #[Computed]
    public function currentSubscription(): ?\Laravel\Cashier\Subscription
    {
        if (!$this->team) {
            return null;
        }

        return $this->team->subscription();
    }

    #[Computed]
    public function currentPlanCode(): string
    {
        $subscription = $this->currentSubscription;

        if ($subscription && $subscription->valid()) {
            return $this->resolvePlanCodeFromPriceId(
                $subscription->stripe_price ?? "",
            );
        }

        // Fallback: check the active quota policy to determine the plan.
        $policy = $this->activeQuotaPolicy;
        if ($policy) {
            return $this->resolvePlanCodeFromLimits(
                $policy->daily_token_limit,
                $policy->weekly_token_limit,
                $policy->monthly_token_limit,
            );
        }

        return (string) config("services.billing.free_plan_code", "free");
    }

    protected function resolvePlanCodeFromLimits(
        ?int $daily,
        ?int $weekly,
        ?int $monthly,
    ): string {
        $plans = (array) config("services.billing.plans", []);

        foreach ($plans as $code => $plan) {
            if (
                ($plan["daily_token_limit"] ?? null) === $daily &&
                ($plan["weekly_token_limit"] ?? null) === $weekly &&
                ($plan["monthly_token_limit"] ?? null) === $monthly
            ) {
                return (string) $code;
            }
        }

        return (string) config("services.billing.free_plan_code", "free");
    }

    protected function resolvePlanCodeFromPriceId(string $stripePriceId): string
    {
        if ($stripePriceId === "") {
            return (string) config("services.billing.free_plan_code", "free");
        }

        $plans = (array) config("services.billing.plans", []);

        foreach ($plans as $code => $plan) {
            if (($plan["stripe_price_id"] ?? null) === $stripePriceId) {
                return (string) $code;
            }
        }

        return (string) config("services.billing.free_plan_code", "free");
    }

    #[Computed]
    public function activeQuotaPolicy(): ?TeamQuotaPolicy
    {
        if (!$this->team) {
            return null;
        }

        return TeamQuotaPolicy::where("team_id", $this->team->id)
            ->where("is_active", true)
            ->orderByDesc("effective_from")
            ->first();
    }

    /**
     * @return Collection<int, array{code: string, name: string, description: string, monthly_price_cents: int, features: array<int, string>, daily_token_limit: ?int, weekly_token_limit: ?int, monthly_token_limit: ?int, is_current: bool}>
     */
    #[Computed]
    public function plans(): Collection
    {
        $plans = (array) config("services.billing.plans", []);
        $currentPlanCode = $this->currentPlanCode;

        return collect($plans)
            ->map(function (array $plan, string $code) use ($currentPlanCode) {
                return [
                    "code" => $code,
                    "name" => $plan["name"] ?? ucfirst($code),
                    "description" => $plan["description"] ?? "",
                    "monthly_price_cents" =>
                        (int) ($plan["monthly_price_cents"] ?? 0),
                    "features" => (array) ($plan["features"] ?? []),
                    "daily_token_limit" => isset($plan["daily_token_limit"])
                        ? (int) $plan["daily_token_limit"]
                        : null,
                    "weekly_token_limit" => isset($plan["weekly_token_limit"])
                        ? (int) $plan["weekly_token_limit"]
                        : null,
                    "monthly_token_limit" => isset($plan["monthly_token_limit"])
                        ? (int) $plan["monthly_token_limit"]
                        : null,
                    "is_current" => $code === $currentPlanCode,
                ];
            })
            ->values();
    }

    /**
     * Subscribe the team to a plan, or downgrade to the free plan.
     *
     * Paid plans are billed as a real recurring Stripe subscription: new
     * subscribers go through Stripe Checkout, existing subscribers swap
     * their price in place (prorated by Stripe).
     */
    public function subscribeToPlan(string $planCode): void
    {
        abort_unless($this->canManageBilling, 403);

        $team = $this->team;
        abort_unless($team, 404);

        $planConfig = config("services.billing.plans.{$planCode}");
        abort_unless($planConfig, 404);

        $stripePriceId = (string) ($planConfig["stripe_price_id"] ?? "");
        $monthlyPriceCents = (int) ($planConfig["monthly_price_cents"] ?? 0);

        if ($stripePriceId === "" || $monthlyPriceCents === 0) {
            $this->switchToFreePlan();

            return;
        }

        if (!config("cashier.secret")) {
            \Flux\Flux::toast(
                variant: "danger",
                text: __("Stripe is not configured."),
            );

            return;
        }

        try {
            if ($team->subscribed("default")) {
                $team->subscription("default")->swap($stripePriceId);

                app(SyncTeamQuotaFromSubscription::class)->handle(
                    team: $team,
                    planCode: $planCode,
                    status: "active",
                );

                unset(
                    $this->currentSubscription,
                    $this->currentPlanCode,
                    $this->activeQuotaPolicy,
                    $this->plans,
                );

                \Flux\Flux::toast(
                    variant: "success",
                    text: __("Plan updated to :plan.", [
                        "plan" => $planConfig["name"] ?? $planCode,
                    ]),
                );

                return;
            }

            $baseUrl = rtrim((string) config("app.url"), "/");
            $successUrl = (string) config(
                "services.billing.checkout_success_url",
                $baseUrl . "/" . $team->slug . "/billing/success",
            );
            $cancelUrl = (string) config(
                "services.billing.checkout_cancel_url",
                $baseUrl . "/" . $team->slug . "/billing/cancel",
            );
            $successUrl .=
                (str_contains($successUrl, "?") ? "&" : "?") .
                "session_id={CHECKOUT_SESSION_ID}";

            $checkout = $team
                ->newSubscription("default", $stripePriceId)
                ->checkout([
                    "success_url" => $successUrl,
                    "cancel_url" => $cancelUrl,
                ]);

            $this->redirect($checkout->url, navigate: false);
        } catch (\Exception $e) {
            \Flux\Flux::toast(
                variant: "danger",
                text: __("Unable to create checkout session: :error", [
                    "error" => $e->getMessage(),
                ]),
            );
        }
    }

    /**
     * Cancel any active subscription immediately and drop the team back to
     * the free plan's quota limits.
     */
    protected function switchToFreePlan(): void
    {
        $team = $this->team;
        abort_unless($team, 404);

        if ($team->subscribed("default")) {
            $team->subscription("default")->cancelNow();
        }

        app(SyncTeamQuotaFromSubscription::class)->handle(
            team: $team,
            planCode: (string) config(
                "services.billing.free_plan_code",
                "free",
            ),
            status: "canceled",
        );

        unset(
            $this->currentSubscription,
            $this->currentPlanCode,
            $this->activeQuotaPolicy,
            $this->plans,
        );

        \Flux\Flux::toast(
            variant: "success",
            text: __("Switched to the Free plan."),
        );
    }

    /**
     * Redirect to the Stripe Customer Portal to manage payment methods,
     * cancel, or view Stripe-hosted invoice history.
     */
    public function openBillingPortal(): void
    {
        abort_unless($this->canManageBilling, 403);

        $team = $this->team;
        abort_unless($team, 404);

        if (!$team->hasStripeId()) {
            \Flux\Flux::toast(
                variant: "danger",
                text: __("No billing account yet. Subscribe to a plan first."),
            );

            return;
        }

        try {
            $url = $team->billingPortalUrl(
                route("billing.index", ["current_team" => $team->slug]),
            );

            $this->redirect($url, navigate: false);
        } catch (\Exception $e) {
            \Flux\Flux::toast(
                variant: "danger",
                text: __("Failed to open the billing portal."),
            );
        }
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
                            <flux:badge color="green" size="sm">{{ __(ucfirst($this->currentSubscription->stripe_status)) }}</flux:badge>
                        @else
                            <flux:badge color="red" size="sm">{{ __(ucfirst($this->currentSubscription->stripe_status)) }}</flux:badge>
                        @endif
                    @else
                        <flux:badge color="zinc" size="sm">{{ __('No active subscription') }}</flux:badge>
                    @endif
                </div>

                @if ($this->canManageBilling && $this->team?->hasStripeId())
                    <flux:button variant="ghost" size="sm" class="mt-3" wire:click="openBillingPortal">
                        {{ __('Manage Subscription') }}
                    </flux:button>
                @endif
            </div>

            {{-- Quota Usage Card --}}
            <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Monthly Token Limit') }}</flux:text>
                @if ($this->activeQuotaPolicy)
                    <flux:heading level="2" size="lg" class="mt-1">
                        @if ($this->activeQuotaPolicy->monthly_token_limit === null)
                            {{ __('Unlimited') }}
                        @else
                            {{ number_format($this->activeQuotaPolicy->monthly_token_limit) }}
                        @endif
                    </flux:heading>
                    @if ($this->activeQuotaPolicy->daily_token_limit !== null)
                        <flux:text class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">
                            {{ __('Daily limit: :limit', ['limit' => number_format($this->activeQuotaPolicy->daily_token_limit)]) }}
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
                            @if ($plan['monthly_price_cents'] === 0)
                                <flux:heading level="2" size="xl">{{ __('Free') }}</flux:heading>
                            @else
                                <flux:heading level="2" size="xl">${{ number_format($plan['monthly_price_cents'] / 100, 0) }}</flux:heading>
                                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">/ {{ __('month') }}</flux:text>
                            @endif
                        </div>

                        <ul class="mt-6 flex-1 space-y-2">
                            @foreach ($plan['features'] as $feature)
                                <li class="flex items-start gap-2 text-sm">
                                    <flux:icon name="check" class="mt-0.5 size-4 shrink-0 text-green-500" />
                                    <span class="text-zinc-600 dark:text-zinc-300">{{ $feature }}</span>
                                </li>
                            @endforeach
                        </ul>

                        <div class="mt-6">
                            @if ($plan['is_current'])
                                <flux:button variant="filled" disabled class="w-full">
                                    {{ __('Current Plan') }}
                                </flux:button>
                            @elseif ($plan['code'] === 'enterprise')
                                <flux:button variant="primary" class="w-full" href="mailto:sales@example.com">
                                    {{ __('Contact Sales') }}
                                </flux:button>
                            @elseif ($plan['monthly_price_cents'] === 0)
                                <flux:button variant="ghost" class="w-full" wire:click="subscribeToPlan('{{ $plan['code'] }}')" wire:confirm="{{ __('Cancel your current subscription and switch to the Free plan?') }}">
                                    {{ __('Downgrade to Free') }}
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
