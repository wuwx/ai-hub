<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'webhook_endpoint_id',
    'event',
    'payload',
    'response_status_code',
    'response_body',
    'succeeded',
    'latency_ms',
    'error',
    'attempt_count',
    'next_retry_at',
])]
class WebhookDelivery extends Model
{
    /**
     * @return BelongsTo<WebhookEndpoint, $this>
     */
    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'response_status_code' => 'integer',
            'succeeded' => 'boolean',
            'latency_ms' => 'integer',
            'attempt_count' => 'integer',
            'next_retry_at' => 'datetime',
        ];
    }
}
