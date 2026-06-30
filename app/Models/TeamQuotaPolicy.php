<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'team_id',
    'daily_token_limit',
    'weekly_token_limit',
    'monthly_token_limit',
    'daily_spend_limit_cents',
    'daily_alert_threshold',
    'monthly_alert_threshold',
    'effective_from',
    'effective_to',
    'is_active',
])]
class TeamQuotaPolicy extends Model
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
            'daily_token_limit' => 'integer',
            'weekly_token_limit' => 'integer',
            'monthly_token_limit' => 'integer',
            'daily_spend_limit_cents' => 'integer',
            'daily_alert_threshold' => 'integer',
            'monthly_alert_threshold' => 'integer',
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
            'is_active' => 'boolean',
        ];
    }
}
