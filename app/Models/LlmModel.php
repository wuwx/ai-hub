<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[
    Fillable([
        'llm_provider_id',
        'name',
        'external_model_id',
        'capabilities',
        'pricing',
        'context_window',
        'max_output_tokens',
        'cost_input_per_1m_usd',
        'cost_output_per_1m_usd',
        'sell_input_per_1m_usd',
        'sell_output_per_1m_usd',
        'markup_percent',
        'is_active',
        'fallback_model_id',
    ]),
]
/**
 * @property array<mixed> $pricing
 * @property array<mixed> $capabilities
 */
class LlmModel extends Model
{
    /**
     * @var array<string, string>
     */
    protected $casts = [
        'capabilities' => 'array',
        'pricing' => 'array',
        'is_active' => 'boolean',
        'cost_input_per_1m_usd' => 'float',
        'cost_output_per_1m_usd' => 'float',
        'sell_input_per_1m_usd' => 'float',
        'sell_output_per_1m_usd' => 'float',
        'markup_percent' => 'integer',
    ];

    /**
     * @return BelongsTo<LlmProvider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(LlmProvider::class, 'llm_provider_id');
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function fallbackModel(): BelongsTo
    {
        return $this->belongsTo(self::class, 'fallback_model_id');
    }

    /**
     * @return HasMany<PlanModelEntitlement, $this>
     */
    public function planEntitlements(): HasMany
    {
        return $this->hasMany(PlanModelEntitlement::class);
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
            'capabilities' => 'array',
            'pricing' => 'array',
            'is_active' => 'boolean',
            'cost_input_per_1m_usd' => 'float',
            'cost_output_per_1m_usd' => 'float',
            'sell_input_per_1m_usd' => 'float',
            'sell_output_per_1m_usd' => 'float',
            'markup_percent' => 'integer',
        ];
    }
}
