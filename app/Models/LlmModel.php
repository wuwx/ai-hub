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
        'is_active',
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
    ];

    /**
     * @return BelongsTo<LlmProvider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(LlmProvider::class, 'llm_provider_id');
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
        ];
    }
}
