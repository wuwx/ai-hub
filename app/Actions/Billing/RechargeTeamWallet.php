<?php

namespace App\Actions\Billing;

use App\Models\Team;
use App\Models\TeamWallet;
use App\Models\TeamWalletTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class RechargeTeamWallet
{
    /**
     * Credit the team wallet after a successful payment.
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
    ): TeamWalletTransaction {
        if ($amountCents <= 0) {
            throw new \InvalidArgumentException('Recharge amount must be positive.');
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

            $wallet->balance_cents += $amountCents;
            $wallet->last_recharged_at = now();
            $wallet->save();

            return TeamWalletTransaction::create([
                'team_id' => $team->id,
                'team_wallet_id' => $wallet->id,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'type' => 'recharge',
                'amount_cents' => $amountCents,
                'balance_after_cents' => $wallet->availableCents(),
                'currency' => $wallet->currency,
                'description' => $description,
                'metadata' => $metadata,
                'reference_id' => $referenceId,
            ]);
        });
    }
}
