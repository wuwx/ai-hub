<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'team_id',
    'payment_provider',
    'stripe_customer_id',
    'stripe_subscription_id',
    'plan_code',
    'status',
    'cancel_at_period_end',
    'current_period_start',
    'current_period_end',
    'meta',
])]
class TeamBillingSubscription extends Model
{
    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cancel_at_period_end' => 'boolean',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'meta' => 'array',
        ];
    }
}
