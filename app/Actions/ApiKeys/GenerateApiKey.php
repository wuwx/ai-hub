<?php

namespace App\Actions\ApiKeys;

use App\Data\GeneratedApiKey;
use App\Models\User;
use Carbon\CarbonInterface;

class GenerateApiKey
{
    /**
     * Create a new Sanctum token for the user.
     */
    public function handle(User $user, string $name, ?CarbonInterface $expiresAt = null): GeneratedApiKey
    {
        $newToken = $user->createToken($name, ['*'], $expiresAt);

        return new GeneratedApiKey($newToken->accessToken, $newToken->plainTextToken);
    }
}
