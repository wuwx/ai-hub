<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[
    Fillable([
        'code',
        'name',
        'description',
        'monthly_price_cents',
        'stripe_price_id',
        'daily_token_limit',
        'weekly_token_limit',
        'monthly_token_limit',
        'features',
        'is_active',
        'sort_order',
    ]),
]
class Plan extends Model
{
    protected $table = 'plans';

    /**
     * @return HasMany<PlanModelEntitlement, $this>
     */
    public function modelEntitlements(): HasMany
    {
        return $this->hasMany(PlanModelEntitlement::class, 'plan_code', 'code');
    }

    /**
     * @return HasMany<PlanProviderEntitlement, $this>
     */
    public function providerEntitlements(): HasMany
    {
        return $this->hasMany(PlanProviderEntitlement::class, 'plan_code', 'code');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeByCode(Builder $query, string $code): Builder
    {
        return $query->where('code', $code);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'features' => 'array',
            'is_active' => 'boolean',
            'monthly_price_cents' => 'integer',
            'daily_token_limit' => 'integer',
            'weekly_token_limit' => 'integer',
            'monthly_token_limit' => 'integer',
            'sort_order' => 'integer',
        ];
    }
}
