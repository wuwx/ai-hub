<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'trace_id',
    'team_id',
    'api_key_id',
    'llm_provider_id',
    'llm_model_id',
    'protocol',
    'endpoint',
    'http_method',
    'is_streaming',
    'tool_calls_count',
    'status_code',
    'token_input',
    'token_output',
    'token_total',
    'latency_ms',
    'error_code',
    'error_message',
    'requested_at',
])]
class RequestLog extends Model
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
            'is_streaming' => 'boolean',
            'tool_calls_count' => 'integer',
            'status_code' => 'integer',
            'token_input' => 'integer',
            'token_output' => 'integer',
            'token_total' => 'integer',
            'latency_ms' => 'integer',
            'requested_at' => 'datetime',
        ];
    }
}
