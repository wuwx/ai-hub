<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable([
    'team_id',
    'balance_cents',
    'credit_grant_cents',
    'currency',
    'is_postpaid',
    'last_recharged_at',
])]
class TeamWallet extends Model
{
    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return HasMany<TeamWalletTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(TeamWalletTransaction::class);
    }

    /**
     * @return MorphMany<TeamWalletTransaction, $this>
     */
    public function sourcedTransactions(): MorphMany
    {
        return $this->morphMany(TeamWalletTransaction::class, 'source');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'balance_cents' => 'integer',
            'credit_grant_cents' => 'integer',
            'is_postpaid' => 'boolean',
            'last_recharged_at' => 'datetime',
        ];
    }

    /**
     * Effective spendable balance: cash + promo credit grant.
     */
    public function availableCents(): int
    {
        return $this->balance_cents + $this->credit_grant_cents;
    }

    public function isPrepaid(): bool
    {
        return ! $this->is_postpaid;
    }

    public function isPostpaid(): bool
    {
        return (bool) $this->is_postpaid;
    }
}
