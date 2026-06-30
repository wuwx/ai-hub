<?php

namespace App\Console\Commands;

use App\Actions\Webhooks\DispatchWebhookEvent;
use App\Models\TeamWallet;
use App\Notifications\Teams\WalletBalanceLow;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

#[Signature('billing:check-wallet-balances {--threshold=500 : Minimum balance in cents to trigger alert}')]
#[Description('Notify team owners when pre-paid wallet balance drops below the threshold.')]
class CheckWalletBalances extends Command
{
    public function __construct(
        private readonly DispatchWebhookEvent $dispatchWebhookEvent,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $threshold = max(0, (int) $this->option('threshold'));

        $wallets = TeamWallet::query()
            ->where('is_postpaid', false)
            ->where('balance_cents', '>', 0)
            ->where('balance_cents', '<=', $threshold)
            ->with('team')
            ->get();

        if ($wallets->isEmpty()) {
            $this->info('No wallets below the threshold.');

            return self::SUCCESS;
        }

        $notified = 0;

        foreach ($wallets as $wallet) {
            $team = $wallet->team;

            if (! $team) {
                continue;
            }

            // Dedupe: alert at most once per day per wallet.
            $cacheKey = sprintf('billing:wallet-low-alert:%d', $wallet->id);

            if (! Cache::add($cacheKey, true, now()->endOfDay())) {
                continue;
            }

            $owner = $team->owner();

            if ($owner) {
                $owner->notify(new WalletBalanceLow(
                    teamName: $team->name,
                    balanceCents: $wallet->balance_cents,
                    currency: $wallet->currency,
                    thresholdCents: $threshold,
                ));

                $notified++;
            }

            $this->dispatchWebhookEvent->handle($team, 'wallet.balance_low', [
                'balance_cents' => $wallet->balance_cents,
                'currency' => $wallet->currency,
                'threshold_cents' => $threshold,
            ]);
        }

        $this->info(sprintf('Checked %d wallet(s), notified %d owner(s).', $wallets->count(), $notified));

        return self::SUCCESS;
    }
}
