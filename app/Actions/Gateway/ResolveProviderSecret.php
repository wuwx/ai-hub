<?php

namespace App\Actions\Gateway;

use Illuminate\Support\Str;

class ResolveProviderSecret
{
    public function handle(?string $secretRef): ?string
    {
        if (! $secretRef) {
            return null;
        }

        if (Str::startsWith($secretRef, 'env://')) {
            return env(Str::after($secretRef, 'env://'));
        }

        if (Str::startsWith($secretRef, 'env:')) {
            return env(Str::after($secretRef, 'env:'));
        }

        if (Str::startsWith($secretRef, 'config://')) {
            return config(Str::after($secretRef, 'config://'));
        }

        if (Str::startsWith($secretRef, 'literal://')) {
            return Str::after($secretRef, 'literal://');
        }

        return $secretRef;
    }
}
