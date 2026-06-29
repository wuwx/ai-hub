<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read Collection<int, LlmModel> $models
 */
#[Fillable([
    'name',
    'slug',
    'adapter_type',
    'base_url',
    'auth_mode',
    'secret_ref',
    'options',
    'is_active',
    'last_health_status',
    'last_health_checked_at',
    'last_health_error',
])]
class LlmProvider extends Model
{
    /**
     * @return HasMany<LlmModel, $this>
     */
    public function models(): HasMany
    {
        return $this->hasMany(LlmModel::class);
    }

    /**
     * @return HasMany<TeamProviderEntitlement, $this>
     */
    public function entitlements(): HasMany
    {
        return $this->hasMany(TeamProviderEntitlement::class);
    }

    /**
     * @return HasMany<RequestLog, $this>
     */
    public function requestLogs(): HasMany
    {
        return $this->hasMany(RequestLog::class);
    }

    /**
     * @return HasMany<UsageLedger, $this>
     */
    public function usageLedgers(): HasMany
    {
        return $this->hasMany(UsageLedger::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'secret_ref' => 'encrypted',
            'options' => 'array',
            'is_active' => 'boolean',
            'last_health_checked_at' => 'datetime',
        ];
    }
}
