<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'team_id',
    'team_wallet_id',
    'source_type',
    'source_id',
    'type',
    'amount_cents',
    'balance_after_cents',
    'currency',
    'description',
    'metadata',
    'reference_id',
])]
class TeamWalletTransaction extends Model
{
    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<TeamWallet, $this>
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(TeamWallet::class, 'team_wallet_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'balance_after_cents' => 'integer',
            'metadata' => 'array',
        ];
    }
}
