<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'user_id',
    'url',
    'secret',
    'events',
    'is_active',
    'last_triggered_at',
    'failure_count',
])]
class WebhookEndpoint extends Model
{
    protected $table = 'webhook_endpoints';

    protected static function booted(): void
    {
        static::creating(function (self $webhook) {
            if (empty($webhook->secret)) {
                $webhook->secret = Str::random(32);
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'events' => 'array',
            'is_active' => 'boolean',
            'last_triggered_at' => 'datetime',
            'failure_count' => 'integer',
        ];
    }

    /**
     * Determine if this endpoint should receive the given event type.
     */
    public function subscribesTo(string $event): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $events = $this->events;

        // Null or empty events list means "subscribe to all".
        if (empty($events)) {
            return true;
        }

        return in_array($event, $events, true);
    }
}
