<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'team_id',
    'api_key_id',
    'llm_provider_id',
    'llm_model_id',
    'bucket_date',
    'bucket_type',
    'token_input',
    'token_output',
    'token_total',
    'request_count',
    'error_count',
])]
class UsageLedger extends Model
{
    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<ApiKey, $this>
     */
    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }

    /**
     * @return BelongsTo<LlmProvider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(LlmProvider::class, 'llm_provider_id');
    }

    /**
     * @return BelongsTo<LlmModel, $this>
     */
    public function llmModel(): BelongsTo
    {
        return $this->belongsTo(LlmModel::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bucket_date' => 'date',
            'token_input' => 'integer',
            'token_output' => 'integer',
            'token_total' => 'integer',
            'request_count' => 'integer',
            'error_count' => 'integer',
        ];
    }
}
