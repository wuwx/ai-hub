<?php

use App\Actions\Billing\CreateStripeCheckoutSession;
use App\Enums\TeamPermission;
use App\Models\BillingInvoice;
use App\Models\Team;
use App\Models\TeamQuotaPolicy;
use App\Models\TeamWallet;
use App\Models\TeamWalletTransaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Billing')] class extends Component
{
    public string $rechargeAmount = '';

    public function mount(): void
    {
        $team = Auth::user()->currentTeam;

        abort_unless($team && Auth::user()->hasTeamPermission($team, TeamPermission::ViewBilling), 403);
    }

    #[Computed]
    public function team(): ?Team
    {
        return Auth::user()->currentTeam;
    }

    #[Computed]
    public function canViewBilling(): bool
    {
        if (! $this->team) {
            return false;
        }

        return Auth::user()->hasTeamPermission($this->team, TeamPermission::ViewBilling);
    }

    #[Computed]
    public function canManageBilling(): bool
    {
        if (! $this->team) {
            return false;
        }

        return Auth::user()->hasTeamPermission($this->team, TeamPermission::ManageBilling);
    }

    #[Computed]
    public function currentSubscription(): ?\Laravel\Cashier\Subscription
    {
        if (! $this->team) {
            return null;
        }

        return $this->team->subscription();
    }

    #[Computed]
    public function currentPlanCode(): string
    {
        $subscription = $this->currentSubscription;

        if ($subscription && $subscription->valid()) {
            return $this->resolvePlanCodeFromPriceId($subscription->stripe_price ?? '');
        }

        return (string) config('services.billing.free_plan_code', 'free');
    }

    protected function resolvePlanCodeFromPriceId(string $stripePriceId): string
    {
        if ($stripePriceId === '') {
            return (string) config('services.billing.free_plan_code', 'free');
        }

        $plans = (array) config('services.billing.plans', []);

        foreach ($plans as $code => $plan) {
            if (($plan['stripe_price_id'] ?? null) === $stripePriceId) {
                return (string) $code;
            }
        }

        return (string) config('services.billing.free_plan_code', 'free');
    }

    #[Computed]
    public function wallet(): ?TeamWallet
    {
        if (! $this->team) {
            return null;
        }

        return TeamWallet::where('team_id', $this->team->id)->first();
    }

    #[Computed]
    public function activeQuotaPolicy(): ?TeamQuotaPolicy
    {
        if (! $this->team) {
            return null;
        }

        return TeamQuotaPolicy::where('team_id', $this->team->id)
            ->where('is_active', true)
            ->orderByDesc('effective_from')
            ->first();
    }

    /**
     * @return Collection<int, array{code: string, name: string, description: string, monthly_price_cents: int, features: array<int, string>, daily_token_limit: ?int, weekly_token_limit: ?int, monthly_token_limit: ?int, is_current: bool}>
     */
    #[Computed]
    public function plans(): Collection
    {
        $plans = (array) config('services.billing.plans', []);
        $currentPlanCode = $this->currentPlanCode;

        return collect($plans)->map(function (array $plan, string $code) use ($currentPlanCode) {
            return [
                'code' => $code,
                'name' => $plan['name'] ?? ucfirst($code),
                'description' => $plan['description'] ?? '',
                'monthly_price_cents' => (int) ($plan['monthly_price_cents'] ?? 0),
                'features' => (array) ($plan['features'] ?? []),
                'daily_token_limit' => isset($plan['daily_token_limit']) ? (int) $plan['daily_token_limit'] : null,
                'weekly_token_limit' => isset($plan['weekly_token_limit']) ? (int) $plan['weekly_token_limit'] : null,
                'monthly_token_limit' => isset($plan['monthly_token_limit']) ? (int) $plan['monthly_token_limit'] : null,
                'is_current' => $code === $currentPlanCode,
            ];
        })->values();
    }

    /**
     * @return Collection<int, array{id: int, invoice_number: string, billing_month: string, currency: string, status: string, total_cents: int, issued_at: string, due_at: string, paid_at: ?string, payment_url: ?string}>
     */
    #[Computed]
    public function recentInvoices(): Collection
    {
        if (! $this->team) {
            return collect();
        }

        return BillingInvoice::where('team_id', $this->team->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn (BillingInvoice $invoice) => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'billing_month' => $invoice->billing_month?->format('M Y') ?? '—',
                'currency' => strtoupper($invoice->currency),
                'status' => $invoice->status,
                'total_cents' => $invoice->total_cents,
                'issued_at' => $invoice->issued_at?->toDateString() ?? '—',
                'due_at' => $invoice->due_at?->toDateString() ?? '—',
                'paid_at' => $invoice->paid_at?->toDateString(),
                'payment_url' => $invoice->payment_url,
            ]);
    }

    /**
     * @return Collection<int, array{id: int, type: string, amount_cents: int, balance_after_cents: int, currency: string, description: ?string, created_at: string}>
     */
    #[Computed]
    public function walletTransactions(): Collection
    {
        if (! $this->team) {
            return collect();
        }

        return TeamWalletTransaction::where('team_id', $this->team->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn (TeamWalletTransaction $tx) => [
                'id' => $tx->id,
                'type' => $tx->type,
                'amount_cents' => $tx->amount_cents,
                'balance_after_cents' => $tx->balance_after_cents,
                'currency' => strtoupper($tx->currency),
                'description' => $tx->description,
                'created_at' => $tx->created_at->toDateTimeString(),
            ]);
    }

    public function subscribeToPlan(string $planCode): void
    {
        abort_unless($this->canManageBilling, 403);

        $team = $this->team;
        abort_unless($team, 404);

        // Create or get a pending invoice for the subscription
        $planConfig = config("services.billing.plans.{$planCode}");
        abort_unless($planConfig, 404);

        $invoice = BillingInvoice::create([
            'team_id' => $team->id,
            'invoice_number' => 'INV-' . now()->format('Ymd') . '-' . str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT),
            'billing_month' => now()->startOfMonth(),
            'currency' => strtolower((string) config('services.billing.currency', 'USD')),
            'status' => 'draft',
            'subtotal_cents' => (int) ($planConfig['monthly_price_cents'] ?? 0),
            'tax_cents' => 0,
            'total_cents' => (int) ($planConfig['monthly_price_cents'] ?? 0),
            'issued_at' => now(),
            'due_at' => now()->addDays((int) config('services.billing.invoice_due_days', 7)),
        ]);

        try {
            $invoice = app(CreateStripeCheckoutSession::class)->handle($invoice);

            if ($invoice->payment_url) {
                $this->redirect($invoice->payment_url, navigate: false);
            }
        } catch (\RuntimeException $e) {
            \Flux\Flux::toast(variant: 'danger', text: __('Unable to create checkout session: :error', ['error' => $e->getMessage()]));
        }
    }

    public function rechargeWallet(): void
    {
        abort_unless($this->canManageBilling, 403);

        $this->validate([
            'rechargeAmount' => ['required', 'numeric', 'min:1', 'max:10000'],
        ]);

        $amountCents = (int) ((float) $this->rechargeAmount * 100);
        $team = $this->team;
        abort_unless($team, 404);

        try {
            $result = app(\App\Actions\Billing\CreateWalletRechargeSession::class)->handle(
                team: $team,
                amountCents: $amountCents,
                currency: strtoupper((string) config('services.billing.currency', 'USD')),
            );

            $this->rechargeAmount = '';
            $this->redirect($result['url'], navigate: false);
        } catch (\RuntimeException $e) {
            \Flux\Flux::toast(variant: 'danger', text: __('Unable to create recharge session: :error', ['error' => $e->getMessage()]));
        }
    }

    public function render()
    {
        return $this->view();
    }
}; ?>

<section class="w-full p-6">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        {{-- Page Header --}}
        <div>
            <flux:heading size="xl" level="1">{{ __('Billing & Subscription') }}</flux:heading>
            <flux:subheading>{{ __('Manage your plan, wallet, and invoices') }}</flux:subheading>
        </div>

        {{-- Current Plan & Wallet Summary --}}
        <div class="grid gap-4 md:grid-cols-3">
            {{-- Current Plan Card --}}
            <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Current Plan') }}</flux:text>
                <flux:heading level="2" size="lg" class="mt-1">
                    {{ ucfirst($this->currentPlanCode) }}
                </flux:heading>
                @if ($this->currentSubscription)
                    <div class="mt-2 flex items-center gap-2">
                        @if ($this->currentSubscription->valid())
                            <flux:badge color="green" size="sm">{{ __(ucfirst($this->currentSubscription->stripe_status)) }}</flux:badge>
                        @else
                            <flux:badge color="red" size="sm">{{ __(ucfirst($this->currentSubscription->stripe_status)) }}</flux:badge>
                        @endif
                    </div>
                @else
                    <flux:badge color="zinc" size="sm" class="mt-2">{{ __('No active subscription') }}</flux:badge>
                @endif
            </div>

            {{-- Wallet Balance Card --}}
            <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Wallet Balance') }}</flux:text>
                @if ($this->wallet)
                    <flux:heading level="2" size="lg" class="mt-1">
                        ${{ number_format($this->wallet->availableCents() / 100, 2) }}
                    </flux:heading>
                    <flux:text class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">
                        {{ $this->wallet->isPostpaid() ? __('Post-paid') : __('Pre-paid') }}
                        · {{ strtoupper($this->wallet->currency) }}
                    </flux:text>
                @else
                    <flux:heading level="2" size="lg" class="mt-1">$0.00</flux:heading>
                    <flux:text class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">{{ __('No wallet yet') }}</flux:text>
                @endif

                @if ($this->canManageBilling)
                    <flux:modal.trigger name="recharge-wallet" class="mt-3">
                        <flux:button variant="filled" size="sm" icon="plus">
                            {{ __('Top Up') }}
                        </flux:button>
                    </flux:modal.trigger>
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
                            @else
                                <flux:button variant="primary" class="w-full" wire:click="subscribeToPlan('{{ $plan['code'] }}')">
                                    {{ $this->currentPlanCode === 'free' ? __('Subscribe Now') : __('Switch Plan') }}
                                </flux:button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Wallet Transactions --}}
        @if ($this->walletTransactions->count() > 0)
            <div class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                <div class="p-6">
                    <flux:heading level="2">{{ __('Wallet Transactions') }}</flux:heading>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-t border-zinc-200 dark:border-zinc-700">
                                <th class="px-6 py-3 text-left font-medium text-zinc-500 dark:text-zinc-400">{{ __('Date') }}</th>
                                <th class="px-6 py-3 text-left font-medium text-zinc-500 dark:text-zinc-400">{{ __('Type') }}</th>
                                <th class="px-6 py-3 text-left font-medium text-zinc-500 dark:text-zinc-400">{{ __('Description') }}</th>
                                <th class="px-6 py-3 text-right font-medium text-zinc-500 dark:text-zinc-400">{{ __('Amount') }}</th>
                                <th class="px-6 py-3 text-right font-medium text-zinc-500 dark:text-zinc-400">{{ __('Balance After') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->walletTransactions as $tx)
                                <tr class="border-t border-zinc-200 dark:border-zinc-700">
                                    <td class="px-6 py-3 text-xs text-zinc-500 dark:text-zinc-400">{{ $tx['created_at'] }}</td>
                                    <td class="px-6 py-3">
                                        @if ($tx['type'] === 'credit' || $tx['type'] === 'recharge')
                                            <flux:badge color="green" size="sm">{{ ucfirst($tx['type']) }}</flux:badge>
                                        @elseif ($tx['type'] === 'debit' || $tx['type'] === 'usage')
                                            <flux:badge color="red" size="sm">{{ ucfirst($tx['type']) }}</flux:badge>
                                        @else
                                            <flux:badge color="zinc" size="sm">{{ ucfirst($tx['type']) }}</flux:badge>
                                        @endif
                                    </td>
                                    <td class="px-6 py-3 text-zinc-600 dark:text-zinc-300">{{ $tx['description'] ?? '—' }}</td>
                                    <td class="px-6 py-3 text-right font-mono {{ $tx['amount_cents'] >= 0 ? 'text-green-600' : 'text-red-500' }}">
                                        {{ $tx['amount_cents'] >= 0 ? '+' : '' }}{{ number_format($tx['amount_cents'] / 100, 2) }}
                                    </td>
                                    <td class="px-6 py-3 text-right font-mono">
                                        {{ $tx['currency'] }} {{ number_format($tx['balance_after_cents'] / 100, 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Recent Invoices --}}
        <div class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <div class="p-6">
                <flux:heading level="2">{{ __('Recent Invoices') }}</flux:heading>
            </div>

            @if ($this->recentInvoices->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-t border-zinc-200 dark:border-zinc-700">
                                <th class="px-6 py-3 text-left font-medium text-zinc-500 dark:text-zinc-400">{{ __('Invoice') }}</th>
                                <th class="px-6 py-3 text-left font-medium text-zinc-500 dark:text-zinc-400">{{ __('Billing Period') }}</th>
                                <th class="px-6 py-3 text-left font-medium text-zinc-500 dark:text-zinc-400">{{ __('Issued') }}</th>
                                <th class="px-6 py-3 text-left font-medium text-zinc-500 dark:text-zinc-400">{{ __('Due') }}</th>
                                <th class="px-6 py-3 text-right font-medium text-zinc-500 dark:text-zinc-400">{{ __('Amount') }}</th>
                                <th class="px-6 py-3 text-center font-medium text-zinc-500 dark:text-zinc-400">{{ __('Status') }}</th>
                                <th class="px-6 py-3 text-center font-medium text-zinc-500 dark:text-zinc-400">{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->recentInvoices as $invoice)
                                <tr class="border-t border-zinc-200 dark:border-zinc-700">
                                    <td class="px-6 py-3 font-medium">{{ $invoice['invoice_number'] }}</td>
                                    <td class="px-6 py-3 text-zinc-500 dark:text-zinc-400">{{ $invoice['billing_month'] }}</td>
                                    <td class="px-6 py-3 text-zinc-500 dark:text-zinc-400">{{ $invoice['issued_at'] }}</td>
                                    <td class="px-6 py-3 text-zinc-500 dark:text-zinc-400">{{ $invoice['due_at'] }}</td>
                                    <td class="px-6 py-3 text-right font-mono">
                                        {{ strtoupper($invoice['currency']) }} {{ number_format($invoice['total_cents'] / 100, 2) }}
                                    </td>
                                    <td class="px-6 py-3 text-center">
                                        @if ($invoice['status'] === 'paid')
                                            <flux:badge color="green" size="sm">{{ __('Paid') }}</flux:badge>
                                        @elseif ($invoice['status'] === 'overdue')
                                            <flux:badge color="red" size="sm">{{ __('Overdue') }}</flux:badge>
                                        @elseif ($invoice['status'] === 'issued')
                                            <flux:badge color="orange" size="sm">{{ __('Issued') }}</flux:badge>
                                        @elseif ($invoice['status'] === 'void')
                                            <flux:badge color="zinc" size="sm">{{ __('Void') }}</flux:badge>
                                        @else
                                            <flux:badge color="zinc" size="sm">{{ __(ucfirst($invoice['status'])) }}</flux:badge>
                                        @endif
                                    </td>
                                    <td class="px-6 py-3 text-center">
                                        @if ($invoice['payment_url'] && $invoice['status'] === 'issued')
                                            <a href="{{ $invoice['payment_url'] }}" target="_blank" class="text-blue-500 hover:text-blue-600 underline">
                                                {{ __('Pay') }}
                                            </a>
                                        @elseif ($invoice['status'] === 'paid')
                                            <flux:text class="text-zinc-400">{{ __('—') }}</flux:text>
                                        @else
                                            <flux:text class="text-zinc-400">{{ __('—') }}</flux:text>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="px-6 pb-12 text-center text-zinc-400">
                    <flux:icon name="document-text" class="mx-auto mb-3 size-10 text-zinc-300 dark:text-zinc-600" />
                    <flux:text>{{ __('No invoices yet. Invoices are generated monthly based on your usage.') }}</flux:text>
                </div>
            @endif
        </div>
    </div>

    {{-- Recharge Wallet Modal --}}
    @if ($this->canManageBilling)
        <flux:modal name="recharge-wallet" class="md:w-96">
            <form wire:submit="rechargeWallet" class="space-y-4">
                <div>
                    <flux:heading level="2">{{ __('Top Up Wallet') }}</flux:heading>
                    <flux:subheading>{{ __('Add funds to your team wallet via Stripe.') }}</flux:subheading>
                </div>

                <flux:field>
                    <flux:label>{{ __('Amount (USD)') }}</flux:label>
                    <flux:input
                        wire:model="rechargeAmount"
                        type="number"
                        step="0.01"
                        min="1"
                        max="10000"
                        :placeholder="__('e.g. 50.00')"
                        required
                    />
                    <flux:description>{{ __('Minimum $1.00, maximum $10,000.00') }}</flux:description>
                </flux:field>

                <div class="flex justify-end gap-2">
                    <flux:button variant="ghost" x-on:click="$flux.modal.close('recharge-wallet')">
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button variant="primary" type="submit">
                        {{ __('Proceed to Payment') }}
                    </flux:button>
                </div>
            </form>
        </flux:modal>
    @endif
</section>
