<?php

namespace App\Actions\ApiKeys;

use App\Data\GeneratedApiKey;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

class RotateApiKey
{
    public function __construct(private readonly GenerateApiKey $generator)
    {
        //
    }

    /**
     * Rotate a token: delete the old one and issue a fresh token with the
     * same name and expiry.
     */
    public function handle(PersonalAccessToken $token): GeneratedApiKey
    {
        /** @var User $user */
        $user = $token->tokenable;
        $name = $token->name;
        $expiresAt = $token->expires_at;

        $token->delete();

        return $this->generator->handle($user, $name, $expiresAt);
    }
}
