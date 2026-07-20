<?php

namespace App\Data;

use Laravel\Sanctum\PersonalAccessToken;

readonly class GeneratedApiKey
{
    public function __construct(
        public PersonalAccessToken $token,
        public string $plainTextToken,
    ) {
        //
    }
}
