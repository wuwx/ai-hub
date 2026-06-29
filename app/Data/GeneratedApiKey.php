<?php

namespace App\Data;

use App\Models\ApiKey;

readonly class GeneratedApiKey
{
    public function __construct(
        public ApiKey $apiKey,
        public string $plainTextKey,
    ) {
        //
    }
}
