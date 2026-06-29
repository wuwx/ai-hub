<?php

namespace App\Actions\ApiKeys;

use App\Data\GeneratedApiKey;
use App\Models\ApiKey;
use Illuminate\Support\Str;

class RotateApiKey
{
    public function __construct(private readonly GenerateApiKey $generator)
    {
        //
    }

    public function handle(ApiKey $apiKey): GeneratedApiKey
    {
        $plainTextKey = 'ahk_'.Str::random(48);

        $apiKey->update([
            'key_hash' => $this->generator->hashKey($plainTextKey),
            'last_four' => Str::substr($plainTextKey, -4),
            'revoked_at' => null,
        ]);

        return new GeneratedApiKey($apiKey->refresh(), $plainTextKey);
    }
}
