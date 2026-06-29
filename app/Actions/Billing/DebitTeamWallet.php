<?php

namespace App\Actions\Billing;

use App\Exceptions\InsufficientWalletBalanceException;
use App\Models\Team;
use App\Models\TeamWallet;
use App\Models\TeamWalletTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class DebitTeamWallet
{
    /**
     * Atomically debit the team wallet for a usage charge.
     *
     * Pre-paid teams are rejected when the available balance (cash + credit)
     * is insufficient. Post-paid teams always succeed (balance goes negative
     * and is collected via the monthly invoice).
     *
     * @param  array<string, mixed>  $metadata
     */
    public function handle(
        Team $team,
        int $amountCents,
        string $description,
        ?Model $source = null,
        ?string $referenceId = null,
        array $metadata = [],
    ): ?TeamWalletTransaction {
        if ($amountCents <= 0) {
            // Zero-cost requests (e.g. free tier, unmapped model pricing) are
            // not debited. The RequestLog still records the usage.
            return null;
        }

        return DB::transaction(function () use ($team, $amountCents, $description, $source, $referenceId, $metadata) {
            /** @var TeamWallet $wallet */
            $wallet = TeamWallet::query()
                ->where('team_id', $team->id)
                ->lockForUpdate()
                ->firstOrCreate(
                    ['team_id' => $team->id],
                    [
                        'balance_cents' => 0,
                        'credit_grant_cents' => 0,
                        'currency' => config('services.billing.currency', 'USD'),
                        'is_postpaid' => false,
                    ],
                );

            $available = $wallet->availableCents();

            if ($wallet->isPrepaid() && $available < $amountCents) {
                throw new InsufficientWalletBalanceException($amountCents, $available);
            }

            // Burn promo credit first, then cash balance.
            $creditUsed = min($wallet->credit_grant_cents, $amountCents);
            $cashUsed = $amountCents - $creditUsed;

            $wallet->credit_grant_cents -= $creditUsed;
            $wallet->balance_cents -= $cashUsed;
            $wallet->save();

            return TeamWalletTransaction::create([
                'team_id' => $team->id,
                'team_wallet_id' => $wallet->id,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'type' => 'debit',
                'amount_cents' => -$amountCents,
                'balance_after_cents' => $wallet->availableCents(),
                'currency' => $wallet->currency,
                'description' => $description,
                'metadata' => array_merge($metadata, [
                    'credit_used_cents' => $creditUsed,
                    'cash_used_cents' => $cashUsed,
                ]),
                'reference_id' => $referenceId,
            ]);
        });
    }

    /**
     * Pre-flight check without committing a debit. Used by the gateway
     * to reject a request before forwarding it to the upstream provider.
     */
    public function hasEnoughBalance(Team $team, int $amountCents): bool
    {
        $wallet = TeamWallet::query()->where('team_id', $team->id)->first();

        if (! $wallet) {
            // No wallet record means the team has neither recharged nor been
            // provisioned as post-paid. Treat as pre-paid with zero balance.
            return false;
        }

        if ($wallet->isPostpaid()) {
            return true;
        }

        return $wallet->availableCents() >= $amountCents;
    }
}
