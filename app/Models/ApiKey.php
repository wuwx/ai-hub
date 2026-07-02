<?php

namespace App\Models;

use App\Actions\ApiKeys\GenerateApiKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'name',
    'key_hash',
    'last_four',
    'allowed_models',
    'allowed_ips',
    'daily_token_limit',
    'rate_limit_per_minute',
    'last_used_at',
    'expires_at',
    'revoked_at',
    'created_by',
])]
class ApiKey extends Model
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isActive(): bool
    {
        return ! $this->isRevoked() && ! $this->isExpired();
    }

    public static function hashPlainTextKey(string $plainTextKey): string
    {
        return app(GenerateApiKey::class)->hashKey($plainTextKey);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'allowed_models' => 'array',
            'allowed_ips' => 'array',
            'daily_token_limit' => 'integer',
            'rate_limit_per_minute' => 'integer',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function canAccessModel(string $externalModelId): bool
    {
        if (empty($this->allowed_models)) {
            return true;
        }

        return in_array($externalModelId, $this->allowed_models, true);
    }

    /**
     * Determine if the given IP address is allowed to use this key.
     *
     * Supports individual IP addresses and CIDR ranges (e.g. 192.168.1.0/24).
     * An empty allow-list means "any IP".
     */
    public function isIpAllowed(string $ip): bool
    {
        $allowedIps = $this->allowed_ips;

        if (empty($allowedIps)) {
            return true;
        }

        foreach ($allowedIps as $allowed) {
            if ($this->ipMatches($ip, $allowed)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if an IP matches a pattern (single IP or CIDR range).
     */
    protected function ipMatches(string $ip, string $pattern): bool
    {
        if (str_contains($pattern, '/')) {
            return $this->ipInCidr($ip, $pattern);
        }

        return $ip === $pattern;
    }

    /**
     * Check if an IP is within a CIDR range.
     */
    protected function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $maskBits] = explode('/', $cidr, 2);
        $maskBits = (int) $maskBits;

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);

        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        $mask = $maskBits === 0 ? 0 : (~0 << (32 - $maskBits));

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }
}
