<?php

namespace App\Actions\ApiKeys;

use App\Data\GeneratedApiKey;
use App\Models\ApiKey;
use App\Models\Team;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class GenerateApiKey
{
    public function handle(Team $team, string $name, ?Carbon $expiresAt = null, ?int $createdBy = null): GeneratedApiKey
    {
        $plainTextKey = $this->makePlainTextKey();

        $apiKey = ApiKey::create([
            'team_id' => $team->id,
            'name' => $name,
            'key_hash' => $this->hashKey($plainTextKey),
            'last_four' => Str::substr($plainTextKey, -4),
            'expires_at' => $expiresAt,
            'created_by' => $createdBy,
        ]);

        return new GeneratedApiKey($apiKey, $plainTextKey);
    }

    public function hashKey(string $plainTextKey): string
    {
        return hash('sha256', $plainTextKey);
    }

    protected function makePlainTextKey(): string
    {
        return 'ahk_'.Str::random(48);
    }
}
