<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'team_webhook_endpoint_id',
    'event',
    'payload',
    'response_status_code',
    'response_body',
    'succeeded',
    'latency_ms',
    'error',
])]
class WebhookDelivery extends Model
{
    /**
     * @return BelongsTo<TeamWebhookEndpoint, $this>
     */
    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(TeamWebhookEndpoint::class, 'team_webhook_endpoint_id');
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
        ];
    }
}
