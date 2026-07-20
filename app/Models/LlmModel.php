<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
     * @return BelongsTo<LlmProvider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(LlmProvider::class, 'llm_provider_id');
    }

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'capabilities' => 'array',
        'pricing' => 'array',
        'is_active' => 'boolean',
    ];
}
