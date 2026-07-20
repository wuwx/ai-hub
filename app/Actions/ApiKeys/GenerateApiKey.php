<?php

namespace App\Actions\ApiKeys;

use App\Models\User;
use Carbon\CarbonInterface;
use Laravel\Sanctum\NewAccessToken;

class GenerateApiKey
{
    /**
     * Create a new Sanctum token for the user.
     */
    public function handle(User $user, string $name, ?CarbonInterface $expiresAt = null): NewAccessToken
    {
        return $user->createToken($name, ['*'], $expiresAt);
    }
}
